import { test, expect } from '@playwright/test';

test('sign-in screen renders labelled credential fields', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const response = await page.goto('/staff/sign-in');
  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { level: 1, name: 'Staff sign in' })).toBeVisible();
  await expect(page.getByLabel('Email')).toHaveAttribute('autocomplete', 'username');
  await expect(page.getByLabel('Password')).toHaveAttribute('type', 'password');
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('sign-in POST rejects a missing CSRF token', async ({ page }) => {
  const response = await page.request.post('/staff/sign-in', {
    form: { email: 'staff@example.test', password: 'whatever-password' },
  });
  expect(response.status()).toBe(419);
});

test('form prevents duplicate submissions and shows a busy state', async ({ page }) => {
  await page.goto('/staff/sign-in');
  await page.getByLabel('Email').fill('staff@example.test');
  await page.getByLabel('Password').fill('wrong-password-000');
  const state = await page.evaluate(() => {
    const form = document.querySelector('form[data-single-submit]');
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    const button = form.querySelector('button');
    const first = {
      disabled: button.disabled,
      label: button.textContent,
      busy: form.getAttribute('aria-busy'),
      submitting: form.dataset.submitting,
      status: form.querySelector('[data-submit-status]').textContent,
    };
    const second = new Event('submit', { cancelable: true, bubbles: true });
    form.dispatchEvent(second);
    return { first, preventedSecond: second.defaultPrevented };
  });
  expect(state.first.disabled).toBe(true);
  expect(state.first.label).toBe('Signing in…');
  expect(state.first.busy).toBe('true');
  expect(state.first.submitting).toBe('true');
  expect(state.first.status).toBe('Signing in…');
  expect(state.preventedSecond).toBe(true);
});

test('invalid credentials return a safe generic error without exposing the field', async ({ page }) => {
  await page.goto('/staff/sign-in');
  await page.getByLabel('Email').fill('staff@example.test');
  await page.getByLabel('Password').fill('wrong-password-000');
  await page.getByRole('button', { name: 'Sign in' }).click();
  const alert = page.getByRole('alert');
  await expect(alert).toContainText('credentials do not match');
  await expect(alert).not.toContainText('password is incorrect');
  await expect(alert).toBeFocused();
});
