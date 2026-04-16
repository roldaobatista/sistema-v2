import { Page, expect } from '@playwright/test';

export class LoginPage {
  constructor(public readonly page: Page) {}

  async goto() {
    await this.page.goto('/login');
  }

  async login(email = process.env.E2E_EMAIL ?? 'admin@example.test', password = process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD') {
    await this.page.getByLabel('E-mail').fill(email);
    await this.page.getByLabel('Senha').fill(password);
    await this.page.getByRole('button', { name: /entrar/i }).click();
    await this.page.waitForURL('**/dashboard**');
  }

  async checkErrorMessage(message: string) {
    await expect(this.page.locator(`text=${message}`)).toBeVisible();
  }
}
