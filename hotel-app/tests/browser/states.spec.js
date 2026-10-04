import { test, expect } from '@playwright/test';

const titles = ['Loading', 'Empty', 'Denied', 'Recoverable error'];

test('each system state is labelled by its heading and offers a usable action', async ({ page }) => {
  await page.goto('/preview/components');
  for (const title of titles) {
    const state = page.locator('.state', { has: page.getByRole('heading', { name: title }) });
    await expect(state).toBeVisible();
    const labelId = await state.getAttribute('aria-labelledby');
    await expect(state.getByRole('heading', { name: title })).toHaveAttribute('id', labelId);
    const action = state.getByRole('link');
    await expect(action).toHaveCount(1);
    await expect(action).toHaveAttribute('href', /.+/);
  }
});
