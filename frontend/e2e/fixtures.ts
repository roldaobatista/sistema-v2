import { expect, test as base, type Page } from '@playwright/test';
import { gotoAuthenticated } from './helpers';
import { LoginPage } from './pages/LoginPage';
import { Navigation } from './pages/Navigation';

// Define the types for our fixtures
type MyFixtures = {
  loginPage: LoginPage;
  nav: Navigation;
};

// Extend base test to include our custom fixtures
export const test = base.extend<MyFixtures>({
  loginPage: async ({ page }, setLoginPage) => {
    await setLoginPage(new LoginPage(page));
  },

  nav: async ({ page }, setNavigation) => {
    await setNavigation(new Navigation(page));
  },
});

export async function navigateToModule(page: Page, path: string): Promise<void> {
  await gotoAuthenticated(page, path);
  await expect(page.locator('main').first()).toBeVisible({ timeout: 10000 });
}

export async function clickSubmitButton(page: Page): Promise<void> {
  const submitButton = page.locator([
    'button[type="submit"]',
    'button:has-text("Salvar")',
    'button:has-text("Criar")',
    'button:has-text("Enviar")',
    'button:has-text("Confirmar")',
    '[data-testid*="submit"]',
  ].join(', ')).first();

  if (await submitButton.count() > 0) {
    await submitButton.click();
    return;
  }

  await page.keyboard.press('Enter');
}

export async function confirmDeleteDialog(page: Page): Promise<void> {
  const dialog = page.locator('[role="alertdialog"], [role="dialog"]').first();
  const scopedButton = dialog.locator('button:has-text("Excluir"), button:has-text("Confirmar"), button:has-text("Sim")').first();

  if (await scopedButton.count() > 0) {
    await scopedButton.click();
    return;
  }

  const fallbackButton = page.locator('button:has-text("Excluir"), button:has-text("Confirmar"), button:has-text("Sim")').first();
  await fallbackButton.click();
}

export const testCustomer = {
  name: `Cliente E2E ${Date.now()}`,
  email: `cliente.e2e.${Date.now()}@example.com`,
  phone: '11999998888',
};

export const testCustomerPJ = {
  name: `Empresa E2E ${Date.now()}`,
  document: '11222333000181',
};

export const testQuote = {
  valid_until: '2026-12-31',
};

export const testWorkOrder = {
  description: `OS E2E ${Date.now()}`,
};

export { expect };
