import { expect, test, type BrowserContext, type Page, type Route } from '@playwright/test'

const longEmail = 'affiliate.user.with.a.very.long.business.account.name.for.mobile.layout.2026@example-company-domain.test'
const longInviteUrl = `https://portal.example.test/register?invite=AFF-2026-LONG-CODE&source=${'campaign-'.repeat(18)}mobile`

const affiliateUser = {
  id: 9001,
  email: longEmail,
  username: 'rebate_owner_9001',
  status: 'active',
  invite_code: 'AFF-2026-LONG-CODE',
  created_at: '2026-07-15 09:00:00',
}

const adminInfo = {
  id: 1,
  name: '管理员',
  username: 'admin',
  email: 'admin@example.com',
  status: 'active',
}

const rebateRecord = {
  id: 301,
  event_id: 201,
  payer_user_id: 9002,
  payer_email: longEmail,
  receiver_user_id: 9001,
  receiver_email: longEmail,
  type: 'milestone',
  level: 1,
  source_amount: '100.00',
  rebate_amount: '15.00',
  status: 'confirmed',
  created_at: '2026-07-15 10:10:00',
}

const teamMember = {
  user_id: 9002,
  email: longEmail,
  username: 'direct_member_9002',
  total_recharge_amount: '300.00',
  total_rebate_amount: '45.00',
  milestone_times: 2,
  joined_at: '2026-07-15 09:30:00',
}

async function loginAdmin(page: Page) {
  await page.route('**/api/v1/dashboard**', (route) => route.abort('failed'))
  await page.goto('/login')
  await page.getByPlaceholder('用户名或管理员邮箱').fill('admin')
  await page.getByPlaceholder('密码').fill('1')
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL(/\/$/)
}

async function mockAdminRebateApi(page: Page) {
  await page.route('**/api/v1/rebate/admin/dashboard', (route) => json(route, {
    total_users: 0,
    direct_referral_count: 0,
    total_rebate_amount: '0.00',
    available_rebate_amount: '0.00',
    frozen_rebate_amount: '0.00',
    withdrawn_amount: '0.00',
    today_rebate_amount: '0.00',
    month_rebate_amount: '0.00',
    pending_withdrawal_count: 0,
    pending_withdrawal_amount: '0.00',
    recent_rebates: [],
    recent_withdrawals: [],
  }))
  await page.route('**/api/v1/rebate/admin/withdrawals**', (route) => json(route, {
    items: [],
    total: 0,
    page: 1,
    page_size: 20,
  }))
  await page.route('**/api/v1/rebate/admin/config', (route) => json(route, {
    milestone_amount: '100.00',
    milestone_reward_amount: '15.00',
    milestone_max_times: 2,
    stage_amount: '100.00',
    stage_reward_amount: '15.00',
    withdraw_min_amount: '2.00',
    withdraw_daily_limit: 10,
    withdraw_daily_amount_limit: '0.00',
    withdraw_to_api_quota_rate: '1.0000',
    native_recharge_enabled: true,
    redeem_enabled: true,
    admin_adjust_enabled: false,
    rebate_cutover_at: null,
    updated_at: null,
  }))
}

async function expectPageReady(page: Page, title: string) {
  await expect(page.getByRole('heading', { name: title, level: 1 })).toBeVisible()
  await expect(page.locator('.ant-skeleton')).toHaveCount(0)
  await expect(page.locator('.ant-alert-error')).toHaveCount(0)
}

async function expectNoViewportOverflow(page: Page) {
  const widths = await page.evaluate(() => ({
    viewport: window.innerWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }))

  expect(widths.document, JSON.stringify(widths)).toBeLessThanOrEqual(widths.viewport + 1)
  expect(widths.body, JSON.stringify(widths)).toBeLessThanOrEqual(widths.viewport + 1)
}

async function json(route: Route, data: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(data),
  })
}

async function savedTokens(context: BrowserContext) {
  const state = await context.storageState()
  const values = new Map(state.origins.flatMap((origin) => origin.localStorage.map((item) => [item.name, item.value])))
  return {
    admin: values.get('adminToken') || null,
    affiliate: values.get('affiliateToken') || null,
  }
}

