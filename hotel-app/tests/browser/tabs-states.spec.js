import { test, expect } from '@playwright/test';

test('tabs switch panels with roving tabindex and arrow keys', async ({ page }) => {
  await page.goto('/preview/components');
  const hot = page.getByRole('tab', { name: 'Hot' });
  const cold = page.getByRole('tab', { name: 'Cold' });
  await expect(hot).toHaveAttribute('aria-selected', 'true');
  await expect(hot).toHaveAttribute('tabindex', '0');
  await expect(cold).toHaveAttribute('aria-selected', 'false');
  await expect(cold).toHaveAttribute('tabindex', '-1');
  await expect(page.getByText('Hot kitchen tickets appear here.')).toBeVisible();
  await expect(page.locator('#panel-cold')).toBeHidden();

  await cold.click();
  await expect(cold).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('#panel-hot')).toBeHidden();
  await expect(cold).toBeFocused();

  await page.keyboard.press('ArrowLeft');
  await expect(hot).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('ArrowRight');
  await expect(cold).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('Home');
  await expect(hot).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('End');
  await expect(cold).toHaveAttribute('aria-selected', 'true');
});

test('table is labelled with caption and scopes', async ({ page }) => {
  await page.goto('/preview/components');
  await expect(page.getByRole('table', { name: 'Guests at table 4' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Guest' })).toBeVisible();
  await expect(page.getByRole('rowheader', { name: 'Guest 1' })).toBeVisible();
  await expect(page.getByRole('cell', { name: 'Seated' })).toBeVisible();
});

test('status region updates are announced', async ({ page }) => {
  await page.goto('/preview/components');
  const status = page.locator('#demo-status');
  await expect(status).toHaveText('Order sent to the kitchen.');
  await status.evaluate((el) => { el.textContent = 'Payment received.'; });
  await expect(status).toHaveText('Payment received.');
});
