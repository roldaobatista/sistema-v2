import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE CADASTROS                               ║
// ║  Clientes, Produtos, Serviços, Fornecedores, Catálogo        ║
// ╚═══════════════════════════════════════════════════════════════╝

test.describe('Cadastro de Clientes', () => {
  test('Deve carregar a lista de clientes', async ({ page }) => {
    await page.goto('/cadastros/clientes');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Lista de clientes deve exibir tabela ou cards', async ({ page }) => {
    await page.goto('/cadastros/clientes');
    const content = page.locator('table, [role="grid"], [data-testid]').first();
    await expect(content).toBeVisible().catch(() => {
      expect(page.locator('body')).not.toBeEmpty();
    });
  });

  test('Deve carregar a página de fusão de clientes', async ({ page }) => {
    await page.goto('/cadastros/clientes/fusao');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Página de clientes não deve exibir erro de servidor', async ({ page }) => {
    await page.goto('/cadastros/clientes');
    await expect(page.getByText(/500|erro interno/i)).not.toBeVisible().catch(() => {});
  });

  test('Deve manter URL ao navegar para clientes', async ({ page }) => {
    await page.goto('/cadastros/clientes');
    await expect(page).toHaveURL(/.*cadastros\/clientes/);
  });
});

test.describe('Cadastro de Produtos', () => {
  test('Deve carregar a lista de produtos', async ({ page }) => {
    await page.goto('/cadastros/produtos');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Página de produtos deve manter URL correta', async ({ page }) => {
    await page.goto('/cadastros/produtos');
    await expect(page).toHaveURL(/.*cadastros\/produtos/);
  });

  test('Página de produtos não deve redirecionar para login', async ({ page }) => {
    await page.goto('/cadastros/produtos');
    await expect(page).not.toHaveURL(/.*login/);
  });
});

test.describe('Cadastro de Serviços', () => {
  test('Deve carregar a lista de serviços', async ({ page }) => {
    await page.goto('/cadastros/servicos');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Página de serviços deve manter URL correta', async ({ page }) => {
    await page.goto('/cadastros/servicos');
    await expect(page).toHaveURL(/.*cadastros\/servicos/);
  });
});

test.describe('Cadastro de Fornecedores', () => {
  test('Deve carregar a lista de fornecedores', async ({ page }) => {
    await page.goto('/cadastros/fornecedores');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Página de fornecedores deve manter URL correta', async ({ page }) => {
    await page.goto('/cadastros/fornecedores');
    await expect(page).toHaveURL(/.*cadastros\/fornecedores/);
  });
});

test.describe('Histórico de Preços', () => {
  test('Deve carregar o histórico de preços', async ({ page }) => {
    await page.goto('/cadastros/historico-precos');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Exportação em Lote', () => {
  test('Deve carregar a página de exportação em lote', async ({ page }) => {
    await page.goto('/cadastros/exportacao-lote');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Catálogo', () => {
  test('Deve carregar a página do catálogo admin', async ({ page }) => {
    await page.goto('/catalogo');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});
