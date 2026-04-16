import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE ORDENS DE SERVIÇO (O.S.)                         ║
// ║  O robô acessa a área de O.S. e verifica se está funcional. ║
// ╚═══════════════════════════════════════════════════════════════╝

test.describe('Módulo de Ordens de Serviço', () => {

  // ─── TESTE 1: Acessar a listagem de O.S. ───
  test('Deve carregar a página de listagem de Ordens de Serviço', async ({ page }) => {
    await page.goto('/os');

    // Confirma que a URL bateu
    await expect(page).toHaveURL(/.*os/);

    // Espera o conteúdo principal carregar
    await expect(page.locator('body')).not.toBeEmpty();
  });

  // ─── TESTE 2: Verificar se a tabela ou cards carregaram ───
  test('Deve exibir conteúdo na tela de O.S.', async ({ page }) => {
    await page.goto('/os');

    // Aguarda até 10 segundos para algum elemento de conteúdo surgir
    const conteudo = page.locator('main').or(page.locator('table')).or(page.locator('[role="table"]'));
    await expect(conteudo.first()).toBeVisible({ timeout: 10000 });
  });
});
