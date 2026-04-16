import { expect, type Page } from '@playwright/test'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { mkdir, open, readFile, rm, writeFile } from 'node:fs/promises'

const BASE = process.env.E2E_FRONTEND_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:3010'
const API_BASE = process.env.E2E_API_BASE || 'http://127.0.0.1:8010/api/v1'
const MODE_SELECTION_KEY = 'kalibrium-mode-selected'
const MODE_STORAGE_KEY = 'kalibrium-mode'
const ONBOARDING_KEY = 'kalibrium-onboarding-done'
const currentDir = dirname(fileURLToPath(import.meta.url))
const authDir = resolve(currentDir, '.auth')
const authFiles = [
    resolve(authDir, 'user.json'),
    resolve(authDir, 'admin.json'),
]
const authLockFile = resolve(authDir, 'admin-login.lock')

interface E2ETenant {
    id: number
    name: string
    document: string | null
    email: string | null
    phone: string | null
    status: string
}

interface E2EUser {
    id: number
    name: string
    email: string
    phone: string | null
    tenant_id: number | null
    permissions: string[]
    roles: string[]
    role_details?: Array<{ name: string; display_name: string }>
    all_permissions?: string[]
    all_roles?: string[]
    tenant?: E2ETenant | null
}

interface E2EAuthSession {
    token: string
    user: E2EUser
    tenant: E2ETenant | null
}

interface LoginAsAdminOptions {
    navigateToApp?: boolean
}

interface OriginStorageEntry {
    name: string
    value: string
}

interface BrowserStorageState {
    cookies: unknown[]
    origins: Array<{
        origin: string
        localStorage: OriginStorageEntry[]
    }>
}

let cachedSession: E2EAuthSession | null = null

function authStoreValue(session: E2EAuthSession): string {
    return JSON.stringify({
        state: {
            token: session.token,
            isAuthenticated: true,
            isLoading: false,
            user: session.user,
            tenant: session.tenant,
        },
        version: 0,
    })
}

function buildStorageState(session: E2EAuthSession): BrowserStorageState {
    return {
        cookies: [],
        origins: [{
            origin: new URL(BASE).origin,
            localStorage: [
                { name: 'auth_token', value: session.token },
                { name: 'auth-store', value: authStoreValue(session) },
                { name: MODE_SELECTION_KEY, value: 'remembered' },
                { name: MODE_STORAGE_KEY, value: 'gestao' },
                { name: ONBOARDING_KEY, value: JSON.stringify({ gestao: true, tecnico: true, vendedor: true }) },
            ],
        }],
    }
}

async function persistAuthFiles(session: E2EAuthSession): Promise<void> {
    await mkdir(authDir, { recursive: true })
    const state = `${JSON.stringify(buildStorageState(session), null, 2)}\n`
    await Promise.all(authFiles.map((authFile) => writeFile(authFile, state, 'utf8')))
}

async function withAuthRefreshLock<T>(action: () => Promise<T>): Promise<T> {
    await mkdir(authDir, { recursive: true })
    const startedAt = Date.now()

    while (true) {
        try {
            const handle = await open(authLockFile, 'wx')
            try {
                return await action()
            } finally {
                await handle.close()
                await rm(authLockFile, { force: true })
            }
        } catch (error) {
            const code = (error as NodeJS.ErrnoException).code
            if (code !== 'EEXIST') {
                throw error
            }

            if (Date.now() - startedAt > 30_000) {
                throw new Error('Timeout ao aguardar lock de renovacao de sessao admin E2E.')
            }

            await new Promise(resolvePromise => setTimeout(resolvePromise, 250))
        }
    }
}

