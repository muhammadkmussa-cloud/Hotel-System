import { test, expect } from '@playwright/test';

test('pairing screen renders without leaking private data', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const response = await page.goto('/device/pair');
  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { level: 1, name: 'Pair this device' })).toBeVisible();
  await expect(page.getByLabel('Pairing code')).toBeVisible();
  await expect(page.getByText(/private data/i)).toBeVisible();
  expect(errors).toEqual([]);
});

test('pairing POST rejects a missing CSRF token', async ({ page }) => {
  const response = await page.request.post('/device/pair', { form: { code: 'whatever-code' } });
  expect(response.status()).toBe(419);
});
