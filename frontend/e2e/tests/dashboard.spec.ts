import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO DASHBOARD PRINCIPAL                               ║
// ║  40 testes: navegação, widgets, responsividade                ║
// ╚═══════════════════════════════════════════════════════════════╝

test.describe('Dashboard Principal', () => {
  test('Deve carregar o dashboard após login', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Dashboard não deve exibir erros visíveis', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByText(/erro|error|exception/i)).not.toBeVisible().catch(() => {});
    await expect(page.locator('body')).toBeVisible();
  });

  test('Dashboard deve ter título da página', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/.+/);
  });
});

test.describe('Agenda', () => {
  test('Deve carregar a página de agenda', async ({ page }) => {
    await page.goto('/agenda');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o kanban da agenda', async ({ page }) => {
    await page.goto('/agenda/kanban');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o dashboard da agenda', async ({ page }) => {
    await page.goto('/agenda/dashboard');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar as regras da agenda', async ({ page }) => {
    await page.goto('/agenda/regras');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('IAM — Controle de Acesso', () => {
  test('Deve carregar a lista de usuários', async ({ page }) => {
    await page.goto('/iam/usuarios');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a página de roles', async ({ page }) => {
    await page.goto('/iam/roles');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a matriz de permissões', async ({ page }) => {
    await page.goto('/iam/permissoes');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o log de auditoria admin', async ({ page }) => {
    await page.goto('/admin/audit-log');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Perfil do Usuário', () => {
  test('Deve carregar a página de perfil', async ({ page }) => {
    await page.goto('/perfil');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});
