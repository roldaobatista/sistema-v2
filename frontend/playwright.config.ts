import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { existsSync } from 'fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const parsedE2EWorkers = Number(process.env.E2E_WORKERS);
const e2eWorkers = Number.isInteger(parsedE2EWorkers) && parsedE2EWorkers > 0
  ? parsedE2EWorkers
  : 1;
const apiPort = process.env.E2E_API_PORT || '8010';
const frontendPort = process.env.E2E_FRONTEND_PORT || '3010';
const apiBase = process.env.E2E_API_BASE || `http://127.0.0.1:${apiPort}/api/v1`;
const apiHealthUrl = process.env.E2E_API_HEALTH_URL || `http://127.0.0.1:${apiPort}/up`;
const frontendUrl = process.env.E2E_FRONTEND_URL || process.env.PLAYWRIGHT_BASE_URL || `http://127.0.0.1:${frontendPort}`;
const reuseExistingServer = process.env.E2E_REUSE_EXISTING_SERVER === 'true' || process.env.E2E_REUSE_SERVER === 'true';
const localPhp84 = process.env.USERPROFILE
  ? resolve(process.env.USERPROFILE, 'AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe')
  : '';
const phpBinary = process.env.E2E_PHP_BIN || (localPhp84 && existsSync(localPhp84) ? localPhp84 : 'php');

process.env.E2E_API_BASE = apiBase;
process.env.E2E_FRONTEND_URL = frontendUrl;
process.env.PLAYWRIGHT_BASE_URL = frontendUrl;
process.env.VITE_PROXY_TARGET = process.env.VITE_PROXY_TARGET || new URL(apiBase).origin;
process.env.VITE_API_URL = '';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  globalSetup: resolve(__dirname, 'playwright.global-setup.ts'),
  testDir: './e2e',
  /* Maximum time one test can run for. */
  timeout: 30 * 1000,
  expect: {
    /**
     * Maximum time expect() should wait for the condition to be met.
     */
    timeout: 5000
  },
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* O backend revoga tokens antigos no login; use E2E_WORKERS>1 apenas com credenciais isoladas por worker. */
  workers: e2eWorkers,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: frontendUrl,

    /* In production/CI we capture trace in all retries. Local just for fails */
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'setup',
      testDir: './e2e',
      testMatch: /global\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Use the auth state for all tests in this project, created by setup
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },
    // We can add Firefox/WebKit later if needed, starting with Chromium for perf
  ],

  /* Run local dev servers before starting the tests */
  webServer: [
    {
      command: `"${phpBinary}" artisan serve --host=127.0.0.1 --port=${apiPort}`,
      cwd: resolve(__dirname, '../backend'),
      url: apiHealthUrl,
      reuseExistingServer,
      timeout: 120 * 1000,
    },
    {
      command: `npm run dev -- --host 127.0.0.1 --port ${frontendPort}`,
      env: {
        ...process.env,
        VITE_API_URL: '',
        VITE_PROXY_TARGET: new URL(apiBase).origin,
        VITE_INMETRO_AUTO_SYNC: 'false',
      },
      url: frontendUrl,
      reuseExistingServer,
      timeout: 120 * 1000,
    },
  ],
});
