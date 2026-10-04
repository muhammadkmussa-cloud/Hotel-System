import { test, expect } from '@playwright/test';

test('starter page loads assets and supports keyboard refresh', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  const css = page.waitForResponse((response) => response.url().endsWith('/assets/css/global.css'));
  const js = page.waitForResponse((response) => response.url().endsWith('/assets/js/main.js'));
  const response = await page.goto('/');
  expect(response.status()).toBe(200);
  await expect(page).toHaveTitle('Browser Fixture Hotel');
  await expect(page.getByRole('main')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Browser Fixture Hotel');
  expect((await css).status()).toBe(200);
  expect((await js).status()).toBe(200);
  const button = page.getByRole('button', { name: 'Check again' });
  await expect(button).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(button).toBeFocused();
  await Promise.all([page.waitForEvent('load'), page.keyboard.press('Enter')]);
  await expect(page.getByText('Ordering is not available yet.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('mobile page remains readable without JavaScript', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 360, height: 800 } });
  try {
    const page = await context.newPage();
    await page.goto('/');
    await expect(page.getByText('Ordering is not available yet.')).toBeVisible();
    await expect(page.locator('[data-reload-page]')).toBeHidden();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  } finally {
    await context.close();
  }
});
