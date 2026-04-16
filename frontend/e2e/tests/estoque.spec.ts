import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE ESTOQUE                                 ║
// ║  Dashboard, Armazéns, Movimentações, Lotes, Inventário, etc  ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasEstoque = [
  { rota: '/estoque', nome: 'Dashboard Estoque' },
  { rota: '/estoque/armazens', nome: 'Armazéns' },
  { rota: '/estoque/movimentacoes', nome: 'Movimentações' },
  { rota: '/estoque/lotes', nome: 'Gestão de Lotes' },
  { rota: '/estoque/inventarios', nome: 'Lista de Inventários' },
  { rota: '/estoque/inventarios/novo', nome: 'Novo Inventário' },
  { rota: '/estoque/inventario-pwa', nome: 'Inventário PWA' },
  { rota: '/estoque/movimentar-qr', nome: 'Movimentar QR' },
  { rota: '/estoque/kardex', nome: 'Kardex' },
  { rota: '/estoque/calibracoes-ferramentas', nome: 'Calibrações de Ferramentas' },
  { rota: '/estoque/inteligencia', nome: 'Inteligência de Estoque' },
  { rota: '/estoque/etiquetas', nome: 'Etiquetas' },
  { rota: '/estoque/integracao', nome: 'Integração de Estoque' },
  { rota: '/estoque/transferencias', nome: 'Transferências' },
  { rota: '/estoque/pecas-usadas', nome: 'Peças Usadas' },
  { rota: '/estoque/numeros-serie', nome: 'Números de Série' },
];

for (const { rota, nome } of rotasEstoque) {
  test(`Estoque: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test.describe('Estoque — Comportamento', () => {
  test('Dashboard de estoque deve ter URL correta', async ({ page }) => {
    await page.goto('/estoque');
    await expect(page).toHaveURL(/.*estoque/);
  });

  test('Armazéns deve exibir conteúdo', async ({ page }) => {
    await page.goto('/estoque/armazens');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Movimentações deve exibir conteúdo', async ({ page }) => {
    await page.goto('/estoque/movimentacoes');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Kardex deve estar acessível', async ({ page }) => {
    await page.goto('/estoque/kardex');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Transferências deve estar acessível', async ({ page }) => {
    await page.goto('/estoque/transferencias');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Números de série deve estar acessível', async ({ page }) => {
    await page.goto('/estoque/numeros-serie');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Etiquetas deve estar acessível', async ({ page }) => {
    await page.goto('/estoque/etiquetas');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Inteligência de Estoque deve estar acessível', async ({ page }) => {
    await page.goto('/estoque/inteligencia');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
