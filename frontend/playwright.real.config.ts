// Vendor
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e-real',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  outputDir: 'test-results-real',
  reporter: process.env.CI
    ? [['line'], ['html', { outputFolder: 'playwright-report-real', open: 'never' }]]
    : 'list',
  use: {
    baseURL: process.env.CARDNEST_E2E_BASE_URL ?? 'http://127.0.0.1:15173',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  projects: [{ name: 'real-chromium', use: { ...devices['Desktop Chrome'] } }],
})
