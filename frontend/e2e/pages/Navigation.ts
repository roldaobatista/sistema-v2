import { Page, expect } from '@playwright/test';

export class Navigation {
  constructor(public readonly page: Page) {}

  async clickSidebarItem(name: string | RegExp) {
    // Assuming the sidebar items are roles of link or button
    const element = this.page.getByRole('link', { name }).or(this.page.getByRole('button', { name }));
    await element.scrollIntoViewIfNeeded();
    await element.click();
  }

  async expandMenu(menuName: string | RegExp) {
    // Toggles a collapsible menu
    const menuButton = this.page.getByRole('button', { name: menuName });
    const isExpanded = await menuButton.getAttribute('aria-expanded');

    if (isExpanded !== 'true') {
      await menuButton.click();
      // Wait for animation
      await this.page.waitForTimeout(300);
    }
  }

  async verifyUrl(path: string | RegExp) {
    await expect(this.page).toHaveURL(path);
  }

  async logout() {
    await this.page.getByRole('button', { name: /usuário/i }).click(); // Abstracted user menu
    await this.page.getByRole('menuitem', { name: /sair/i }).click();
  }
}
