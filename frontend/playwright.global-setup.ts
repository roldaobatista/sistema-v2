import { execFileSync } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { existsSync } from 'node:fs'
import { open, rm } from 'node:fs/promises'
import type { FullConfig } from '@playwright/test'

type SeedCommandError = Error & {
    status?: number | null
    signal?: NodeJS.Signals | null
    stdout?: string | Buffer
    stderr?: string | Buffer
}

const SENSITIVE_ENV_NAME_PATTERN = /(PASSWORD|TOKEN|SECRET|KEY|CREDENTIAL|AUTH)/i
const MAX_SEED_OUTPUT_LENGTH = 6000

function isLocalApi(apiBase: string): boolean {
    return apiBase.includes('127.0.0.1') || apiBase.includes('localhost')
}

async function wait(ms: number): Promise<void> {
    await new Promise((resolvePromise) => setTimeout(resolvePromise, ms))
}

async function withSeedLock<T>(lockFile: string, action: () => Promise<T>): Promise<T> {
    const start = Date.now()

    while (true) {
        try {
            const handle = await open(lockFile, 'wx')
            try {
                return await action()
            } finally {
                await handle.close()
                await rm(lockFile, { force: true })
            }
        } catch (error) {
            const code = (error as NodeJS.ErrnoException).code
            if (code !== 'EEXIST') {
                throw error
            }

            if (Date.now() - start > 120_000) {
                throw new Error('Timeout ao aguardar lock de seed do Playwright.')
            }

            await wait(1000)
        }
    }
}

function seedCommandFailed(error: unknown): error is SeedCommandError {
    return error instanceof Error
}

function normalizeCommandOutput(output: string | Buffer | undefined): string {
    if (!output) {
        return ''
    }

    return Buffer.isBuffer(output) ? output.toString('utf8') : output
}

function truncateCommandOutput(output: string): string {
    if (output.length <= MAX_SEED_OUTPUT_LENGTH) {
        return output
    }

    return `${output.slice(0, MAX_SEED_OUTPUT_LENGTH)}\n[output truncado em ${MAX_SEED_OUTPUT_LENGTH} caracteres]`
}

function redactSensitiveValues(output: string): string {
    const sensitiveValues = Object.entries(process.env)
        .filter(([key, value]) => SENSITIVE_ENV_NAME_PATTERN.test(key) && typeof value === 'string' && value.length >= 8)
        .map(([, value]) => value as string)

    return [...new Set(sensitiveValues)].reduce((redactedOutput, value) => redactedOutput.split(value).join('[masked]'), output)
}

function formatCommandOutput(label: string, output: string | Buffer | undefined): string | null {
    const normalizedOutput = normalizeCommandOutput(output).trim()
    if (!normalizedOutput) {
        return null
    }

    return `${label}:\n${truncateCommandOutput(redactSensitiveValues(normalizedOutput))}`
}

function getSeedErrorMessage(error: unknown): string {
    if (!seedCommandFailed(error)) {
        return 'erro desconhecido ao seedar ambiente E2E'
    }

    const details = [
        `status=${error.status ?? 'unknown'}`,
        `signal=${error.signal ?? 'none'}`,
        formatCommandOutput('message', error.message),
        formatCommandOutput('stdout', error.stdout),
        formatCommandOutput('stderr', error.stderr),
    ].filter((detail): detail is string => detail !== null)

    return details.join('\n')
}

function resolvePhpBinary(): string {
    const localPhp84 = process.env.USERPROFILE
        ? resolve(process.env.USERPROFILE, 'AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe')
        : ''

    return process.env.E2E_PHP_BIN || (localPhp84 && existsSync(localPhp84) ? localPhp84 : 'php')
}

export default async function globalSetup(_config: FullConfig): Promise<void> {
    const apiBase = process.env.E2E_API_BASE || 'http://127.0.0.1:8000/api/v1'
    if (!isLocalApi(apiBase)) {
        return
    }

    const currentDir = dirname(fileURLToPath(import.meta.url))
    const backendDir = resolve(currentDir, '../backend')
    const lockFile = resolve(currentDir, '.playwright-seed.lock')
    const phpBinary = resolvePhpBinary()
    const commonOptions = {
        cwd: backendDir,
        stdio: 'pipe' as const,
        encoding: 'utf8' as const,
        maxBuffer: 10 * 1024 * 1024,
    }

    await withSeedLock(lockFile, async () => {
        try {
            execFileSync(phpBinary, ['artisan', 'db:seed', '--class=PermissionsSeeder', '--force'], commonOptions)
            execFileSync(phpBinary, ['artisan', 'db:seed', '--class=CreateAdminUserSeeder', '--force'], commonOptions)
            execFileSync(phpBinary, ['artisan', 'db:seed', '--class=CrmSeeder', '--force'], commonOptions)
            execFileSync(phpBinary, ['artisan', 'db:seed', '--class=E2eReferenceSeeder', '--force'], commonOptions)
        } catch (error) {
            throw new Error(`[playwright.global-setup] Seed local falhou; E2E bloqueado para evitar skips falsos. ${getSeedErrorMessage(error)}`)
        }
    })
}