async function mockAffiliateApi(page: Page) {
  await page.addInitScript(({ token, user }) => {
    localStorage.setItem('affiliateToken', token)
    localStorage.setItem('affiliateInfo', JSON.stringify(user))
  }, { token: 'e2e-affiliate-token', user: affiliateUser })

  await page.route('**/api/v1/affiliate/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    expect(request.headers().authorization).toBe('Bearer e2e-affiliate-token')

    if (path.endsWith('/affiliate/auth/me')) {
      return json(route, { user: affiliateUser })
    }
    if (path.endsWith('/affiliate/dashboard')) {
      return json(route, {
        user: affiliateUser,
        balance: {
          available_amount: '1245.50',
          frozen_amount: '25.00',
          withdrawn_amount: '180.00',
          total_rebate_amount: '1450.50',
        },
        direct_count: 1,
        converted_count: 1,
        total_direct_recharge_amount: '300.00',
        pending_withdrawal_amount: '25.00',
        recent_rebates: [rebateRecord],
      })
    }
    if (path.endsWith('/affiliate/team')) {
      return json(route, { items: [], total: 0, page: 1, page_size: 20 })
    }
    if (path.endsWith('/affiliate/promotion')) {
      return json(route, {
        invite_code: affiliateUser.invite_code,
        invite_url: longInviteUrl,
        direct_count: 1,
        converted_count: 1,
        conversion_rate: '100.00',
        total_direct_recharge_amount: '300.00',
        items: [teamMember],
      })
    }
    if (path.endsWith('/affiliate/rebate-records')) {
      return json(route, { items: [rebateRecord], total: 1, page: 1, page_size: 20 })
    }
    if (path.endsWith('/affiliate/withdrawals') && request.method() === 'GET') {
      return json(route, {
        items: [],
        total: 0,
        page: 1,
        page_size: 20,
        balance: {
          available_amount: '1245.50',
          frozen_amount: '25.00',
          withdrawn_amount: '180.00',
          total_rebate_amount: '1450.50',
        },
        config: {
          min_amount: '2.00',
          daily_limit: 10,
          daily_amount_limit: '0.00',
          to_api_quota_rate: '1.0000',
        },
        today_count: 0,
        today_amount: '0.00',
      })
    }

    return json(route, { message: `未配置的 Affiliate mock: ${request.method()} ${path}` }, 501)
  })
}

test('admin rebate pages render without viewport overflow', async ({ page }) => {
  await mockAdminRebateApi(page)
  await loginAdmin(page)

  const pages = [
    { path: '/rebate/dashboard', title: '推广返利看板', breadcrumb: '数据看板' },
    { path: '/rebate/relationships', title: '推荐关系', breadcrumb: '推荐关系', state: '请选择账号后查看一级推荐关系' },
    { path: '/rebate/withdrawals', title: '提现审核', breadcrumb: '提现审核', state: '暂无数据' },
    { path: '/rebate/config', title: '返利配置', breadcrumb: '返利配置', state: '初始累充门槛' },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page).toHaveURL(new RegExp(`${item.path}$`))
    await expect(page.locator('.soyBreadcrumb')).toHaveText(item.breadcrumb)
    await expectPageReady(page, item.title)
    if (item.state) await expect(page.getByText(item.state, { exact: true }).first()).toBeVisible()
    await expectNoViewportOverflow(page)
  }
})

test('affiliate rebate pages handle long content and empty states without viewport overflow', async ({ page }) => {
  await mockAffiliateApi(page)

  const pages = [
    { path: '/affiliate/dashboard', title: '仪表盘', text: longEmail },
    { path: '/affiliate/team', title: '我的团队', text: '暂无直接下级' },
    { path: '/affiliate/promotion', title: '推广中心', text: longEmail },
    { path: '/affiliate/rebates', title: '返利明细', text: longEmail },
    { path: '/affiliate/withdrawals', title: '提现管理', text: '暂无提现记录' },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page).toHaveURL(new RegExp(`${item.path}$`))
    await expectPageReady(page, item.title)
    await expect(page.getByText(item.text, { exact: true }).first()).toBeVisible()
    if (item.path === '/affiliate/promotion') {
      await expect(page.locator('input[readonly]').nth(1)).toHaveValue(longInviteUrl)
    }
    await expectNoViewportOverflow(page)
  }
})

