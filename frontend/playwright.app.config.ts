import { defineConfig, devices } from '@playwright/test'

const port = Number(process.env.E2E_APP_FRONTEND_PORT || 5251)

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: /app-mode\.spec\.ts/,
  workers: 1,
  timeout: 30_000,
  expect: { timeout: 8_000 },
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'app', use: { ...devices['iPhone 12'], browserName: 'chromium', channel: 'chrome' } },
  ],
})
