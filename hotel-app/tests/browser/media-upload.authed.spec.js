import { test, expect } from '@playwright/test';
import { signIn } from './helpers.mjs';

// A valid 32x32 PNG (123 bytes, above the 120-byte raster minimum).
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAALUlEQVRIiWOsCNBgoCVgoqnpoxaMWjBqwagFoxaMWjBqwagFoxaMWjBqARUBAO1EATC9A4JlAAAAAElFTkSuQmCC',
  'base64',
);

// A distinct valid 33x29 PNG so the ingredient upload is not deduplicated.
const INGREDIENT_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAACEAAAAdCAIAAACrPpLuAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAALElEQVRIiWMUWRDFQGPARGsLRu0YtWPUjlE7Ru0YtWPUjlE7Ru0YtWMA7QAARNEBSFCJ/cMAAAAASUVORK5CYII=',
  'base64',
);

test('the uploader previews a valid photo and reports a bad one', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/media');
  const input = page.getByLabel('Photo file');

  await input.setInputFiles({ name: 'dish.png', mimeType: 'image/png', buffer: PNG });
  await expect(page.locator('[data-upload-preview]')).toBeVisible();
  await expect(page.locator('[data-upload-name]')).toContainText('dish.png');

  await input.setInputFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
  await expect(page.locator('[data-upload-error]')).toBeVisible();
  await expect(page.locator('[data-upload-error]')).toContainText('JPEG, PNG or WebP');
});

test('an uploaded photo is saved as a draft, never published', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/media');
  await page.getByLabel('Photo file').setInputFiles({ name: 'dish.png', mimeType: 'image/png', buffer: PNG });
  await page.getByRole('button', { name: 'Upload' }).click();
  await page.waitForURL(/\/admin\/media\/[0-9a-f-]{36}$/, { timeout: 15000 });
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  await expect(page.locator('.pill', { hasText: 'draft' })).toBeVisible();
  await expect(page.locator('.pill', { hasText: 'published' })).toHaveCount(0);
});

test('hotel approval is recorded explicitly', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/media');
  await page.getByLabel('Photo file').setInputFiles({ name: 'dish.png', mimeType: 'image/png', buffer: PNG });
  await page.getByRole('button', { name: 'Upload' }).click();
  await page.waitForURL(/\/admin\/media\/[0-9a-f-]{36}$/, { timeout: 15000 });
  await page.getByLabel('Content approver').fill('Chef Amina');
  await page.getByLabel('Chef approver').fill('Head Chef Kamau');
  await page.getByRole('button', { name: 'Record approval' }).click();
  await expect(page.locator('[data-approval-summary]')).toContainText('Chef Amina');
  await expect(page.locator('[data-approval-summary]')).toContainText('Head Chef Kamau');
});

test('the ingredient create form lists uploaded ingredient photos', async ({ page }) => {
  await signIn(page);
  await page.goto('/admin/media?kind=ingredient');
  await page.getByLabel('Photo file').setInputFiles({ name: 'herb.png', mimeType: 'image/png', buffer: INGREDIENT_PNG });
  await page.getByRole('button', { name: 'Upload' }).click();
  await page.waitForURL(/\/admin\/media\/[0-9a-f-]{36}$/, { timeout: 15000 });
  await page.goto('/admin/ingredients');
  await expect(page.getByRole('heading', { level: 1, name: 'Ingredients' })).toBeVisible();
  await expect(page.getByLabel('Photo')).toBeVisible();
  // The uploaded photo appears as a selectable option (plus the "No photo" default).
  expect(await page.locator('#media_id option').count()).toBeGreaterThan(1);
});
