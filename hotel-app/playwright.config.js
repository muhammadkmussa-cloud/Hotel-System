import { defineConfig } from '@playwright/test';
import { origin, authedOrigin } from './tests/browser/settings.mjs';

export default defineConfig({
  testDir: './tests/browser',
  testMatch: '**/*.spec.js',
  workers: 1,
  retries: 0,
  forbidOnly: true,
  timeout: 30000,
  reporter: 'list',
  use: {
    browserName: 'chromium',
    channel: 'chromium',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'anonymous', testIgnore: '**/*.authed.spec.js', use: { baseURL: origin } },
    { name: 'authenticated', testMatch: '**/*.authed.spec.js', use: { baseURL: authedOrigin } },
  ],
  webServer: [
    {
      command: 'node tests/browser/server.mjs',
      url: origin,
      reuseExistingServer: false,
      timeout: 20000,
      gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
    },
    {
      command: 'node tests/browser/server-authed.mjs',
      url: authedOrigin,
      reuseExistingServer: false,
      timeout: 30000,
      gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
    },
  ],
});
