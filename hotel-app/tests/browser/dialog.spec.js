import { test, expect } from '@playwright/test';

test('dialog opens, traps focus, closes on Escape, and restores focus', async ({ page }) => {
  await page.goto('/preview/components');
  const opener = page.getByRole('button', { name: 'Open review dialog' });
  await opener.click();
  const dialog = page.locator('#demo-dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('role', 'dialog');
  const closeBtn = page.getByRole('button', { name: 'Close', exact: true }).first();
  await expect(closeBtn).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
});

test('dialog recovers focus when it escapes the layer, then Escape dismisses', async ({ page }) => {
  await page.goto('/preview/components');
  const opener = page.getByRole('button', { name: 'Open review dialog' });
  await opener.click();
  const dialog = page.locator('#demo-dialog');
  await page.getByRole('heading', { level: 1 }).click();
  await expect(dialog).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(dialog.locator(':focus')).toHaveCount(1);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
});

test('double open does not stack listeners and one close removes the layer', async ({ page }) => {
  await page.goto('/preview/components');
  await page.evaluate(() => {
    const dialog = document.getElementById('demo-dialog');
    dialog.addEventListener('keydown', () => { dialog.dataset.count = String(Number(dialog.dataset.count ?? 0) + 1); });
  });
  const opener = page.getByRole('button', { name: 'Open review dialog' });
  await opener.click();
  await opener.click();
  const dialog = page.locator('#demo-dialog');
  await expect(dialog).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await page.keyboard.press('Tab');
  const count = await dialog.evaluate((el) => el.dataset.count ?? '0');
  expect(Number(count)).toBeLessThanOrEqual(1);
});

test('drawer uses the same primitive and restores focus', async ({ page }) => {
  await page.goto('/preview/components');
  const opener = page.getByRole('button', { name: 'Open ingredient drawer' });
  await opener.click();
  const drawer = page.locator('#demo-drawer');
  await expect(drawer).toBeVisible();
  await expect(drawer).toHaveAttribute('data-variant', 'drawer');
  await page.keyboard.press('Escape');
  await expect(drawer).toBeHidden();
  await expect(opener).toBeFocused();
});

test('empty-focusable layer focuses the layer itself without crashing', async ({ page }) => {
  await page.goto('/preview/components');
  await page.evaluate(async () => {
    const { openDialog } = await import('/assets/js/components/ui/dialog.js');
    const layer = document.createElement('div');
    layer.id = 'empty-layer';
    layer.hidden = true;
    document.body.appendChild(layer);
    openDialog(layer);
  });
  await expect(page.locator('#empty-layer')).toBeFocused();
});
