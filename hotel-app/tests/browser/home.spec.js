import { test, expect } from '@playwright/test';

test('landing page loads assets and offers the entry points', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  const css = page.waitForResponse((response) => response.url().endsWith('/assets/css/app.css'));
  const js = page.waitForResponse((response) => response.url().endsWith('/assets/js/main.js'));
  const response = await page.goto('/');
  expect(response.status()).toBe(200);
  await expect(page).toHaveTitle('Browser Fixture Hotel');
  await expect(page.getByRole('main')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Browser Fixture Hotel');
  expect((await css).status()).toBe(200);
  expect((await js).status()).toBe(200);
  await expect(page.getByRole('link', { name: 'Staff sign in' })).toBeVisible();
  await expect(page.getByRole('link', { name: /Pair a tablet/ })).toBeVisible();
  expect(errors).toEqual([]);
});

test('mobile page remains readable without JavaScript', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 360, height: 800 } });
  try {
    const page = await context.newPage();
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1, name: 'Browser Fixture Hotel' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Staff sign in' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  } finally {
    await context.close();
  }
});
