// App 端 E2E 测试脚本（员工视角）
// 用法：node .v0-e2e/app-test.mjs [路径] [截图输出路径]
// 例：  node .v0-e2e/app-test.mjs /app/users-quota /tmp/agent-browser/quota.png
// 必须在 frontend 目录下运行；无头模式。
import { chromium } from '@playwright/test'

const BASE = process.env.BASE_URL || 'http://localhost:5173'
const ACCOUNT = process.env.TEST_ACCOUNT || 'test123'
const PASSWORD = process.env.TEST_PASSWORD || 'QWEasd123'
const gotoPath = process.argv[2] || ''
const shot = process.argv[3] || '/tmp/agent-browser/app-e2e.png'

const browser = await chromium.launch({ headless: true })
const context = await browser.newContext({
  viewport: { width: 390, height: 844 },
  deviceScaleFactor: 2,
  isMobile: true,
  hasTouch: true,
})
const page = await context.newPage()
page.on('console', (m) => {
  const t = m.text()
  if (m.type() === 'error' || t.includes('[v0]')) console.log('  [browser]', t)
})

await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })

// 若在登录页则登录（员工账号）
if (await page.getByPlaceholder('请输入账号').count()) {
  await page.getByPlaceholder('请输入账号').fill(ACCOUNT)
  await page.getByPlaceholder('请输入密码').fill(PASSWORD)
  await page.getByRole('button', { name: '登录' }).click()
  await page.waitForURL(/\/app/, { timeout: 20000 }).catch(() => {})
  await page.waitForTimeout(1500)
  console.log('登录后 URL:', page.url())
}

if (gotoPath) {
  await page.goto(BASE + gotoPath, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  console.log('目标页 URL:', page.url())
}

await page.waitForTimeout(500)
await page.screenshot({ path: shot, fullPage: true })
console.log('截图已保存:', shot)
await browser.close()
