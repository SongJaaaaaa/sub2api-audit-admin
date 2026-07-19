import { expect, test } from '@playwright/test'

const admin = {
  id: 1,
  sub2api_user_id: 1,
  name: '管理员',
  username: 'admin',
  email: 'admin@example.com',
  status: 'active',
}

test('finance pages use Chinese dates, server sorting, signed amounts and local history', async ({ context, page }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  const sortedUrls: string[] = []
  const statUrls: string[] = []

  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const url = new URL(req.url())
    const path = url.pathname
    let body: unknown = {}

    if (path.endsWith('/auth/login')) {
      body = { identity_type: 'admin', token: 'finance-e2e-token', admin }
    } else if (path.endsWith('/auth/me')) {
      body = { admin }
    } else if (path.endsWith('/auth/admin-options')) {
      body = { items: [admin] }
    } else if (path.endsWith('/sub2api/users')) {
      if (url.searchParams.get('sort_by')) sortedUrls.push(url.toString())
      body = {
        items: [{ id: 1001, email: 'alpha@example.com', username: 'alpha', role: 'user', balance: '12.50', total_recharged: '100.00', status: 'active', last_used_at: '2026-07-18 10:00:00', created_at: '2026-07-01 10:00:00', updated_at: '2026-07-18 10:00:00' }],
        total: 1,
        page: 1,
        page_size: 20,
        summary: { user_count: 1, active_count: 1, disabled_count: 0, balance_total: '12.50', average_balance: '12.50', negative_balance_count: 0, zero_balance_count: 0 },
      }
    } else if (path.endsWith('/ledger-adjustments/user-stats')) {
      statUrls.push(url.toString())
      body = {
        items: [{ period_start: '2026-07-13', period_end: '2026-07-19', sub2api_user_id: 1001, sub2api_user_email: 'alpha@example.com', record_count: 2, cash_total: '80.00', gift_total: '20.00', increment_total: '100.00', decrement_total: '80.00', net_total: '20.00' }],
        total: 1,
        page: 1,
        page_size: 20,
        granularity: url.searchParams.get('granularity') || 'day',
      }
    } else if (path.endsWith('/ledger-adjustments')) {
      body = {
        items: [
          { id: 1, ledger_no: 'ADJ-IN', idempotency_key: 'in', sub2api_user_id: 1001, sub2api_source_id: 1, sub2api_user_email: 'alpha@example.com', operation: 'increment', amount: '100.00', cash_amount: '80.00', gift_quota_amount: '20.00', before_balance: '0.00', after_balance: '100.00', status: 'succeeded', adjust_reason: '充值', admin_notes: null, sub2api_notes: null, exception_reason: null, called_at: null, confirmed_at: '2026-07-18 10:00:00', created_at: '2026-07-18 10:00:00', created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com' },
          { id: 2, ledger_no: 'ADJ-OUT', idempotency_key: 'out', sub2api_user_id: 1001, sub2api_source_id: 2, sub2api_user_email: 'alpha@example.com', operation: 'decrement', amount: '80.00', cash_amount: '0.00', gift_quota_amount: '0.00', before_balance: '100.00', after_balance: '20.00', status: 'succeeded', adjust_reason: '人工扣减', admin_notes: null, sub2api_notes: null, exception_reason: null, called_at: null, confirmed_at: '2026-07-18 11:00:00', created_at: '2026-07-18 11:00:00', created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com' },
        ],
        total: 2,
        page: 1,
        page_size: 20,
        summary: { record_count: 2, user_count: 1, increment_total: '100.00', decrement_total: '80.00', net_total: '20.00', cash_total: '80.00', gift_total: '20.00' },
      }
    } else if (path.endsWith('/finance/expenses')) {
      body = {
        items: [{ id: 1, expense_no: 'EXP001', category: '服务器', amount: '30.00', paid_at: '2026-07-18', remark: '月费', content_html: null, created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com', created_at: '2026-07-18 09:00:00' }],
        total: 1,
        page: 1,
        page_size: 20,
        categories: [{ category: '服务器', record_count: 1, amount_total: '30.00' }],
        summary: { record_count: 1, category_count: 1, amount_total: '30.00', max_amount: '30.00', daily_average: null },
      }
    } else if (path.endsWith('/finance/history')) {
      body = {
        items: [
          { type: 'expense', source_id: 1, bill_no: 'EXP001', biz_date: '2026-07-18', sub2api_user_id: null, sub2api_user_email: null, category: '服务器', amount: '30.00', created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com', remark: '月费', created_at: '2026-07-18 09:00:00' },
          { type: 'gift', source_id: 1, bill_no: 'GIFT001', biz_date: '2026-07-17', sub2api_user_id: 1001, sub2api_user_email: 'alpha@example.com', category: null, amount: '20.00', created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com', remark: '赠送', created_at: '2026-07-17 10:00:00' },
          { type: 'income', source_id: 1, bill_no: 'CASH001', biz_date: '2026-07-16', sub2api_user_id: 1001, sub2api_user_email: 'alpha@example.com', category: null, amount: '80.00', created_by: 1, operator_name: '管理员', operator_email: 'admin@example.com', remark: '充值', created_at: '2026-07-16 10:00:00' },
        ],
        total: 3,
        page: 1,
        page_size: 20,
        summary: { record_count: 3, income_count: 1, expense_count: 1, gift_count: 1, income_total: '80.00', expense_total: '30.00', gift_total: '20.00' },
      }
    }

    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })
  })

  await page.goto('/login')
  await page.getByPlaceholder('Sub2API 用户邮箱').fill(admin.email)
  await page.getByPlaceholder('密码').fill('secret123')
  await page.locator('button[type="submit"]').click()

  await page.goto('/sub2api/users')
  await page.getByRole('button', { name: /alpha@example.com/ }).click()
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe('alpha@example.com')
  await page.getByRole('columnheader', { name: /余额/ }).click()
  await expect.poll(() => sortedUrls.some(url => url.includes('sort_by=balance') && url.includes('sort_order=asc'))).toBeTruthy()

  await page.goto('/ledger')
  await expect(page.locator('.soyBreadcrumb')).toHaveText('收入')
  await expect(page.locator('.summaryGrid section').nth(0)).toContainText('+80.00')
  await expect(page.locator('.summaryGrid section').nth(1)).toContainText('+20.00')
  await expect(page.locator('.summaryGrid section').nth(2)).toContainText('+20.00')
  await expect(page.locator('.summaryGrid section').nth(3)).toContainText('-80.00')
  await page.getByText('周', { exact: true }).click()
  await expect.poll(() => statUrls.some(url => url.includes('granularity=week'))).toBeTruthy()
  await page.locator('.dateFilter').click()
  await expect(page.getByRole('button', { name: /年/ }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: /月/ }).first()).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '一' }).first()).toBeVisible()
  await page.keyboard.press('Escape')

  await page.goto('/operation-expense')
  await expect(page.locator('.soyBreadcrumb')).toHaveText('支出')
  await expect(page.locator('.summaryGrid')).toContainText('-30.00')
  await expect(page.getByRole('row', { name: /EXP001/ })).toContainText('-30.00')

  await page.goto('/balance-events')
  await expect(page.locator('.summaryGrid section').nth(0)).toContainText('+80.00')
  await expect(page.locator('.summaryGrid section').nth(1)).toContainText('-30.00')
  await expect(page.locator('.summaryGrid section').nth(2)).toContainText('+20.00')
  await expect(page.getByRole('row', { name: /EXP001/ })).toContainText('-30.00')
  await expect(page.getByRole('row', { name: /GIFT001/ })).toContainText('+20.00')
  await expect(page.getByRole('row', { name: /CASH001/ })).toContainText('+80.00')
})
