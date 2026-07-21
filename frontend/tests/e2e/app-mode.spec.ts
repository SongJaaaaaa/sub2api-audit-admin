import { expect, test } from '@playwright/test'

const admin = {
  id: 1,
  sub2api_user_id: 1,
  name: '管理员',
  username: 'admin',
  email: 'admin@example.com',
  status: 'active',
}

test('App mode opens the module home without desktop navigation and renders mobile ranking cards', async ({ page }) => {
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/auth/login')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ identity_type: 'admin', token: 'app-e2e-token', admin }),
      })
    }
    if (path.endsWith('/auth/me')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ admin }) })
    }
    if (path.endsWith('/dashboard')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          range: { start_date: '2026-07-14', end_date: '2026-07-20', timezone: 'Asia/Shanghai' },
          cutover_at: null,
          finance: { cash_total: '100.00', gift_total: '20.00', adjustment_in_total: '120.00', adjustment_out_total: '0.00', adjustment_net_total: '120.00', trend: [] },
          usage: { request_count: 2, total_tokens: 300, standard_cost: '0.00', actual_cost: '1.25', trend: [] },
          balance: { active_user_count: 1, active_user_balance: '80.00', total_recharged: '100.00', as_of: '2026-07-20 10:00:00' },
          rankings: { recharge_users: [], user_tokens: [], user_actual_cost: [], models: [] },
          recent_adjustments: [{ id: 1, ledger_no: 'ADJ001', sub2api_source_id: 9, sub2api_user_id: 10, sub2api_user_email: 'user@example.com', operation: 'increment', amount: '100.00', cash_amount: '80.00', gift_quota_amount: '20.00', status: 'succeeded', adjust_reason: '充值', exception_reason: null, event_at: '2026-07-20 10:00:00' }],
          alerts: { unlinked_adjustment_count: 0 },
        }),
      })
    }
    if (path.endsWith('/sub2api/model-stats')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          range: { start_date: '2026-07-14', end_date: '2026-07-20', timezone: 'Asia/Shanghai' },
          model_source: 'requested',
          selected_model: null,
          summary: { model_count: 1, request_count: 2, total_tokens: 300, cache_tokens: 0, cache_rate: 0, standard_cost: '0.00', actual_cost: '0.00', top3_token_rate: 1 },
          models: [{ model: 'claude-3-7-sonnet', request_count: 2, input_tokens: 100, output_tokens: 200, cache_creation_tokens: 0, cache_read_tokens: 0, total_tokens: 300, standard_cost: '0.00', actual_cost: '0.00' }],
          users: [],
        }),
      })
    }
    if (path.endsWith('/audit-logs')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [{ id: 1, admin_id: 1, admin_name: '管理员', action: 'admin.create', target_type: 'admin', target_id: 2, before_value: null, after_value: { name: '新管理员' }, ip: '127.0.0.1', user_agent: 'app-e2e', created_at: '2026-07-20 10:00:00' }], total: 1, page: 1, page_size: 20, summary: { record_count: 1, operator_count: 1, action_count: 1, target_count: 1, high_risk_count: 1, actions: [{ action: 'admin.create', record_count: 1 }] } }),
      })
    }
    if (path.endsWith('/admins')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [{ id: 1, sub2api_user_id: 1, name: '管理员', email: 'admin@example.com', status: 'active', created_at: '2026-07-20 10:00:00' }], total: 1, page: 1, page_size: 20, summary: { admin_count: 1, active_count: 1, disabled_count: 0 } }),
      })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [], total: 0, page: 1, page_size: 20 }) })
  })

  await page.goto('/login')
  await page.getByPlaceholder('请输入账号').fill(admin.email)
  await page.getByPlaceholder('请输入密码').fill('secret123')
  await page.locator('button[type="submit"]').click()

  await expect(page).toHaveURL(/\/app$/)
  await expect(page.getByText(/^(首页|数据总览)$/).first()).toBeVisible()
  for (const label of ['消费排行', 'Sub2API 用户', '用户充值', '收入', '支出', '历史账', '利润统计', '异常中心', '操作审计', '管理员账号']) {
    await expect(page.getByText(label, { exact: true }).first()).toBeVisible()
  }
  await expect(page.locator('.soySider')).toHaveCount(0)
  await expect(page.locator('.mobileDrawerContent')).toHaveCount(0)

  await page.getByText('消费排行', { exact: true }).first().click()
  await expect(page).toHaveURL(/\/sub2api\/models$/)
  await expect(page.locator('.appStatList')).toBeVisible()
  await expect(page.locator('.appStatCard')).toContainText('claude-3-7-sonnet')
  await expect(page.locator('.soySider')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  await page.getByRole('button', { name: '返回' }).click()
  await expect(page).toHaveURL(/\/app$/)

  await page.goto('/')
  await expect(page.locator('.appDashboardKpis')).toBeVisible()
  await expect(page.locator('.appRecordCard')).toContainText('user@example.com')

  await page.goto('/audit-log')
  await expect(page.locator('.appAuditCard')).toContainText('管理员')
  await expect(page.locator('.soySider')).toHaveCount(0)
  await page.goto('/admins')
  await expect(page.locator('.appAdminCard')).toContainText('admin@example.com')
})

