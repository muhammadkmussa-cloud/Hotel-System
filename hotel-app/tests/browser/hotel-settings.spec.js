import { test, expect } from '@playwright/test';

test('hotel settings denies anonymous visitors', async ({ page }) => {
  const response = await page.goto('/admin/settings');
  if (response.status() === 200) {
    expect(page.url()).not.toContain('/admin/settings');
  } else {
    expect([302, 401, 403]).toContain(response.status());
  }
});