function normalizeUser(rawUser: Partial<E2EUser> & Record<string, unknown>): E2EUser {
    const permissions = Array.isArray(rawUser.permissions)
        ? (rawUser.permissions as string[])
        : (Array.isArray(rawUser.all_permissions) ? (rawUser.all_permissions as string[]) : [])

    const roles = Array.isArray(rawUser.roles)
        ? (rawUser.roles as string[])
        : (Array.isArray(rawUser.all_roles) ? (rawUser.all_roles as string[]) : [])

    const tenant = rawUser.tenant && typeof rawUser.tenant === 'object'
        ? (rawUser.tenant as E2ETenant)
        : null

    return {
        id: Number(rawUser.id) || 0,
        name: String(rawUser.name || ''),
        email: String(rawUser.email || ''),
        phone: rawUser.phone ? String(rawUser.phone) : null,
        tenant_id: tenant?.id ?? (rawUser.tenant_id ? Number(rawUser.tenant_id) : null),
        permissions,
        roles,
        role_details: Array.isArray(rawUser.role_details)
            ? (rawUser.role_details as Array<{ name: string; display_name: string }>)
            : [],
        all_permissions: permissions,
        all_roles: roles,
        tenant,
    }
}

async function persistSession(page: Page, session: E2EAuthSession): Promise<void> {
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
    await page.evaluate(({ authSession, modeSelectionKey, modeStorageKey, onboardingKey }) => {
        localStorage.clear()
        localStorage.setItem('auth_token', authSession.token)
        localStorage.setItem('auth-store', JSON.stringify({
            state: {
                token: authSession.token,
                isAuthenticated: true,
                isLoading: false,
                user: authSession.user,
                tenant: authSession.tenant,
            },
            version: 0,
        }))
        localStorage.setItem(modeSelectionKey, 'remembered')
        localStorage.setItem(modeStorageKey, 'gestao')
        localStorage.setItem(onboardingKey, JSON.stringify({ gestao: true, tecnico: true, vendedor: true }))
    }, {
        authSession: session,
        modeSelectionKey: MODE_SELECTION_KEY,
        modeStorageKey: MODE_STORAGE_KEY,
        onboardingKey: ONBOARDING_KEY,
    })
}

async function buildSession(page: Page, token: string): Promise<E2EAuthSession | null> {
    const meResponse = await page.request.get(`${API_BASE}/me`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    })

    if (!meResponse.ok()) {
        return null
    }

    const meBody = await meResponse.json()
    const payload = meBody.data ?? meBody
    const user = normalizeUser((payload.user ?? payload) as Partial<E2EUser> & Record<string, unknown>)

    return {
        token,
        user,
        tenant: user.tenant ?? null,
    }
}

async function readStoredToken(page: Page): Promise<string | null> {
    const expectedOrigin = new URL(BASE).origin
    const storageState = await page.context().storageState().catch(() => null)
    const originStorage = storageState?.origins.find((origin) => origin.origin === expectedOrigin)
    const localStorage = originStorage?.localStorage ?? []

    const directToken = localStorage.find((entry) => entry.name === 'auth_token')?.value
    if (directToken) return directToken

    const persistedStore = localStorage.find((entry) => entry.name === 'auth-store')?.value
    if (!persistedStore) return null

    try {
        const parsed = JSON.parse(persistedStore) as { state?: { token?: unknown } }
        return typeof parsed.state?.token === 'string' ? parsed.state.token : null
    } catch {
        return null
    }
}

function extractTokenFromStorageState(storageState: BrowserStorageState | null): string | null {
    const expectedOrigin = new URL(BASE).origin
    const originStorage = storageState?.origins.find((origin) => origin.origin === expectedOrigin)
    const localStorage = originStorage?.localStorage ?? []

    const directToken = localStorage.find((entry) => entry.name === 'auth_token')?.value
    if (directToken) return directToken

    const persistedStore = localStorage.find((entry) => entry.name === 'auth-store')?.value
    if (!persistedStore) return null

    try {
        const parsed = JSON.parse(persistedStore) as { state?: { token?: unknown } }
        return typeof parsed.state?.token === 'string' ? parsed.state.token : null
    } catch {
        return null
    }
}

async function readTokenFromAuthFile(): Promise<string | null> {
    for (const authFile of authFiles) {
        try {
            const content = await readFile(authFile, 'utf8')
            const token = extractTokenFromStorageState(JSON.parse(content) as BrowserStorageState)
            if (token) return token
        } catch {
            // Arquivo ausente ou invalido: o login abaixo recria o storageState.
        }
    }

    return null
}

