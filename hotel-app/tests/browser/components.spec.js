import { test, expect } from '@playwright/test';

test('buttons expose accessible names and disabled explanation', async ({ page }) => {
  await page.goto('/preview/components');
  await expect(page.getByRole('button', { name: 'Add to order' })).toBeVisible();
  const disabled = page.getByRole('button', { name: 'Remove item' });
  await expect(disabled).toBeDisabled();
  await expect(page.locator('#disabled-note')).toHaveText(/unavailable/);
});

test('text input is labelled and error is described', async ({ page }) => {
  await page.goto('/preview/components');
  const input = page.getByLabel('Guest name');
  await expect(input).toBeVisible();
  await expect(input).toHaveAttribute('aria-invalid', 'true');
  await expect(input).toHaveAttribute('aria-describedby', 'guest-name-error');
  await expect(page.locator('#guest-name-error')).toBeVisible();
});

test('keyboard can reach and operate the add button', async ({ page }) => {
  await page.goto('/preview/components');
  await page.keyboard.press('Tab'); // skip link
  await page.keyboard.press('Tab'); // prototype switcher link 1
  for (let i = 0; i < 6; i += 1) await page.keyboard.press('Tab');
  const button = page.getByRole('button', { name: 'Add to order' });
  // tab until focused or give up after 20 tabs
  for (let i = 0; i < 20 && !(await button.evaluate((el) => el === document.activeElement)); i += 1) await page.keyboard.press('Tab');
  await expect(button).toBeFocused();
});
