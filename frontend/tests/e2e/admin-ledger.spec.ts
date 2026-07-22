import { expect, test } from '@playwright/test'

test('admin can login and open core business pages', async ({ page }) => {
  const admin = {
    id: 1,
    sub2api_user_id: 1,
    name: '管理员',
    username: 'admin',
    email: 'admin@example.com',
    status: 'active',
  }
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname
    let body: unknown = { items: [], total: 0, page: 1, page_size: 20 }

    if (path.endsWith('/finance/cash')) {
      body = {
        ...body as object,
        summary: {
          record_count: 0,
          user_count: 0,
          amount_total: '0.00',
          linked_count: 0,
          unlinked_count: 0,
        },
      }
    } else if (path.endsWith('/ledger-adjustments/user-stats')) {
      body = { ...body as object, granularity: 'day' }
    } else if (path.endsWith('/finance/gifts')) {
      body = {
        ...body as object,
        summary: { record_count: 0, user_count: 0, amount_total: '0.00', linked_count: 0, unlinked_count: 0 },
      }
    } else if (path.endsWith('/finance/expenses')) {
      body = {
        ...body as object,
        categories: [],
        summary: { record_count: 0, category_count: 0, amount_total: '0.00', max_amount: '0.00', daily_average: null },
      }
    } else if (path.endsWith('/finance/history')) {
      body = {
        ...body as object,
        summary: { record_count: 0, income_count: 0, expense_count: 0, gift_count: 0, income_total: '0.00', expense_total: '0.00', gift_total: '0.00' },
      }
    } else if (path.endsWith('/profit/summary')) {
      body = {
        owners: [],
        days: [],
        summary: { income_total: '0.00', expense_total: '0.00', profit_total: '0.00', income_count: 0, expense_count: 0 },
        pending_summary: { income_total: '0.00', expense_total: '0.00', profit_total: '0.00', income_count: 0, expense_count: 0 },
      }
    } else if (path.endsWith('/audit-logs')) {
      body = {
        ...body as object,
        summary: { record_count: 0, operator_count: 0, action_count: 0, target_count: 0, high_risk_count: 0, actions: [] },
      }
    }

    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })
  })
  await page.route('**/api/v1/auth/login', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ identity_type: 'admin', token: 'e2e-admin-token', admin }),
  }))
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ admin }),
  }))
  await page.route('**/api/v1/dashboard**', (route) => route.abort('failed'))
  await page.goto('/login')
  await page.getByPlaceholder('Sub2API 用户邮箱').fill('admin@example.com')
  await page.getByPlaceholder('密码').fill('secret123')
  await page.locator('button[type="submit"]').click()

  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('button', { name: '刷新' })).toBeVisible()

  const pages = [
    { path: '/ledger', title: '收入' },
    { path: '/balance-events', title: '历史账' },
    { path: '/users-quota', title: '用户充值' },
    { path: '/operation-expense', title: '支出' },
    { path: '/profit', title: '利润统计' },
    { path: '/audit-log', title: '操作审计' },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page).toHaveURL(new RegExp(`${item.path}$`))
    await expect(page.locator('.soyBreadcrumb')).toHaveText(item.title)
  }
})
