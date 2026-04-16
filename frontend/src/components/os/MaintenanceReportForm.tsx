import { useState, useEffect } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import type { MaintenanceReport, ReplacedPart } from '@/types/work-order'
import type { MaintenanceReportPayload } from '@/lib/maintenance-report-api'

interface MaintenanceReportFormProps {
    workOrderId: number
    equipmentId: number
    report?: MaintenanceReport | null
    open: boolean
    onClose: () => void
    onSubmit: (data: MaintenanceReportPayload) => void
    isPending?: boolean
}

const SEAL_STATUS_OPTIONS = [
    { value: 'intact', label: 'Intacto' },
    { value: 'broken', label: 'Quebrado' },
    { value: 'replaced', label: 'Substituído' },
    { value: 'not_applicable', label: 'Não Aplicável' },
]

const CONDITION_BEFORE_OPTIONS = [
    { value: 'defective', label: 'Defeituoso' },
    { value: 'degraded', label: 'Degradado' },
    { value: 'functional', label: 'Funcional' },
    { value: 'unknown', label: 'Desconhecido' },
]

const CONDITION_AFTER_OPTIONS = [
    { value: 'functional', label: 'Funcional' },
    { value: 'limited', label: 'Limitado' },
    { value: 'requires_calibration', label: 'Requer Calibração' },
    { value: 'not_repaired', label: 'Não Reparado' },
]

const emptyPart: ReplacedPart = { name: '', part_number: '', origin: '', quantity: 1 }

