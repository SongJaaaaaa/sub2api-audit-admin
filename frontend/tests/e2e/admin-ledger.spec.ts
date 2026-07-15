import { expect, test } from '@playwright/test'

test('admin can login and open core business pages', async ({ page }) => {
  await page.goto('/login')
  await page.getByPlaceholder('用户名或管理员邮箱').fill('admin')
  await page.getByPlaceholder('密码').fill('1')
  await page.locator('button[type="submit"]').click()

  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('button', { name: '刷新' })).toBeVisible()

  const pages = [
    { path: '/ledger', title: '入账记录' },
    { path: '/gift-quota', title: '赠送额度' },
    { path: '/operation-expense', title: '经营账' },
    { path: '/profit', title: '利润统计' },
    { path: '/reconcile', title: '调额对账中心' },
    { path: '/audit-log', title: '操作审计' },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page).toHaveURL(new RegExp(`${item.path}$`))
    await expect(page.locator('.soyBreadcrumb')).toHaveText(item.title)
  }
})
