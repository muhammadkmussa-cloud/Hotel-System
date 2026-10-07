import { test, expect } from '@playwright/test';
import { signIn } from './helpers.mjs';

test('an owner can view every settings section with redacted integrations', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/settings');
  await expect(page.getByRole('heading', { level: 1, name: 'Hotel settings' })).toBeVisible();
  for (const name of ['Hotel', 'Receipt identity', 'Tables', 'Kitchen and bar stations', 'Receipt printer destinations', 'Integrations']) {
    await expect(page.getByRole('heading', { level: 2, name, exact: true })).toBeVisible();
  }
  // Integration status is boolean-only; no secret values are rendered.
  await expect(page.locator('body')).not.toContainText(/secret|password|api[_-]?key/i);
});
