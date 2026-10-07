import { expect } from '@playwright/test';

// Sign in against the seeded browser fixture (hotel:demo-seed).
export async function signIn(page, email = 'owner@demo.test', password = 'demo-password-2026') {
  await page.goto('/staff/sign-in');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).not.toHaveURL(/\/staff\/sign-in$/);
}
