import { test, expect } from '@playwright/test';

// P07.05 — the waiter table overview and its guest actions are staff-only.
test('table overview denies anonymous visitors', async ({ page }) => {
  const response = await page.goto('/staff/tables');
  if (response.status() === 200) {
    expect(page.url()).not.toContain('/staff/tables');
  } else {
    expect([302, 401, 403]).toContain(response.status());
  }
});

test('adding a guest requires an authenticated staff session', async ({ page, request }) => {
  const response = await page.goto('/staff/tables');
  if (response.status() === 200) {
    expect(page.url()).not.toContain('/staff/tables');
  } else {
    expect([302, 401, 403]).toContain(response.status());
  }

  // A forged guest command must never reach the domain without a principal.
  const forged = await request.post('/staff/visits/00000000-0000-7000-8000-000000000000/guests', {
    form: { name: 'Unauthorised' },
    headers: { 'X-CSRF-TOKEN': 'anonymous' },
  });
  expect([302, 401, 403, 404, 405, 419]).toContain(forged.status());
});
