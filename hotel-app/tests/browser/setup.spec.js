import { test, expect } from '@playwright/test';

test('setup screen renders labelled fields with server-side value constraints', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const response = await page.goto('/setup');
  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { level: 1, name: 'First installation setup' })).toBeVisible();
  await expect(page.getByLabel('Hotel trading name')).toHaveAttribute('required', '');
  await expect(page.getByLabel('Timezone')).toBeVisible();
  await expect(page.getByLabel('Currency')).toHaveValue('KES');
  await expect(page.getByLabel('Currency')).toHaveAttribute('readonly', '');
  await expect(page.getByLabel('Installer secret')).toHaveAttribute('type', 'password');
  await expect(page.getByRole('button', { name: 'Save installation settings' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('setup timezone list is restricted to approved options', async ({ page }) => {
  await page.goto('/setup');
  const options = await page.getByLabel('Timezone').locator('option').allTextContents();
  expect(options).toContain('Africa/Nairobi');
  expect(options).not.toContain('America/New_York');
});

test('setup POST rejects a missing CSRF token', async ({ page }) => {
  const response = await page.request.post('/setup', {
    form: { name: 'No CSRF Hotel', timezone: 'Africa/Nairobi', installer_secret: 'x'.repeat(40) },
  });
  expect(response.status()).toBe(419);
});

test('setup POST rejects a wrong installer secret', async ({ page }) => {
  await page.goto('/setup');
  await page.getByLabel('Hotel trading name').fill('Secret Hotel');
  await page.getByLabel('Installer secret').fill('wrong-secret-value-that-is-long-enough');
  await page.getByRole('button', { name: 'Save installation settings' }).click();
  await expect(page.getByRole('alert')).toContainText('Installer authorization failed');
});

test('setup form is keyboard operable and validates required name', async ({ page }) => {
  await page.goto('/setup');
  await page.getByRole('button', { name: 'Save installation settings' }).click();
  const name = page.getByLabel('Hotel trading name');
  await expect(name).toBeFocused();
  await page.getByLabel('Hotel trading name').fill('Keyboard Hotel');
  await expect(name).toHaveValue('Keyboard Hotel');
});
