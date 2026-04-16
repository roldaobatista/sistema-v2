import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const srcRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

function readSource(relativeFromSrc: string): string {
    return readFileSync(resolve(srcRoot, relativeFromSrc), 'utf8')
}

describe('Layer 3 error feedback and accessibility regressions', () => {
    it('uses backend API messages for targeted mutation errors', () => {
        const approvalQueue = readSource('components/journey/ApprovalQueue.tsx')
        const agendaPage = readSource('pages/agenda/AgendaPage.tsx')
        const expenseLimits = readSource('pages/configuracoes/ExpenseLimitsConfigPage.tsx')

        expect(approvalQueue).toContain("getApiErrorMessage(err, 'Erro ao aprovar')")
        expect(approvalQueue).toContain("getApiErrorMessage(err, 'Erro ao rejeitar')")
        expect(agendaPage).toContain("getApiErrorMessage(err, 'Erro ao usar template')")
        expect(agendaPage).toContain("getApiErrorMessage(err, 'Erro ao criar template')")
        expect(expenseLimits).toContain("getApiErrorMessage(err, 'Erro ao salvar limites')")

        expect(approvalQueue).not.toContain("onError: () => toast.error('Erro ao aprovar')")
        expect(approvalQueue).not.toContain("onError: () => toast.error('Erro ao rejeitar')")
        expect(agendaPage).not.toContain("onError: () => toast.error('Erro ao usar template')")
        expect(agendaPage).not.toContain("onError: () => toast.error('Erro ao criar template')")
        expect(expenseLimits).not.toContain("onError: () => toast.error('Erro ao salvar limites')")
    })

    it('keeps checklist form controls and icon-only buttons accessible', () => {
        const serviceChecklists = readSource('pages/os/ServiceChecklistsPage.tsx')

        expect(serviceChecklists).toContain('htmlFor="checklist-name"')
        expect(serviceChecklists).toContain('id="checklist-name"')
        expect(serviceChecklists).toContain('htmlFor="checklist-description"')
        expect(serviceChecklists).toContain('id="checklist-description"')
        expect(serviceChecklists).toContain('aria-label={`Descrição do item ${idx + 1}`}')
        expect(serviceChecklists).toContain('aria-label={`Tipo do item ${idx + 1}`}')
        expect(serviceChecklists).toContain('aria-label={`Remover item ${idx + 1}`}')
    })

    it('associates audit log filters with accessible names', () => {
        const auditLog = readSource('pages/admin/AuditLogPage.tsx')

        expect(auditLog).toContain('htmlFor="audit-search"')
        expect(auditLog).toContain('id="audit-search"')
        expect(auditLog).toContain('htmlFor="audit-action"')
        expect(auditLog).toContain('id="audit-action"')
        expect(auditLog).toContain('htmlFor="audit-entity-type"')
        expect(auditLog).toContain('id="audit-entity-type"')
        expect(auditLog).toContain('htmlFor="audit-from"')
        expect(auditLog).toContain('id="audit-from"')
        expect(auditLog).toContain('htmlFor="audit-to"')
        expect(auditLog).toContain('id="audit-to"')
    })

    it('keeps technician certificate server state on React Query', () => {
        const techCertificate = readSource('pages/tech/TechCertificatePage.tsx')

        expect(techCertificate).toContain('TECH_CERTIFICATE_QUERY_KEYS')
        expect(techCertificate).toContain('useQuery({')
        expect(techCertificate).not.toContain('const [equipments, setEquipments]')
        expect(techCertificate).not.toContain('const [calibrations, setCalibrations]')
        expect(techCertificate).not.toContain('const [templates, setTemplates]')
    })

    it('clears chat mutation authorization errors before retrying the work order chat', () => {
        const adminChat = readSource('components/os/AdminChatTab.tsx')
        const retryLoad = adminChat.match(/const retryLoad = async \(\) => \{[\s\S]*?\n\s{4}\}/)?.[0] ?? ''

        expect(retryLoad).toContain('sendMessageMutation.reset()')
        expect(retryLoad.indexOf('sendMessageMutation.reset()')).toBeLessThan(retryLoad.indexOf('chatQuery.refetch()'))
    })

    it('clears chat mutation authorization errors when the target work order changes', () => {
        const adminChat = readSource('components/os/AdminChatTab.tsx')
        const workOrderResetEffect = adminChat.match(
            /useEffect\(\(\) => \{\n\s{8}markAsReadForWorkOrderRef\.current = null[\s\S]*?\n\s{4}\}, \[workOrderId\]\)/
        )?.[0] ?? ''

        expect(workOrderResetEffect).toContain('sendMessageMutation.reset()')
        expect(workOrderResetEffect).toContain('markAsReadMutation.reset()')
    })
})