async function tryApplyToken(page: Page, token: string, options: LoginAsAdminOptions): Promise<boolean> {
    const session = await buildSession(page, token)
    if (!session) return false

    cachedSession = session
    await applySession(page, session, options)
    return true
}

async function useContextToken(page: Page, token: string, options: LoginAsAdminOptions): Promise<boolean> {
    const session = await buildSession(page, token)
    if (!session) return false

    cachedSession = session

    if (options.navigateToApp !== false) {
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
    }

    return true
}

export async function waitForAppReady(page: Page): Promise<void> {
    await page.waitForLoadState('domcontentloaded')
    await expect(page.locator('body')).toBeVisible({ timeout: 10000 })

    const busyIndicators = page.locator(
        '[aria-busy="true"], [role="progressbar"], [class*="skeleton"], [class*="Skeleton"], .animate-spin.rounded-full.border-t-transparent'
    )
    const busyCount = await busyIndicators.count()

    for (let index = 0; index < busyCount; index++) {
        await busyIndicators.nth(index).waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
    }
}

async function applySession(page: Page, session: E2EAuthSession, options: LoginAsAdminOptions): Promise<void> {
    await persistSession(page, session)

    if (options.navigateToApp === false) {
        return
    }

    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

export async function loginAsAdmin(page: Page, options: LoginAsAdminOptions = {}): Promise<boolean> {
    const storedToken = await readStoredToken(page)
    if (storedToken) {
        if (await useContextToken(page, storedToken, options)) {
            return true
        }
    }

    if (cachedSession) {
        if (await tryApplyToken(page, cachedSession.token, options)) {
            return true
        }

        cachedSession = null
    }

    const fileToken = await readTokenFromAuthFile()
    if (fileToken && await tryApplyToken(page, fileToken, options)) {
        return true
    }

    return withAuthRefreshLock(async () => {
        const refreshedFileToken = await readTokenFromAuthFile()
        if (refreshedFileToken && await tryApplyToken(page, refreshedFileToken, options)) {
            return true
        }

        const credentials = [
            { email: 'admin@example.test', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
            { email: 'admin@sistema.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
        ]
        const failures: string[] = []

        for (const credential of credentials) {
            try {
                const response = await page.request.post(`${API_BASE}/login`, { data: credential })
                if (!response.ok()) {
                    const body = await response.text().catch(() => '')
                    failures.push(`${credential.email}: HTTP ${response.status()} ${body.slice(0, 180)}`)
                    continue
                }

                const body = await response.json()
                const token = body.token || body.data?.token
                if (!token) {
                    failures.push(`${credential.email}: resposta sem token`)
                    continue
                }

                const session = await buildSession(page, token)
                if (!session) {
                    failures.push(`${credential.email}: /me nao retornou sessao valida`)
                    continue
                }

                cachedSession = session
                await persistAuthFiles(session)
                await applySession(page, session, options)
                return true
            } catch (error) {
                const message = error instanceof Error ? error.message : String(error)
                failures.push(`${credential.email}: ${message}`)
            }
        }

        throw new Error(`Login E2E de administrador falhou em ${API_BASE}. ${failures.join(' | ')}`)
    })
}

export async function gotoAuthenticated(page: Page, path: string): Promise<void> {
    const target = path.startsWith('http') ? path : BASE + path
    let lastError: unknown

    for (let attempt = 0; attempt < 2; attempt++) {
        if (attempt > 0) {
            await loginAsAdmin(page, { navigateToApp: false })
        }

        try {
            await page.goto(target, { waitUntil: 'domcontentloaded' })
            await waitForAppReady(page)

            if (/\/login(?:$|\?)/.test(page.url())) {
                cachedSession = null
                lastError = new Error(`Navegacao autenticada caiu para login em ${path}.`)
                continue
            }

            await expect(page.locator('body')).toBeVisible({ timeout: 10000 })
            return
        } catch (error) {
            cachedSession = null
            lastError = error
        }
    }

    const message = lastError instanceof Error ? lastError.message : String(lastError)
    throw new Error(`Falha ao navegar autenticado para ${path}: ${message}`)
}

export { BASE, API_BASE }
