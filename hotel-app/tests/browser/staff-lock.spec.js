import { test, expect } from '@playwright/test';

test('lock screen redirects anonymous visitors to sign in', async ({ page }) => {
  await page.goto('/staff/lock');
  await expect(page).toHaveURL(/\/staff\/sign-in$/);
});

test('unlock POST rejects a missing CSRF token', async ({ page }) => {
  const response = await page.request.post('/staff/unlock', { form: { password: 'whatever-password' } });
  expect(response.status()).toBe(419);
});
