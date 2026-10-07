import { test, expect } from '@playwright/test';
import { signIn } from './helpers.mjs';

test('an owner can view the staff list and administration controls', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/staff');
  await expect(page.getByRole('heading', { level: 1, name: 'Staff' })).toBeVisible();
  await expect(page.getByRole('cell', { name: 'owner@demo.test' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Create staff member' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: 'Change roles' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: 'Edit name or email' })).toBeVisible();
});

test('a non-owner cannot administer staff', async ({ page }) => {
  await signIn(page, 'cashier@demo.test');
  const response = await page.goto('/admin/staff');
  if (response.status() === 200) {
    expect(page.url()).not.toContain('/admin/staff');
  } else {
    expect([302, 403]).toContain(response.status());
  }
});

test('an invalid staff creation surfaces an accessible error', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/staff');
  // Bypass native validation so the server-side error path is exercised.
  await page.evaluate(() => document.querySelectorAll('form').forEach((form) => { form.noValidate = true; }));
  await page.getByRole('button', { name: 'Create staff member' }).click();
  await expect(page.getByRole('alert')).toContainText("didn't work");
});
