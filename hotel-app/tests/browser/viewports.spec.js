import { test, expect } from '@playwright/test';

const viewports = {
  fallback: { width: 360, height: 800 },
  tabletPortrait: { width: 768, height: 1024 },
  tabletLandscape: { width: 1024, height: 768 },
  kioskPortrait: { width: 1080, height: 1920 },
  cashier: { width: 1440, height: 900 },
};

const modeTargets = {
  customer: 'tabletLandscape',
  kiosk: 'kioskPortrait',
  staff: 'cashier',
  kitchen: 'tabletLandscape',
  collection: 'tabletLandscape',
  cashier: 'cashier',
  bill: 'tabletLandscape',
};

for (const [mode, target] of Object.entries(modeTargets)) {
  test(`${mode} reflows at its target viewport (${target}) without horizontal overflow`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: viewports[target] });
    const page = await context.newPage();
    await page.goto(`/preview/${mode}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await context.close();
  });
}

for (const mode of Object.keys(modeTargets)) {
  test(`${mode} reflows in tablet portrait (768x1024) without horizontal overflow`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: viewports.tabletPortrait });
    const page = await context.newPage();
    await page.goto(`/preview/${mode}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await context.close();
  });
}

for (const mode of Object.keys(modeTargets)) {
  test(`${mode} reflows at the 360px fallback without horizontal overflow`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: viewports.fallback });
    const page = await context.newPage();
    await page.goto(`/preview/${mode}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await context.close();
  });
}

for (const [mode, target] of Object.entries(modeTargets)) {
  test(`${mode} reflows at 200% text zoom without horizontal overflow`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: viewports[target] });
    const page = await context.newPage();
    await page.goto(`/preview/${mode}`);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await context.close();
  });
}

test('components page reflows at 200% text zoom without losing actions', async ({ page }) => {
  await page.goto('/preview/components');
  await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  await expect(page.getByRole('button', { name: 'Add to order' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Open review dialog' })).toBeVisible();
});

test('keyboard order reaches the primary customer action with visible focus', async ({ page }) => {
  await page.goto('/preview/customer');
  const target = page.getByRole('button', { name: 'Customise' }).first();
  for (let i = 0; i < 40 && !(await target.evaluate((el) => el === document.activeElement)); i += 1) {
    await page.keyboard.press('Tab');
  }
  await expect(target).toBeFocused();
  const outline = await target.evaluate((el) => getComputedStyle(el).outlineStyle);
  expect(outline).not.toBe('none');
});
