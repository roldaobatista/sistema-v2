import { readFileSync } from 'node:fs'
import ts from 'typescript'
import { describe, expect, it } from 'vitest'

type UnsafeWindowOpenCall = {
    filePath: string
    line: number
    text: string
}

type UnsafeBlankTarget = {
    filePath: string
    line: number
    text: string
}

const pageFiles = [
    '../../pages/agenda/AgendaPage.tsx',
    '../../pages/cadastros/Customer360Page.tsx',
    '../../pages/fiscal/FiscalNotesPage.tsx',
    '../../components/os/AdminChatTab.tsx',
    '../../components/tech/TechChatDrawer.tsx',
    '../../pages/tech/TechChatPage.tsx',
]

function readFixture(relativePath: string): { filePath: string; source: string } {
    const fileUrl = new URL(relativePath, import.meta.url)
    return {
        filePath: fileUrl.pathname,
        source: readFileSync(fileUrl, 'utf8'),
    }
}

function isWindowOpenCall(node: ts.CallExpression, sourceFile: ts.SourceFile): boolean {
    if (!ts.isPropertyAccessExpression(node.expression)) {
        return false
    }

    return node.expression.name.text === 'open'
        && node.expression.expression.getText(sourceFile) === 'window'
}

function isBlankTarget(argument: ts.Expression | undefined): boolean {
    return ts.isStringLiteralLike(argument) && argument.text === '_blank'
}

function hasNoopenerNoreferrer(argument: ts.Expression | undefined, sourceFile: ts.SourceFile): boolean {
    if (!argument) {
        return false
    }

    const features = argument.getText(sourceFile).toLowerCase()
    return features.includes('noopener') && features.includes('noreferrer')
}

function findUnsafeWindowOpenCalls(filePath: string, source: string): UnsafeWindowOpenCall[] {
    const sourceFile = ts.createSourceFile(filePath, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)
    const unsafeCalls: UnsafeWindowOpenCall[] = []

    function visit(node: ts.Node) {
        if (ts.isCallExpression(node)
            && isWindowOpenCall(node, sourceFile)
            && isBlankTarget(node.arguments[1])
            && !hasNoopenerNoreferrer(node.arguments[2], sourceFile)) {
            const position = sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile))
            unsafeCalls.push({
                filePath,
                line: position.line + 1,
                text: node.getText(sourceFile),
            })
        }

        ts.forEachChild(node, visit)
    }

    visit(sourceFile)
    return unsafeCalls
}

function getJsxAttribute(node: ts.JsxOpeningElement, name: string): ts.JsxAttribute | undefined {
    return node.attributes.properties.find((property): property is ts.JsxAttribute => {
        return ts.isJsxAttribute(property) && property.name.text === name
    })
}

function getStaticJsxAttributeText(attribute: ts.JsxAttribute | undefined): string | null {
    if (!attribute?.initializer) {
        return ''
    }

    if (ts.isStringLiteral(attribute.initializer)) {
        return attribute.initializer.text
    }

    if (ts.isJsxExpression(attribute.initializer)
        && attribute.initializer.expression
        && ts.isStringLiteralLike(attribute.initializer.expression)) {
        return attribute.initializer.expression.text
    }

    return null
}

function findUnsafeBlankTargets(filePath: string, source: string): UnsafeBlankTarget[] {
    const sourceFile = ts.createSourceFile(filePath, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)
    const unsafeTargets: UnsafeBlankTarget[] = []

    function visit(node: ts.Node) {
        if (ts.isJsxOpeningElement(node)
            && ts.isIdentifier(node.tagName)
            && node.tagName.text === 'a') {
            const target = getStaticJsxAttributeText(getJsxAttribute(node, 'target'))
            const rel = getStaticJsxAttributeText(getJsxAttribute(node, 'rel'))?.toLowerCase() ?? ''

            if (target === '_blank' && (!rel.includes('noopener') || !rel.includes('noreferrer'))) {
                const position = sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile))
                unsafeTargets.push({
                    filePath,
                    line: position.line + 1,
                    text: node.getText(sourceFile),
                })
            }
        }

        ts.forEachChild(node, visit)
    }

    visit(sourceFile)
    return unsafeTargets
}

