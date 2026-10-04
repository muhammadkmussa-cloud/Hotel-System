import { test, expect } from '@playwright/test';

const modes = ['customer', 'kiosk', 'staff', 'kitchen', 'collection', 'cashier', 'bill'];
const labels = { customer: 'Customer', kiosk: 'Kiosk', staff: 'Staff', kitchen: 'Kitchen', collection: 'Collection', cashier: 'Cashier', bill: 'Bill' };

for (const mode of modes) {
  test(`${mode} preview shell renders landmarks, demo label, and its mode nav`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const response = await page.goto(`/preview/${mode}`);
    expect(response.status()).toBe(200);
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText(/Prototype screen/)).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Prototype screens' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: `${labels[mode]} actions` })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeAttached();
    expect(errors).toEqual([]);
  });
}

test('customer mode navigation exposes no staff actions', async ({ page }) => {
  await page.goto('/preview/customer');
  const customerNav = page.getByRole('navigation', { name: 'Customer actions' });
  await expect(customerNav).toBeVisible();
  await expect(customerNav.getByText(/staff|kitchen|cashier/i)).toHaveCount(0);
});

test('customer layout composes priced meal cards and a cart action', async ({ page }) => {
  await page.goto('/preview/customer');
  await expect(page.getByRole('heading', { name: 'Grilled tilapia' })).toBeVisible();
  await expect(page.getByText('Ksh 1,200.00')).toBeVisible();
  await expect(page.getByRole('complementary', { name: 'Cart' })).toBeVisible();
});

test('kitchen layout shows exclusions and state without meal photography', async ({ page }) => {
  await page.goto('/preview/kitchen');
  await expect(page.getByText('No chilli — allergy note not implied')).toBeVisible();
  await expect(page.getByText('Preparing')).toBeVisible();
  await expect(page.locator('.meal-image')).toHaveCount(0);
});

test('cashier keeps payment received and close visit as separate controls', async ({ page }) => {
  await page.goto('/preview/cashier');
  await expect(page.getByRole('button', { name: 'Cash received' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Card terminal confirmed' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'M-PESA verified' })).toBeVisible();
  await expect(page.getByRole('button', { name: /close visit/i })).toHaveCount(0);
});

test('collection shell shows public numbers and omits private payment data', async ({ page }) => {
  await page.goto('/preview/collection');
  await expect(page.getByText('A-014')).toBeVisible();
  await expect(page.getByText(/paid|balance|M-PESA/i)).toHaveCount(0);
});