test('h5 affiliate navigation opens and closes through the drawer', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'h5')
  await mockAffiliateApi(page)
  await page.goto('/affiliate/dashboard')

  await page.getByRole('button', { name: '打开导航' }).click()
  const drawer = page.locator('.ant-drawer-content')
  await expect(drawer).toBeVisible()
  await drawer.getByRole('button', { name: '我的团队' }).click()

  await expect(page).toHaveURL(/\/affiliate\/team$/)
  await expect(drawer).not.toBeVisible()
})

test('withdrawal review refreshes server state after a lost response', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')
  let status = 'pending'
  let reads = 0

  await page.addInitScript(({ token, admin }) => {
    localStorage.setItem('adminToken', token)
    localStorage.setItem('adminInfo', JSON.stringify(admin))
  }, { token: 'e2e-admin-token', admin: adminInfo })
  await page.route('**/api/v1/auth/me', (route) => json(route, { admin: adminInfo }))
  await page.route('**/api/v1/rebate/admin/withdrawals**', async (route) => {
    const request = route.request()
    expect(request.headers().authorization).toBe('Bearer e2e-admin-token')
    if (request.method() === 'POST') {
      status = 'processing'
      await new Promise((resolve) => setTimeout(resolve, 300))
      return route.abort('timedout')
    }

    reads += 1
    return json(route, {
      items: [{
        id: 701,
        request_no: 'RBW-LOST-RESPONSE',
        user_id: 9001,
        user_email: longEmail,
        amount: '20.00',
        quota_amount: '20.00',
        status,
        reject_reason: null,
        error_message: null,
        requested_at: '2026-07-15 10:00:00',
      }],
      total: 1,
      page: 1,
      page_size: 20,
    })
  })

  await page.goto('/rebate/withdrawals')
  const table = page.locator('.ant-table')
  await table.getByRole('button', { name: '通过' }).click()
  await page.getByRole('button', { name: '通过并处理' }).click()

  await expect.poll(() => reads).toBeGreaterThanOrEqual(2)
  await expect(table.getByText('处理中', { exact: true })).toBeVisible()
  await expect(table.getByRole('button', { name: '通过' })).toHaveCount(0)
})

test('admin and affiliate 401 responses clear only their own login state', async ({ context, page }) => {
  await page.goto('/login')
  await page.evaluate(({ admin, affiliate }) => {
    localStorage.setItem('adminToken', 'expired-admin-token')
    localStorage.setItem('adminInfo', JSON.stringify(admin))
    localStorage.setItem('affiliateToken', 'valid-affiliate-token')
    localStorage.setItem('affiliateInfo', JSON.stringify(affiliate))
  }, { admin: adminInfo, affiliate: affiliateUser })
  await page.route('**/api/v1/auth/me', (route) => json(route, { message: 'Unauthenticated.' }, 401))
  await page.route('**/api/v1/rebate/admin/dashboard', (route) => route.abort('failed'))

  await page.goto('/rebate/dashboard')
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await expect.poll(() => savedTokens(context)).toEqual({ admin: null, affiliate: 'valid-affiliate-token' })

  await page.evaluate(({ admin }) => {
    localStorage.setItem('adminToken', 'valid-admin-token')
    localStorage.setItem('adminInfo', JSON.stringify(admin))
    localStorage.setItem('affiliateToken', 'expired-affiliate-token')
  }, { admin: adminInfo })
  await page.route('**/api/v1/affiliate/auth/me', (route) => json(route, { message: 'Unauthenticated.' }, 401))
  await page.route('**/api/v1/affiliate/dashboard', (route) => route.abort('failed'))

  await page.goto('/affiliate/dashboard')
  await expect(page).toHaveURL(/\/affiliate\/login(?:\?|$)/)
  await expect.poll(() => savedTokens(context)).toEqual({ admin: 'valid-admin-token', affiliate: null })
})
