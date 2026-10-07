import { test, expect } from '@playwright/test';
import { signIn } from './helpers.mjs';

// Pair a fresh kiosk device through the real admin + pairing flow and return its page.
async function pairKiosk(page, browser) {
  await signIn(page);
  await page.goto('/admin/devices');
  await page.getByLabel('Name').fill('Test Kiosk');
  await page.getByLabel('Type').selectOption('kiosk');
  await page.getByRole('button', { name: 'Create and get pairing code' }).click();
  const notice = await page.locator('.notice').first().innerText();
  const code = (notice.match(/[A-Z0-9]{4}-[A-Z0-9]{4}/) || [])[0];
  expect(code, 'a pairing code is shown').toBeTruthy();

  const device = await browser.newContext();
  const kiosk = await device.newPage();
  await kiosk.goto('/device/pair');
  await kiosk.getByLabel('Pairing code').fill(code);
  await kiosk.getByRole('button', { name: 'Pair device' }).click();
  await kiosk.waitForURL(/\/kiosk/);
  return kiosk;
}

test('the real kiosk menu and customiser work end to end', async ({ page, browser }) => {
  const kiosk = await pairKiosk(page, browser);
  try {
    await kiosk.goto('/kiosk');
    await kiosk.getByRole('button', { name: 'Start order' }).click();

    // P11.03 — search filters the menu, keeps focus, and shows an empty state.
    const search = kiosk.getByLabel('Search the menu');
    await search.fill('tilapia');
    await expect(search).toBeFocused();
    await expect(kiosk.getByRole('button', { name: /Whole fried tilapia/ })).toBeVisible();
    await expect(kiosk.getByRole('button', { name: /Classic cheeseburger/ })).toHaveCount(0);
    await search.fill('zzzz');
    await expect(kiosk.getByText('No dishes match your search.')).toBeVisible();
    await kiosk.getByRole('button', { name: 'Show all dishes' }).click();

    // P11.02 — published meal cards render on the real screen.
    const burger = kiosk.getByRole('button', { name: /Classic cheeseburger/ });
    await expect(burger).toBeVisible();
    await burger.click();

    // P11.05/P11.06 — fixed ingredient is non-interactive; removable offers Remove; extra offers Add.
    await expect(kiosk.getByText('Beef patty')).toBeVisible();
    await expect(kiosk.getByText('Included').first()).toBeVisible();
    await expect(kiosk.getByRole('button', { name: 'Remove' }).first()).toBeVisible();
    await expect(kiosk.getByRole('button', { name: /^Add / }).first()).toBeVisible();

    // P11.06 — the fixed ingredient has no removal control.
    const fixedRow = kiosk.locator('.ing', { hasText: 'Beef patty' });
    await expect(fixedRow.getByRole('button', { name: 'Remove' })).toHaveCount(0);

    // P11.07 — a dense ingredient set collapses into an overflow tray.
    const tray = kiosk.getByRole('button', { name: /Show all \d+ ingredients/ });
    await expect(tray).toBeVisible();
    await tray.click();
    await expect(kiosk.getByRole('button', { name: 'Show fewer ingredients' })).toBeVisible();

    // P11.09 — quantity and the add-to-order line summary.
    await kiosk.getByRole('button', { name: 'More' }).click();
    await kiosk.getByRole('button', { name: /Add to order/ }).click();

    // The cart now shows the line with its quantity.
    await expect(kiosk.getByText(/Classic cheeseburger/).first()).toBeVisible();
  } finally {
    await kiosk.context().close();
  }
});
