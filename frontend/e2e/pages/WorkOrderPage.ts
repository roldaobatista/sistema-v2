import { Page, expect } from '@playwright/test';

export class WorkOrderPage {
  constructor(public readonly page: Page) {}

  async gotoList() {
    await this.page.goto('/os');
    // Ensure table is loaded
    await expect(this.page.getByRole('table')).toBeVisible({ timeout: 10000 });
  }

  async openCreateModal() {
    await this.page
      .getByTestId('work-order-create-button')
      .or(this.page.getByRole('button', { name: /nova\s*(os|o\.s\.)/i }))
      .first()
      .click();
    await expect(this.page.getByRole('dialog')).toBeVisible();
  }

  async fillExpressForm(data: { customerName: string, description: string }) {
    // Fill customer combobox (requires typing and selecting from dropdown)
    await this.page.getByPlaceholder(/selecione um cliente/i).click();
    await this.page.getByPlaceholder(/buscar/i).fill(data.customerName);
    await this.page.getByRole('option', { name: new RegExp(data.customerName, 'i') }).click();

    // Fill description
    await this.page.getByLabel(/descrição do problema/i).fill(data.description);
  }

  async submitForm() {
    await this.page.getByRole('button', { name: /salvar/i }).click();

    // Wait for success toast
    await expect(this.page.getByRole('status').filter({ hasText: /criada com sucesso|salvo/i })).toBeVisible({ timeout: 5000 });
  }

  async verifyWorkOrderInList(identifier: string) {
    // Search or just look in the list
    await this.page.getByPlaceholder(/buscar/i).fill(identifier);
    await this.page.keyboard.press('Enter');

    // Ensure a cell has this text
    await expect(this.page.getByRole('cell', { name: identifier }).first()).toBeVisible();
  }
}