test('App quota paste fallback sends one request after repeated clicks and paste input events', async ({ page }) => {
  const queries: string[] = []
  await page.route('**/api/v1/**', (route) => {
    const url = new URL(route.request().url())
    if (url.pathname.endsWith('/auth/login')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ identity_type: 'admin', token: 'app-e2e-token', admin }),
      })
    }
    if (url.pathname.endsWith('/auth/me')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ admin }) })
    }
    if (url.pathname.endsWith('/sub2api/users')) {
      queries.push(url.searchParams.get('keyword') || '')
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [], total: 0, page: 1, page_size: 10 }),
      })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{}' })
  })

  await page.goto('/login')
  await page.getByPlaceholder('请输入账号').fill(admin.email)
  await page.getByPlaceholder('请输入密码').fill('secret123')
  await page.locator('button[type="submit"]').click()
  await page.goto('/users-quota')
  await expect.poll(() => queries).toEqual([''])

  await page.getByRole('button', { name: '粘贴搜索' }).click()
  await page.getByRole('button', { name: '粘贴搜索' }).click()
  await page.getByPlaceholder('邮箱或用户名/ID').evaluate((input) => {
    const data = new DataTransfer()
    data.setData('text/plain', 'search@example.com')
    input.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: data }))
    input.value = 'search@example.com'
    input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste', data: 'search@example.com' }))
  })

  await expect.poll(() => queries).toEqual(['', 'search@example.com'])
  await page.waitForTimeout(300)
  expect(queries).toEqual(['', 'search@example.com'])

  await page.evaluate(() => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: {
        readText: () => new Promise((resolve) => {
          window.setTimeout(() => resolve('direct@example.com'), 80)
        }),
      },
    })
  })
  await page.getByRole('button', { name: '粘贴搜索' }).dblclick()

  await expect.poll(() => queries).toEqual(['', 'search@example.com', 'direct@example.com'])
  await page.waitForTimeout(300)
  expect(queries).toEqual(['', 'search@example.com', 'direct@example.com'])
})

test('App login displays an unavailable server error', async ({ page }) => {
  await page.route('**/api/v1/auth/login', (route) => route.fulfill({
    status: 502,
    contentType: 'application/json',
    body: JSON.stringify({ message: 'Sub2API 登录服务暂不可用' }),
  }))

  await page.goto('/login')
  await page.getByPlaceholder('请输入账号').fill(admin.email)
  await page.getByPlaceholder('请输入密码').fill('secret123')
  await page.locator('button[type="submit"]').click()

  await expect(page.getByRole('alert')).toContainText('Sub2API 登录服务暂不可用')
})
