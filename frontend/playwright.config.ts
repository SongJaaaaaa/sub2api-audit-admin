import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: { timeout: 8_000 },
  use: {
    baseURL: 'http://127.0.0.1:5174',
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'php artisan migrate --seed && php artisan serve --host=127.0.0.1 --port=8010',
      cwd: '../backend',
      url: 'http://127.0.0.1:8010/api/v1/health',
      reuseExistingServer: true,
      timeout: 120_000,
    },
    {
      command: 'VITE_API_PROXY_TARGET=http://127.0.0.1:8010 pnpm dev --host 127.0.0.1 --port 5174',
      url: 'http://127.0.0.1:5174',
      reuseExistingServer: true,
      timeout: 120_000,
    },
  ],
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], browserName: 'chromium', channel: 'chrome' } },
    { name: 'h5', use: { ...devices['iPhone 12'], browserName: 'chromium', channel: 'chrome' } },
  ],
})
