import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/LoginPage';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE AUTENTICAÇÃO E SEGURANÇA                         ║
// ║  Aqui o robô tenta invadir e quebrar a tela de login.       ║
// ║  Se o sistema aguentar, significa que está seguro.           ║
// ╚═══════════════════════════════════════════════════════════════╝

// Desliga a sessão salva — o robô precisa estar DESLOGADO para testar o login.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Testes da Tela de Login', () => {
  let loginPage: LoginPage;

  // Antes de cada teste, o robô abre o navegador e vai para /login
  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
    await loginPage.goto();
  });

  // ─── TESTE 1: Área restrita bloqueada ───
  test('Deve bloquear acesso ao painel sem estar logado', async ({ page }) => {
    // O robô tenta acessar /dashboard diretamente pela URL (como um hacker faria)
    await page.goto('/dashboard');

    // O sistema deve forçar ele de volta para a tela de login
    await expect(page).toHaveURL(/.*login/);
  });

  // ─── TESTE 2: Senha errada ───
  test('Deve mostrar erro ao digitar e-mail e senha incorretos', async ({ page }) => {
    // O robô digita credenciais inventadas
    await page.locator('#email').fill('hacker@email.com');
    await page.locator('#password').fill('senhaerrada123');
    await page.locator('button[type="submit"]').click();

    // Espera o botão voltar do estado "Entrando..." para "Entrar" (sinal de que a API respondeu)
    // OU a mensagem de erro aparecer — o que vier primeiro
    await Promise.race([
      page.getByText(/credenciais|erro|inválid|incorret|falh/i).first().waitFor({ state: 'visible', timeout: 10000 }).catch(() => {}),
      page.locator('button[type="submit"]:not(:disabled)').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {}),
    ]);

    // O importante: deve continuar na tela de login (não logou)
    await expect(page).toHaveURL(/.*login/);
  });

  // ─── TESTES 3 a 12: E-mails malucos (10 variações automáticas) ───
  // O robô tenta logar com 10 formatos de e-mail bizarros.
  // O navegador Chrome bloqueia automaticamente (campo type="email").
  const emailsInvalidos = [
    'admin@kalibrium',            // falta o .com
    'plainaddress',                // nem parece um e-mail
    '#@%^%#$@#$@#.com',          // caracteres especiais
    '@example.com',                // falta o nome antes do @
    'email.example.com',           // falta o @
    'email@example@example.com',   // dois @
    '.email@example.com',          // começa com ponto
    'email.@example.com',          // ponto antes do @
    'email..email@example.com',    // dois pontos seguidos
    ' ',                           // espaço em branco
  ];

  for (const email of emailsInvalidos) {
    test(`Deve rejeitar o formato de e-mail inválido: "${email}"`, async ({ page }) => {
      // O robô preenche o campo de e-mail com o valor maluco
      await page.locator('#email').fill(email);
      await page.locator('#password').fill('qualquersenha');
      await page.getByRole('button', { name: /entrar/i }).click();

      // O Chrome bloqueia a submissão. O robô confirma que NÃO saiu da tela de login.
      await expect(page).toHaveURL(/.*login/);
    });
  }
});
