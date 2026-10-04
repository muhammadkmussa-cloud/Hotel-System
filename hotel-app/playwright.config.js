import { defineConfig } from '@playwright/test';
import { origin } from './tests/browser/settings.mjs';

export default defineConfig({
  testDir: './tests/browser',
  testMatch: '**/*.spec.js',
  workers: 1,
  retries: 0,
  forbidOnly: true,
  timeout: 30000,
  reporter: 'list',
  use: {
    baseURL: origin,
    browserName: 'chromium',
    channel: 'chromium',
    trace: 'retain-on-failure',
  },
  webServer: {
    command: 'node tests/browser/server.mjs',
    url: origin,
    reuseExistingServer: false,
    timeout: 20000,
    gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
  },
});
