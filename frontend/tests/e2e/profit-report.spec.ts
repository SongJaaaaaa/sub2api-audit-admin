import { expect, test } from '@playwright/test'

const admin = {
  id: 1,
  sub2api_user_id: 1,
  name: '爱吃胡萝卜',
  username: 'carrot',
  email: 'carrot@example.com',
  status: 'active',
}

const zeroSummary = {
  income_total: '0.00',
  expense_total: '0.00',
  profit_total: '0.00',
  income_count: 0,
  expense_count: 0,
}

test('settlement keeps administrator columns and historical profit visible', async ({ page }) => {
  let settled = false

  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const path = new URL(req.url()).pathname

    if (path.endsWith('/auth/login')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ identity_type: 'admin', token: 'profit-e2e-token', admin }),
      })
    }
    if (path.endsWith('/auth/me')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ admin }) })
    }
    if (path.endsWith('/profit/summary')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          owners: [
            { id: 1, name: '爱吃胡萝卜', email: 'carrot@example.com', income_total: '123089.00', income_count: 8, expense_total: '34351.00', expense_count: 1 },
            { id: 2, name: '牛宝', email: 'niu@example.com', income_total: '30863.00', income_count: 8, expense_total: '18430.90', expense_count: 1 },
            { id: 3, name: '小铺', email: 'shop@example.com', income_total: '31200.00', income_count: 8, expense_total: '0.00', expense_count: 0 },
          ],
          days: [{
            biz_date: '2026-07-17',
            income_by_owner: { 1: '123089.00', 2: '30863.00', 3: '31200.00' },
            expense_by_owner: { 1: '34351.00', 2: '18430.90' },
            income_total: '185152.00',
            expense_total: '52781.90',
            profit_total: '132370.10',
            income_count: 24,
            expense_count: 2,
          }],
          summary: { income_total: '185152.00', expense_total: '52781.90', profit_total: '132370.10', income_count: 24, expense_count: 2 },
          pending_summary: settled
            ? zeroSummary
            : { income_total: '100.00', expense_total: '20.00', profit_total: '80.00', income_count: 1, expense_count: 1 },
        }),
      })
    }
    if (path.endsWith('/profit/details')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          income: [{ id: 1, entry_no: 'CASH001', sub2api_user_id: 10, sub2api_user_email: 'user@example.com', amount: '123089.00', owner_admin_id: 1, owner_name: '爱吃胡萝卜', remark: null, biz_date: '2026-07-17', created_at: '2026-07-17 10:00:00' }],
          expenses: [{ id: 1, expense_no: 'EXP001', category: '服务器', amount: '34351.00', owner_admin_id: 1, owner_name: '爱吃胡萝卜', remark: null, biz_date: '2026-07-17', created_at: '2026-07-17 11:00:00' }],
        }),
      })
    }
    if (path.endsWith('/profit/settlements') && req.method() === 'POST') {
      settled = true
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          message: '分账已确认',
          settlement: { id: 1, batch_no: 'PST001', start_date: '2026-07-11', end_date: '2026-07-17', income_total: '100.00', expense_total: '20.00', profit_total: '80.00', income_count: 1, expense_count: 1, status: 'confirmed', created_by: 1, operator_name: '爱吃胡萝卜', reversed_by: null, reverser_name: null, reversed_at: null, created_at: '2026-07-17 12:00:00' },
        }),
      })
    }
    if (path.endsWith('/profit/settlements')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [], total: 0, page: 1, page_size: 20 }) })
    }

    return route.fulfill({ status: 200, contentType: 'application/json', body: '{}' })
  })

  await page.goto('/login')
  await page.getByPlaceholder('Sub2API 用户邮箱').fill(admin.email)
  await page.getByPlaceholder('密码').fill('secret123')
  await page.locator('button[type="submit"]').click()
  await page.goto('/profit')

  await expect(page.getByRole('tab', { name: '利润明细' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '爱吃胡萝卜入账' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '牛宝入账' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '小铺入账' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '爱吃胡萝卜支出' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: '牛宝支出' })).toBeVisible()

  const confirm = page.getByRole('button', { name: '确认分账（2 笔）' })
  await confirm.click()
  const dialog = page.getByRole('dialog', { name: '确认分账' })
  await expect(dialog).toContainText('100.00（1 笔）')
  await expect(dialog).toContainText('20.00（1 笔）')
  await expect(dialog).not.toContainText('185152.00')
  await dialog.getByRole('button', { name: '确认分账' }).click()

  const settledButton = page.getByRole('button', { name: '确认分账（0 笔）' })
  await expect(settledButton).toBeDisabled()
  await expect(page.getByRole('columnheader', { name: '爱吃胡萝卜入账' })).toBeVisible()
  await expect(page.locator('.profitSummary')).toContainText('185152.00')

  await page.getByText('2026-07-17', { exact: true }).first().click()
  await expect(page.getByText('2026-07-17 收支明细', { exact: true })).toBeVisible()
  await expect(page.getByRole('row', { name: /CASH001 爱吃胡萝卜/ })).toBeVisible()
  await expect(page.getByRole('row', { name: /EXP001 爱吃胡萝卜/ })).toBeVisible()
})
