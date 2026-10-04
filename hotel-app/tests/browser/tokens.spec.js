import { test, expect } from '@playwright/test';

test('design tokens are exposed and applied', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const css = page.waitForResponse((r) => r.url().endsWith('/assets/css/tokens.css'));
  await page.goto('/preview/customer');
  expect((await css).status()).toBe(200);
  const tokens = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    return {
      canvas: cs.getPropertyValue('--color-canvas').trim(),
      action: cs.getPropertyValue('--color-action').trim(),
      space4: cs.getPropertyValue('--space-4').trim(),
      radius: cs.getPropertyValue('--radius-control').trim(),
      bodyBg: getComputedStyle(document.documentElement).backgroundColor,
    };
  });
  expect(tokens.canvas).toBe('#F7F4EE');
  expect(tokens.action).toBe('#164A37');
  expect(tokens.space4).toBe('16px');
  expect(tokens.radius).toBe('12px');
  expect(tokens.bodyBg).toBe('rgb(247, 244, 238)');
  expect(errors).toEqual([]);
});

test('body text meets contrast against canvas', async ({ page }) => {
  await page.goto('/preview/customer');
  const ratio = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    const ink = cs.getPropertyValue('--color-ink').trim();
    const canvas = cs.getPropertyValue('--color-canvas').trim();
    const lum = (hex) => {
      const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const [l1, l2] = [lum(ink), lum(canvas)].sort((a, b) => b - a);
    return (l1 + 0.05) / (l2 + 0.05);
  });
  expect(ratio).toBeGreaterThanOrEqual(4.5);
});
