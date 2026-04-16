import { test as setup, expect, type Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authDir = path.join(__dirname, '.auth');
const authFiles = {
  user: path.join(authDir, 'user.json'),
  admin: path.join(authDir, 'admin.json'),
  restricted: path.join(authDir, 'restricted.json'),
};

type LoginProfile = {
  name: string;
  email: string;
  password: string;
  requiredRole: string;
  outputFiles: string[];
};

type AuthPayload = {
  token?: string;
  user?: unknown;
  data?: {
    token?: string;
    user?: unknown;
  };
};

type AuthUser = {
  email?: string;
  tenant_id?: number | null;
  current_tenant_id?: number | null;
  roles?: unknown[];
  tenant?: AuthTenant | null;
};

type AuthTenant = {
  id?: number | null;
  name?: string | null;
};

type MePayload = {
  user?: AuthUser;
  tenant?: AuthTenant | null;
  data?: {
    user?: AuthUser;
    tenant?: AuthTenant | null;
  } & AuthUser;
};

function authPayload(body: AuthPayload): { token: string | undefined; user: unknown } {
  return {
    token: body.token ?? body.data?.token,
    user: body.user ?? body.data?.user ?? null,
  };
}

function normalizeRoleName(role: unknown): string | null {
  if (typeof role === 'string') return role;
  if (role && typeof role === 'object' && 'name' in role && typeof role.name === 'string') {
    return role.name;
  }

  return null;
}

function profileFromMePayload(body: MePayload): { user: AuthUser; tenant: AuthTenant | null; roles: string[] } {
  const data = body.data ?? {};
  const user = data.user ?? body.user ?? data;
  const tenant = data.tenant ?? body.tenant ?? user.tenant ?? null;
  const roles = Array.isArray(user.roles)
    ? user.roles.map(normalizeRoleName).filter((role): role is string => role !== null)
    : [];

  return { user, tenant, roles };
}

async function assertInvalidLoginRejected(page: Page, apiBase: string): Promise<void> {
  const response = await page.request.post(`${apiBase}/login`, {
    data: { email: 'invalid-e2e-user@sistema.local', password: 'invalid-password' },
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  });

  expect(response.status(), await response.text()).not.toBe(200);
}

async function persistProfile(page: Page, profile: LoginProfile, apiBase: string): Promise<void> {
  const apiResponse = await page.request.post(`${apiBase}/login`, {
    data: { email: profile.email, password: profile.password },
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  });

  const status = apiResponse.status();
  const text = await apiResponse.text();

  if (status !== 200) {
    throw new Error(
      `E2E setup: login ${profile.name} falhou com status ${status}. ` +
      `Resposta: ${text.substring(0, 500)}. ` +
      `Verifique ${apiBase} e as credenciais ${profile.email}.`
    );
  }

  const { token, user } = authPayload(JSON.parse(text) as AuthPayload);
  expect(token, `Token de autenticação ausente para ${profile.name}`).toBeTruthy();

  const meResponse = await page.request.get(`${apiBase}/me`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  });
  const meText = await meResponse.text();
  expect(meResponse.status(), meText).toBe(200);

  const { user: meUser, tenant, roles } = profileFromMePayload(JSON.parse(meText) as MePayload);
  const tenantId = meUser.current_tenant_id ?? meUser.tenant_id ?? tenant?.id ?? null;
  expect(meUser.email, `E2E setup autenticou principal inesperado para ${profile.name}`).toBe(profile.email);
  expect(tenantId, `E2E setup sem tenant para ${profile.name}`).not.toBeNull();
  expect(roles, `E2E setup perfil ${profile.name} sem role ${profile.requiredRole}`).toContain(profile.requiredRole);

  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.evaluate(({ authToken, userData }) => {
    localStorage.clear();
    localStorage.setItem('auth_token', authToken);
    localStorage.setItem('auth-store', JSON.stringify({
      state: {
        user: userData,
        token: authToken,
        isAuthenticated: true,
        isLoading: false,
        tenant: (userData as { tenant?: unknown } | null)?.tenant ?? null,
      },
      version: 0,
    }));
    localStorage.setItem('kalibrium-mode-selected', 'remembered');
    localStorage.setItem('kalibrium-mode', 'gestao');
    localStorage.setItem('kalibrium-onboarding-done', JSON.stringify({ gestao: true, tecnico: true, vendedor: true }));
  }, { authToken: token, userData: meUser });

  for (const outputFile of profile.outputFiles) {
    await page.context().storageState({ path: outputFile });
  }
}

setup('autenticar perfis E2E', async ({ page }) => {
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const apiBase = process.env.E2E_API_BASE || 'http://127.0.0.1:8010/api/v1';
  await assertInvalidLoginRejected(page, apiBase);

  const profiles: LoginProfile[] = [
    {
      name: 'admin',
      email: process.env.E2E_EMAIL ?? 'admin@example.test',
      password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD',
      requiredRole: 'super_admin',
      outputFiles: [authFiles.user, authFiles.admin],
    },
    {
      name: 'restricted',
      email: process.env.E2E_RESTRICTED_EMAIL || 'ricardo@techassist.com.br',
      password: process.env.E2E_RESTRICTED_PASSWORD ?? 'CHANGE_ME_E2E_RESTRICTED_PASSWORD',
      requiredRole: 'tecnico',
      outputFiles: [authFiles.restricted],
    },
  ];

  for (const profile of profiles) {
    await persistProfile(page, profile, apiBase);
  }
});
