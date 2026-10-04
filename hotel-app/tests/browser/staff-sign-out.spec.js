import { test, expect } from '@playwright/test';

test('home page shows no sign-out control for an anonymous visitor', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('button', { name: 'Sign out' })).toHaveCount(0);
});

test('sign-out POST rejects a missing CSRF token', async ({ page }) => {
  const response = await page.request.post('/staff/sign-out', { form: {} });
  expect(response.status()).toBe(419);
});
