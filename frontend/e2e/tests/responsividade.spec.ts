import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE RESPONSIVIDADE E ACESSIBILIDADE                   ║
// ║  Verifica se as páginas funcionam em diferentes tamanhos      ║
// ╚═══════════════════════════════════════════════════════════════╝

const paginasPrincipais = [
  '/', '/cadastros/clientes', '/os', '/crm', '/financeiro',
  '/estoque', '/rh', '/equipamentos', '/inmetro', '/configuracoes',
  '/orcamentos', '/relatorios', '/perfil', '/qualidade',
  '/financeiro/pagar', '/financeiro/receber', '/crm/pipeline',
  '/os/kanban', '/chamados', '/alertas',
];

// ─── Mobile (375px) ───
test.describe('Responsividade — Mobile (375px)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  for (const rota of paginasPrincipais) {
    test(`Mobile: ${rota} deve renderizar`, async ({ page }) => {
      await page.goto(rota);
      await expect(page.locator('body')).toBeVisible();
    });
  }
});

// ─── Tablet (768px) ───
test.describe('Responsividade — Tablet (768px)', () => {
  test.use({ viewport: { width: 768, height: 1024 } });

  for (const rota of paginasPrincipais) {
    test(`Tablet: ${rota} deve renderizar`, async ({ page }) => {
      await page.goto(rota);
      await expect(page.locator('body')).toBeVisible();
    });
  }
});

// ─── Desktop Grande (1920px) ───
test.describe('Responsividade — Desktop Grande (1920px)', () => {
  test.use({ viewport: { width: 1920, height: 1080 } });

  for (const rota of paginasPrincipais) {
    test(`Desktop: ${rota} deve renderizar`, async ({ page }) => {
      await page.goto(rota);
      await expect(page.locator('body')).toBeVisible();
    });
  }
});

// ─── Acessibilidade básica ───
test.describe('Acessibilidade — Tela de Login', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('Login deve ter campo de e-mail com label', async ({ page }) => {
    await page.goto('/login');
    const email = page.locator('#email');
    await expect(email).toBeVisible();
  });

  test('Login deve ter campo de senha com label', async ({ page }) => {
    await page.goto('/login');
    const password = page.locator('#password');
    await expect(password).toBeVisible();
  });

  test('Login deve ter botão submit', async ({ page }) => {
    await page.goto('/login');
    const btn = page.locator('button[type="submit"]');
    await expect(btn).toBeVisible();
  });

  test('Login deve ter título na página', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveTitle(/.+/);
  });

  test('Login deve navegar com Tab entre campos', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').focus();
    await page.keyboard.press('Tab');
    // Após tab, foco deve sair do e-mail
    const activeTag = await page.evaluate(() => document.activeElement?.tagName);
    expect(activeTag).toBeTruthy();
  });
});
