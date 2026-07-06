import { expect, test } from '@playwright/test'

test('admin can login and open core business pages', async ({ page }) => {
  await page.goto('/login')
  await page.getByPlaceholder('管理员邮箱').fill('admin@example.com')
  await page.getByPlaceholder('密码').fill('admin123')
  await page.locator('button[type="submit"]').click()

  await expect(page.getByRole('heading', { name: '首页看板' })).toBeVisible()

  const pages = [
    { path: '/ledger', title: '额度调整记录' },
    { path: '/gift-quota', title: '现金账与赠送额度' },
    { path: '/operation-expense', title: '经营账' },
    { path: '/reconcile', title: '对账中心' },
    { path: '/audit-log', title: '操作审计' },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page.getByRole('heading', { name: item.title })).toBeVisible()
  }
})
