import { defineConfig, devices } from '@playwright/test'

const frontendPort = Number(process.env.E2E_FRONTEND_PORT || 5175)
const appFrontendPort = Number(process.env.E2E_APP_FRONTEND_PORT || 5176)
const backendPort = Number(process.env.E2E_BACKEND_PORT || 8010)
const nonAppSpec = /app-mode\.spec\.ts/
const appOnly = process.env.E2E_APP_ONLY === 'true'

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
    ...(appOnly ? [{
      command: `node scripts/e2e-server.mjs frontend ${appFrontendPort}`,
      url: `http://127.0.0.1:${appFrontendPort}`,
      env: { ...process.env, VITE_APP_MODE: 'app', E2E_BACKEND_PORT: String(backendPort) } as Record<string, string>,
      reuseExistingServer: false,
      timeout: 120_000,
    }] : [{
      command: `node scripts/e2e-server.mjs frontend ${frontendPort}`,
      url: `http://127.0.0.1:${frontendPort}`,
      env: { ...process.env, VITE_APP_MODE: 'web', E2E_BACKEND_PORT: String(backendPort) } as Record<string, string>,
      reuseExistingServer: false,
      timeout: 120_000,
    }]),
  ],
  projects: appOnly ? [
    { name: 'app', testMatch: /app-mode\.spec\.ts/, use: { ...devices['iPhone 12'], browserName: 'chromium', channel: 'chrome', baseURL: `http://127.0.0.1:${appFrontendPort}` } },
  ] : [
    { name: 'desktop', testIgnore: nonAppSpec, use: { ...devices['Desktop Chrome'], browserName: 'chromium', channel: 'chrome' } },
    { name: 'h5', testIgnore: nonAppSpec, use: { ...devices['iPhone 12'], browserName: 'chromium', channel: 'chrome' } },
    { name: 'pixel7', testIgnore: nonAppSpec, use: { ...devices['Pixel 7'], browserName: 'chromium', channel: 'chrome' } },
  ],
})
