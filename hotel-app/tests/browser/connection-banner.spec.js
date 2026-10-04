import { test, expect } from '@playwright/test';

test('offline shows the offline state and recovers', async ({ page, context }) => {
  await page.goto('/preview/components');
  const banner = page.locator('#connection-banner');
  await context.setOffline(true);
  await page.evaluate(() => window.dispatchEvent(new Event('offline')));
  await expect(banner).toHaveAttribute('data-state', 'offline');
  await expect(banner).toHaveText(/offline/);
  await context.setOffline(false);
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await expect(banner).not.toHaveAttribute('data-state');
  await expect(banner).toHaveText('');
});

test('unreachable is distinct from provider failure', async ({ page }) => {
  await page.goto('/preview/components');
  await page.evaluate(() => {
    const el = document.createElement('p');
    el.id = 'isolated-banner';
    document.body.appendChild(el);
  });
  const banner = page.locator('#isolated-banner');
  await page.evaluate(async () => {
    const { initConnectionBanner } = await import('/assets/js/lib/connection-banner.js');
    const handle = initConnectionBanner({
      banner: document.getElementById('isolated-banner'),
      fetchProbe: () => Promise.reject(Object.assign(new Error('down'), { kind: 'unreachable' })),
    });
    window.__banner = handle;
    await null;
  });
  await expect(banner).toHaveAttribute('data-state', 'unreachable');
  await page.evaluate(() => window.__banner.reportProviderFailure());
  await expect(banner).toHaveAttribute('data-state', 'provider');
  await expect(banner).toHaveText(/service is unavailable/);
});

test('abort errors do not raise the banner and stale probes cannot hide offline', async ({ page, context }) => {
  await page.goto('/preview/components');
  await page.evaluate(() => {
    const el = document.createElement('p');
    el.id = 'isolated-race-banner';
    document.body.appendChild(el);
  });
  const banner = page.locator('#isolated-race-banner');
  await page.evaluate(async () => {
    const { initConnectionBanner } = await import('/assets/js/lib/connection-banner.js');
    let release;
    window.__resolveProbe = () => release();
    window.__banner = initConnectionBanner({
      banner: document.getElementById('isolated-race-banner'),
      fetchProbe: () => new Promise((resolve) => { release = resolve; }),
    });
    await null;
  });
  await context.setOffline(true);
  await page.evaluate(() => window.dispatchEvent(new Event('offline')));
  await expect(banner).toHaveAttribute('data-state', 'offline');
  // A late-resolving probe must not hide the offline banner.
  await page.evaluate(() => window.__resolveProbe());
  await expect(banner).toHaveAttribute('data-state', 'offline');
  await context.setOffline(false);
});

test('an aborted probe leaves the banner unchanged', async ({ page }) => {
  await page.goto('/preview/components');
  await page.evaluate(() => {
    const el = document.createElement('p');
    el.id = 'isolated-abort-banner';
    document.body.appendChild(el);
  });
  const banner = page.locator('#isolated-abort-banner');
  await page.evaluate(async () => {
    const { initConnectionBanner } = await import('/assets/js/lib/connection-banner.js');
    initConnectionBanner({
      banner: document.getElementById('isolated-abort-banner'),
      fetchProbe: () => Promise.reject(new DOMException('aborted', 'AbortError')),
    });
    await null;
  });
  await expect(banner).not.toHaveAttribute('data-state');
  await expect(banner).toHaveText('');
});

test('a missing locale bundle still initialises the banner (bootstrap resilience)', async ({ page }) => {
  await page.route('**/locales/en.json', (route) => route.fulfill({ status: 500, body: 'nope' }));
  await page.goto('/preview/components');
  await page.evaluate(() => window.dispatchEvent(new Event('offline')));
  await expect(page.locator('#connection-banner')).toHaveAttribute('data-state', 'offline');
});
