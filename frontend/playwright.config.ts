import { defineConfig, devices } from '@playwright/test'

const frontendPort = Number(process.env.E2E_FRONTEND_PORT || 5175)
const backendPort = Number(process.env.E2E_BACKEND_PORT || 8010)

export default defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 30_000,
  expect: { timeout: 8_000 },
  use: {
    baseURL: `http://127.0.0.1:${frontendPort}`,
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: `node scripts/e2e-server.mjs backend ${backendPort}`,
      url: `http://127.0.0.1:${backendPort}/api/v1/health`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: `node scripts/e2e-server.mjs frontend ${frontendPort}`,
      url: `http://127.0.0.1:${frontendPort}`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
  ],
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], browserName: 'chromium', channel: 'chrome' } },
    { name: 'h5', use: { ...devices['iPhone 12'], browserName: 'chromium', channel: 'chrome' } },
  ],
})