describe('regressões de segurança frontend da Camada 3', () => {
    it('abre janelas externas com noopener e noreferrer nas telas auditadas', () => {
        const unsafeCalls = pageFiles.flatMap((relativePath) => {
            const { filePath, source } = readFixture(relativePath)
            return findUnsafeWindowOpenCalls(filePath, source)
        })

        expect(unsafeCalls).toEqual([])
    })

    it('abre links target blank com rel noopener e noreferrer nas telas auditadas', () => {
        const unsafeTargets = pageFiles.flatMap((relativePath) => {
            const { filePath, source } = readFixture(relativePath)
            return findUnsafeBlankTargets(filePath, source)
        })

        expect(unsafeTargets).toEqual([])
    })

    it('não mantém cache de endpoints autenticados no service worker', () => {
        const { source } = readFixture('../../../public/sw.js')

        expect(source).toContain("const AUTHENTICATED_API_CACHE_DISABLED = true")
        expect(source).toContain('networkOnlyApi(event.request)')
        expect(source).toContain("key.startsWith('kalibrium-api-')")

        const prefetchHandler = source.match(/if \(type === 'CACHE_API_DATA'\) \{[\s\S]*?\n {2}\}/)?.[0] ?? ''

        expect(prefetchHandler).toContain('authenticated-api-cache-disabled')
        expect(prefetchHandler).not.toContain('fetch(')
        expect(prefetchHandler).not.toContain('cache.put')
    })

    it('limpa o cache global do React Query nas bordas de autenticação e tenant', () => {
        const queryClient = readFixture('../../lib/query-client.ts').source
        const authStore = readFixture('../../stores/auth-store.ts').source
        const portalAuthStore = readFixture('../../stores/portal-auth-store.ts').source
        const currentTenant = readFixture('../../hooks/useCurrentTenant.ts').source

        expect(queryClient).toContain('export function clearAuthenticatedQueryCache(options: ClearAuthenticatedQueryCacheOptions = {})')
        expect(queryClient).toContain('queryClient.clear()')
        expect(queryClient).toContain('if (options.broadcast)')
        expect(authStore).toContain("import { clearAuthenticatedQueryCache } from '@/lib/query-client'")
        expect(portalAuthStore).toContain("import { clearAuthenticatedQueryCache } from '@/lib/query-client'")
        expect(currentTenant).toContain("import { clearAuthenticatedQueryCache } from '@/lib/query-client'")
        expect(authStore.match(/clearAuthenticatedQueryCache\(\)/g)?.length ?? 0).toBeGreaterThanOrEqual(3)
        expect(portalAuthStore.match(/clearAuthenticatedQueryCache\(\)/g)?.length ?? 0).toBeGreaterThanOrEqual(3)
        expect(currentTenant.match(/clearAuthenticatedQueryCache\(\)/g)?.length ?? 0).toBeGreaterThanOrEqual(1)
    })

    it('derruba estado autenticado e tenant nas outras abas quando o cache autenticado é limpo', () => {
        const crossTab = readFixture('../../lib/cross-tab-sync.ts').source
        const authStore = readFixture('../../stores/auth-store.ts').source
        const portalAuthStore = readFixture('../../stores/portal-auth-store.ts').source

        expect(crossTab).toContain('export function subscribeAuthenticatedCacheClear')
        expect(crossTab).toContain("export type AuthenticatedCacheScope = 'admin' | 'portal' | 'all'")
        expect(crossTab).toContain('clearQueriesForScope(queryClient, scope)')
        expect(crossTab).toContain('notifyAuthenticatedCacheClearListeners(scope)')
        expect(authStore).toContain("subscribeAuthenticatedCacheClear((scope) => {")
        expect(authStore).toContain("scope !== 'admin' && scope !== 'all'")
        expect(portalAuthStore).toContain("subscribeAuthenticatedCacheClear((scope) => {")
        expect(portalAuthStore).toContain("scope !== 'portal' && scope !== 'all'")
        expect(authStore).toContain('useAuthStore.setState({')
        expect(authStore).toContain('tenant: null')
        expect(portalAuthStore).toContain('usePortalAuthStore.setState({')
        expect(portalAuthStore).toContain('user: null')
    })
})
