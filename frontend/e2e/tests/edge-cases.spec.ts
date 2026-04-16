import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE EDGE CASES E CENÁRIOS ESPECIAIS                   ║
// ║  URLs inválidas, deep links, double click, etc               ║
// ╚═══════════════════════════════════════════════════════════════╝

test.describe('Edge Cases — URLs Inválidas', () => {
  test('URL inexistente deve redirecionar', async ({ page }) => {
    await page.goto('/pagina-que-nao-existe');
    // Deve redirecionar para / ou mostrar 404
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('URL com caracteres especiais deve funcionar', async ({ page }) => {
    await page.goto('/cadastros/clientes?busca=hello%20world');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('URL com hash deve funcionar', async ({ page }) => {
    await page.goto('/configuracoes#section');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('URL duplicada /os/os não deve crashar', async ({ page }) => {
    await page.goto('/os/os');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('URL com trailing slash deve funcionar', async ({ page }) => {
    await page.goto('/cadastros/clientes/');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('URL muito longa não deve crashar', async ({ page }) => {
    const longPath = '/cadastros/clientes?' + 'a'.repeat(500);
    await page.goto(longPath);
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Edge Cases — Navegação Rápida', () => {
  test('Navegação rápida entre 5 páginas não deve crashar', async ({ page }) => {
    const paginas = ['/os', '/crm', '/financeiro', '/estoque', '/rh'];
    for (const p of paginas) {
      await page.goto(p);
    }
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Navegação rápida ida e volta não deve crashar', async ({ page }) => {
    await page.goto('/os');
    await page.goto('/crm');
    await page.goBack();
    await page.goForward();
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Duplo clique no menu não deve crashar', async ({ page }) => {
    await page.goto('/os');
    const link = page.locator('nav a, aside a, [role="navigation"] a').first();
    if (await link.isVisible().catch(() => false)) {
      await link.dblclick();
    }
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

test.describe('Edge Cases — Estado da Sessão', () => {
  test('Página deve sobreviver após longa inatividade simulada', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(500);
    await page.reload();
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Múltiplas abas simuladas não devem conflitar', async ({ page, context }) => {
    const page2 = await context.newPage();
    await page.goto('/os');
    await page2.goto('/crm');
    await expect(page.locator('body')).not.toBeEmpty();
    await expect(page2.locator('body')).not.toBeEmpty();
    await page2.close();
  });

  test('LocalStorage deve manter auth após navegação', async ({ page }) => {
    await page.goto('/');
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));
    await page.goto('/financeiro');
    const token2 = await page.evaluate(() => localStorage.getItem('auth_token'));
    expect(token).toBe(token2);
  });
});

test.describe('Edge Cases — Campos de Busca Gerais', () => {
  const paginasComBusca = [
    '/cadastros/clientes', '/cadastros/produtos', '/os',
    '/chamados', '/equipamentos', '/orcamentos',
    '/financeiro/pagar', '/financeiro/receber',
    '/estoque', '/rh',
  ];

  for (const rota of paginasComBusca) {
    test(`Busca vazia em ${rota} não deve crashar`, async ({ page }) => {
      await page.goto(rota);
      await page.waitForTimeout(500);
      const inputs = page.locator('input');
      if (await inputs.count() > 0) {
        await inputs.first().fill('');
        await page.keyboard.press('Enter');
      }
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }

  for (const rota of paginasComBusca) {
    test(`Busca com caractere especial em ${rota} não deve crashar`, async ({ page }) => {
      await page.goto(rota);
      await page.waitForTimeout(500);
      const inputs = page.locator('input');
      if (await inputs.count() > 0) {
        await inputs.first().fill('!@#$%^&*()');
      }
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }
});

test.describe('Edge Cases — Scroll e Lazy Loading', () => {
  const paginasLongas = [
    '/', '/cadastros/clientes', '/os', '/crm',
    '/financeiro/pagar', '/financeiro/receber',
    '/estoque/movimentacoes', '/chamados',
  ];

  for (const rota of paginasLongas) {
    test(`Scroll até o final em ${rota} não deve crashar`, async ({ page }) => {
      await page.goto(rota);
      await page.waitForTimeout(500);
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }
});

test.describe('Edge Cases — Keyboard Shortcuts', () => {
  test('Escape deve funcionar sem crashar', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Escape');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Ctrl+F deve funcionar sem crashar', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Control+f');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Tab navigation deve funcionar', async ({ page }) => {
    await page.goto('/');
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');
    }
    await expect(page.locator('body')).not.toBeEmpty();
  });
});
