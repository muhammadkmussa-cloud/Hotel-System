import { test, expect } from '@playwright/test';

test('staff administration denies anonymous visitors', async ({ page }) => {
  const response = await page.goto('/admin/staff');
  // Either a safe 401/403 or a redirect to the sign-in screen; never the admin page.
  if (response.status() === 200) {
    expect(page.url()).not.toContain('/admin/staff');
  } else {
    expect([302, 401, 403]).toContain(response.status());
  }
});
