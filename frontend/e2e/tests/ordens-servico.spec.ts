import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE ORDENS DE SERVIÇO                       ║
// ║  Lista, Kanban, Criação, Dashboard, Agenda, Mapa, SLA        ║
// ╚═══════════════════════════════════════════════════════════════╝

test.describe('Ordens de Serviço — Navegação', () => {
  test('Deve carregar a lista de OS', async ({ page }) => {
    await page.goto('/os');
    await expect(page).toHaveURL(/.*\/os/);
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o kanban de OS', async ({ page }) => {
    await page.goto('/os/kanban');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a página de criação de OS', async ({ page }) => {
    await page.goto('/os/nova');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a agenda de OS', async ({ page }) => {
    await page.goto('/os/agenda');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o mapa de OS', async ({ page }) => {
    await page.goto('/os/mapa');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o dashboard de OS', async ({ page }) => {
    await page.goto('/os/dashboard');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Ordens de Serviço — Funcionalidades', () => {
  test('Deve carregar contratos recorrentes', async ({ page }) => {
    await page.goto('/os/contratos-recorrentes');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar políticas de SLA', async ({ page }) => {
    await page.goto('/os/sla');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o dashboard de SLA', async ({ page }) => {
    await page.goto('/os/sla-dashboard');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a página de checklists', async ({ page }) => {
    await page.goto('/os/checklists');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar checklists de serviço', async ({ page }) => {
    await page.goto('/os/checklists-servico');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar kits de peças', async ({ page }) => {
    await page.goto('/os/kits-pecas');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Página de lista de OS não deve redirecionar para login', async ({ page }) => {
    await page.goto('/os');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Página de criação de OS não deve redirecionar para login', async ({ page }) => {
    await page.goto('/os/nova');
    await expect(page).not.toHaveURL(/.*login/);
  });
});

test.describe('Chamados (Service Calls)', () => {
  test('Deve carregar a lista de chamados', async ({ page }) => {
    await page.goto('/chamados');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a página de novo chamado', async ({ page }) => {
    await page.goto('/chamados/novo');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o kanban de chamados', async ({ page }) => {
    await page.goto('/chamados/kanban');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o dashboard de chamados', async ({ page }) => {
    await page.goto('/chamados/dashboard');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o mapa de chamados', async ({ page }) => {
    await page.goto('/chamados/mapa');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar a agenda de técnicos', async ({ page }) => {
    await page.goto('/chamados/agenda');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Técnicos', () => {
  test('Deve carregar a agenda de técnicos', async ({ page }) => {
    await page.goto('/tecnicos/agenda');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar os apontamentos', async ({ page }) => {
    await page.goto('/tecnicos/apontamentos');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Deve carregar o caixa de técnicos', async ({ page }) => {
    await page.goto('/tecnicos/caixa');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});
