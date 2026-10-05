import { test, expect } from '@playwright/test';

// P07.05/P07.06 — the waiter table overview, its guest actions and device
// bindings are staff-only, and a forged identity must never be honoured.
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

test('device-to-guest binding requires an authenticated staff session', async ({ page, request }) => {
  // A visitor (or a paired tablet acting on its own) cannot create a binding.
  const forged = await request.post('/staff/guest-bindings', {
    form: {
      guest_id: '00000000-0000-7000-8000-000000000000',
      device_session_id: '00000000-0000-7000-8000-000000000001',
    },
    headers: { 'X-CSRF-TOKEN': 'anonymous' },
  });
  expect([302, 401, 403, 404, 405, 419]).toContain(forged.status());

  const forgedRevocation = await request.post('/staff/guest-bindings/00000000-0000-7000-8000-000000000002/revoke', {
    headers: { 'X-CSRF-TOKEN': 'anonymous' },
  });
  expect([302, 401, 403, 404, 405, 419]).toContain(forgedRevocation.status());

  const tables = await page.goto('/staff/tables');
  if (tables.status() === 200) {
    expect(page.url()).not.toContain('/staff/tables');
  } else {
    expect([302, 401, 403]).toContain(tables.status());
  }
});