export default function MaintenanceReportForm({
    workOrderId,
    equipmentId,
    report,
    open,
    onClose,
    onSubmit,
    isPending,
}: MaintenanceReportFormProps) {
    const [defectFound, setDefectFound] = useState('')
    const [probableCause, setProbableCause] = useState('')
    const [correctiveAction, setCorrectiveAction] = useState('')
    const [partsReplaced, setPartsReplaced] = useState<ReplacedPart[]>([])
    const [sealStatus, setSealStatus] = useState('not_applicable')
    const [newSealNumber, setNewSealNumber] = useState('')
    const [conditionBefore, setConditionBefore] = useState('unknown')
    const [conditionAfter, setConditionAfter] = useState('functional')
    const [requiresCalibrationAfter, setRequiresCalibrationAfter] = useState(false)
    const [requiresIpemVerification, setRequiresIpemVerification] = useState(false)
    const [startedAt, setStartedAt] = useState('')
    const [completedAt, setCompletedAt] = useState('')
    const [notes, setNotes] = useState('')

    useEffect(() => {
        if (report) {
            setDefectFound(report.defect_found || '')
            setProbableCause(report.probable_cause || '')
            setCorrectiveAction(report.corrective_action || '')
            setPartsReplaced(report.parts_replaced?.length ? report.parts_replaced : [])
            setSealStatus(report.seal_status || 'not_applicable')
            setNewSealNumber(report.new_seal_number || '')
            setConditionBefore(report.condition_before || 'unknown')
            setConditionAfter(report.condition_after || 'functional')
            setRequiresCalibrationAfter(report.requires_calibration_after ?? false)
            setRequiresIpemVerification(report.requires_ipem_verification ?? false)
            setStartedAt(report.started_at ? report.started_at.slice(0, 16) : '')
            setCompletedAt(report.completed_at ? report.completed_at.slice(0, 16) : '')
            setNotes(report.notes || '')
        } else {
            setDefectFound('')
            setProbableCause('')
            setCorrectiveAction('')
            setPartsReplaced([])
            setSealStatus('not_applicable')
            setNewSealNumber('')
            setConditionBefore('unknown')
            setConditionAfter('functional')
            setRequiresCalibrationAfter(false)
            setRequiresIpemVerification(false)
            setStartedAt('')
            setCompletedAt('')
            setNotes('')
        }
    }, [report, open])

    const addPart = () => setPartsReplaced([...partsReplaced, { ...emptyPart }])
    const removePart = (index: number) => setPartsReplaced(partsReplaced.filter((_, i) => i !== index))
    const updatePart = (index: number, field: keyof ReplacedPart, value: string | number) => {
        const updated = [...partsReplaced]
        updated[index] = { ...updated[index], [field]: value }
        setPartsReplaced(updated)
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        const payload: MaintenanceReportPayload = {
            work_order_id: workOrderId,
            equipment_id: equipmentId,
            defect_found: defectFound,
            probable_cause: probableCause || null,
            corrective_action: correctiveAction || null,
            parts_replaced: partsReplaced.length > 0 ? partsReplaced.filter(p => p.name.trim()) : null,
            seal_status: sealStatus,
            new_seal_number: sealStatus === 'replaced' ? newSealNumber : null,
            condition_before: conditionBefore,
            condition_after: conditionAfter,
            requires_calibration_after: requiresCalibrationAfter,
            requires_ipem_verification: requiresIpemVerification,
            started_at: startedAt || null,
            completed_at: completedAt || null,
            notes: notes || null,
        }
        onSubmit(payload)
    }

    const labelClass = "block text-sm font-medium text-surface-700 mb-1"
    const inputClass = "w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm text-surface-900 placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
    const selectClass = inputClass

    return (
        <Modal open={open} onClose={onClose} title={report ? 'Editar Relatório de Manutenção' : 'Novo Relatório de Manutenção'} size="lg">
            <form onSubmit={handleSubmit} className="space-y-5 max-h-[70vh] overflow-y-auto pr-1">
                {/* Defeito Encontrado */}
                <div>
                    <label className={labelClass}>Defeito Encontrado *</label>
                    <textarea
                        className={inputClass}
                        rows={3}
                        value={defectFound}
                        onChange={(e) => setDefectFound(e.target.value)}
                        required
                        placeholder="Descreva o defeito encontrado no equipamento"
                    />
                </div>

                {/* Causa Provável */}
                <div>
                    <label className={labelClass}>Causa Provável</label>
                    <textarea
                        className={inputClass}
                        rows={2}
                        value={probableCause}
                        onChange={(e) => setProbableCause(e.target.value)}
                        placeholder="Descreva a causa provável do defeito"
                    />
                </div>

                {/* Ação Corretiva */}
                <div>
                    <label className={labelClass}>Ação Corretiva</label>
                    <textarea
                        className={inputClass}
                        rows={2}
                        value={correctiveAction}
                        onChange={(e) => setCorrectiveAction(e.target.value)}
                        placeholder="Descreva a ação corretiva realizada"
                    />
                </div>

                {/* Condição Antes / Depois */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className={labelClass}>Condição Antes</label>
                        <select className={selectClass} value={conditionBefore} onChange={(e) => setConditionBefore(e.target.value)}>
                            {CONDITION_BEFORE_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className={labelClass}>Condição Depois</label>
                        <select className={selectClass} value={conditionAfter} onChange={(e) => setConditionAfter(e.target.value)}>
                            {CONDITION_AFTER_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Peças Substituídas */}
                <div>
                    <div className="flex items-center justify-between mb-2">
                        <label className={labelClass}>Peças Substituídas</label>
                        <Button type="button" variant="ghost" size="sm" onClick={addPart}>
                            <Plus className="h-4 w-4 mr-1" /> Adicionar Peça
                        </Button>
                    </div>
                    {partsReplaced.length === 0 && (
                        <p className="text-sm text-surface-400 italic">Nenhuma peça substituída</p>
                    )}
                    {partsReplaced.map((part, index) => (
                        <div key={index} className="grid grid-cols-12 gap-2 mb-2 items-end">
                            <div className="col-span-4">
                                {index === 0 && <label className="text-xs text-surface-500">Nome</label>}
                                <Input
                                    value={part.name}
                                    onChange={(e) => updatePart(index, 'name', e.target.value)}
                                    placeholder="Nome da peça"
                                />
                            </div>
                            <div className="col-span-3">
                                {index === 0 && <label className="text-xs text-surface-500">Nº da Peça</label>}
                                <Input
                                    value={part.part_number || ''}
                                    onChange={(e) => updatePart(index, 'part_number', e.target.value)}
                                    placeholder="Part number"
                                />
                            </div>
                            <div className="col-span-3">
                                {index === 0 && <label className="text-xs text-surface-500">Origem</label>}
                                <Input
                                    value={part.origin || ''}
                                    onChange={(e) => updatePart(index, 'origin', e.target.value)}
                                    placeholder="Origem"
                                />
                            </div>
                            <div className="col-span-1">
                                {index === 0 && <label className="text-xs text-surface-500">Qtd</label>}
                                <Input
                                    type="number"
                                    min={1}
                                    value={part.quantity ?? 1}
                                    onChange={(e) => updatePart(index, 'quantity', parseInt(e.target.value) || 1)}
                                />
                            </div>
                            <div className="col-span-1 flex justify-center">
                                <Button type="button" variant="ghost" size="sm" onClick={() => removePart(index)} className="text-red-500 hover:text-red-700 p-1">
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Lacre */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className={labelClass}>Status do Lacre</label>
                        <select className={selectClass} value={sealStatus} onChange={(e) => setSealStatus(e.target.value)}>
                            {SEAL_STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                    {sealStatus === 'replaced' && (
                        <div>
                            <label className={labelClass}>Número do Novo Lacre</label>
                            <Input
                                value={newSealNumber}
                                onChange={(e) => setNewSealNumber(e.target.value)}
                                placeholder="Nº do novo lacre"
                            />
                        </div>
                    )}
                </div>

                {/* Toggles */}
                <div className="flex flex-wrap gap-6">
                    <label className="flex items-center gap-2 text-sm text-surface-700 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={requiresCalibrationAfter}
                            onChange={(e) => setRequiresCalibrationAfter(e.target.checked)}
                            className="rounded border-surface-300 text-brand-600 focus:ring-brand-500"
                        />
                        Requer calibração após manutenção
                    </label>
                    <label className="flex items-center gap-2 text-sm text-surface-700 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={requiresIpemVerification}
                            onChange={(e) => setRequiresIpemVerification(e.target.checked)}
                            className="rounded border-surface-300 text-brand-600 focus:ring-brand-500"
                        />
                        Requer verificação IPEM
                    </label>
                </div>

                {/* Datas */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className={labelClass}>Início da Manutenção</label>
                        <Input
                            type="datetime-local"
                            value={startedAt}
                            onChange={(e) => setStartedAt(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className={labelClass}>Conclusão da Manutenção</label>
                        <Input
                            type="datetime-local"
                            value={completedAt}
                            onChange={(e) => setCompletedAt(e.target.value)}
                        />
                    </div>
                </div>

                {/* Observações */}
                <div>
                    <label className={labelClass}>Observações</label>
                    <textarea
                        className={inputClass}
                        rows={2}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Observações adicionais"
                    />
                </div>

                {/* Actions */}
                <div className="flex justify-end gap-3 pt-2 border-t border-subtle">
                    <Button type="button" variant="ghost" onClick={onClose} disabled={isPending}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={isPending || !defectFound.trim()}>
                        {isPending ? 'Salvando...' : report ? 'Atualizar' : 'Criar Relatório'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
