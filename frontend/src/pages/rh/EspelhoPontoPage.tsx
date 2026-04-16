import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Calendar as CalendarIcon, FileCheck, FileSignature, AlertCircle,
    CheckCircle2, Printer, Loader2, ChevronLeft, ChevronRight, Lock
} from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import type { EspelhoPonto } from '@/types/hr'

type EspelhoDay = EspelhoPonto['days'][number]
type EspelhoDayEntry = EspelhoDay['entries'][number]

export default function EspelhoPontoPage() {
    const qc = useQueryClient()
    const [currentDate, setCurrentDate] = useState(() => new Date())
    const [showConfirmModal, setShowConfirmModal] = useState(false)
    const [password, setPassword] = useState('')

    const year = currentDate.getFullYear()
    const month = currentDate.getMonth() + 1

    const { data: espelho, isLoading, error } = useQuery({
        queryKey: ['espelho', year, month],
        queryFn: () => api.get('/hr/clock/espelho', { params: { year, month } }).then(unwrapData<EspelhoPonto>)
    })
    const queryErrorMessage = error ? getApiErrorMessage(error, 'Erro ao carregar espelho de ponto') : null

    const confirmMut = useMutation({
        mutationFn: (pwd: string) => api.post('/hr/clock/espelho/confirm', { year, month, password: pwd }),
        onSuccess: () => {
            toast.success('Espelho de ponto assinado com sucesso!')
            setShowConfirmModal(false)
            setPassword('')
            qc.invalidateQueries({ queryKey: ['espelho', year, month] })
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao assinar espelho de ponto'))
        }
    })

    const handlePrevMonth = () => {
        setCurrentDate(prev => {
            const d = new Date(prev)
            d.setMonth(d.getMonth() - 1)
            return d
        })
    }

    const handleNextMonth = () => {
        setCurrentDate(prev => {
            const d = new Date(prev)
            d.setMonth(d.getMonth() + 1)
            return d
        })
    }

    const handleConfirm = () => {
        if (!password) {
            toast.error('Informe a senha para assinar eletronicamente.')
            return
        }
        confirmMut.mutate(password)
    }

    const handlePrint = () => {
        window.print()
    }

    return (
        <div className="space-y-6 max-w-5xl mx-auto">
            <PageHeader
                title="Espelho de Ponto"
                subtitle="Visualize e assine seu relatório mensal de ponto"
                actions={
                    <Button variant="outline" onClick={handlePrint} icon={<Printer className="w-4 h-4" />} className="print:hidden">
                        Imprimir / PDF
                    </Button>
                }
            />

            <div className="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-sm print:hidden">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handlePrevMonth}
                        icon={<ChevronLeft className="w-4 h-4" />}
                        aria-label="Mês anterior"
                    />
                    <div className="flex items-center gap-2 px-4 py-1.5 bg-slate-50 rounded-lg font-medium text-slate-700 min-w-[140px] justify-center">
                        <CalendarIcon className="w-4 h-4 text-slate-400" />
                        {new Date(year, month - 1).toLocaleString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase())}
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleNextMonth}
                        icon={<ChevronRight className="w-4 h-4" />}
                        aria-label="Próximo mês"
                        disabled={month === new Date().getMonth() + 1 && year === new Date().getFullYear()}
                    />
                </div>

                {espelho && (
                    <div className="flex items-center gap-4">
                        {espelho.confirmation ? (
                            <div className="flex items-center gap-2 text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg text-sm font-medium">
                                <FileCheck className="w-4 h-4" />
                                Assinado
                            </div>
                        ) : (
                            <Button
                                onClick={() => setShowConfirmModal(true)}
                                icon={<FileSignature className="w-4 h-4" />}
                            >
                                Assinar Eletronicamente
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {isLoading ? (
                <div className="flex flex-col items-center justify-center py-20 bg-white rounded-xl border border-slate-200">
                    <Loader2 className="w-10 h-10 animate-spin text-brand-500 mb-4" />
                    <p className="text-slate-500">Gerando espelho de ponto...</p>
                </div>
            ) : queryErrorMessage ? (
                <div className="flex flex-col items-center justify-center py-20 bg-white rounded-xl border border-slate-200 text-red-600">
                    {queryErrorMessage}
                </div>
            ) : !espelho ? (
                <div className="flex flex-col items-center justify-center py-20 bg-white rounded-xl border border-slate-200 text-slate-500">
                    Nenhum dado encontrado para o período.
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden print:border-none print:shadow-none">
                    {/* Header */}
                    <div className="p-6 border-b border-slate-200 flex items-start justify-between bg-slate-50 print:bg-transparent">
                        <div>
                            <h2 className="text-lg font-bold text-slate-800">Folha de Registro de Ponto</h2>
                            <p className="text-sm text-slate-500 mt-1">Identificação do Empregado e Período (Portaria 671/2021)</p>
                        </div>
                        <div className="text-right text-sm">
                            <div className="font-medium text-slate-700">{espelho.period.month_name.toUpperCase()} / {espelho.period.year}</div>
                            <div className="text-slate-500">
                                Período: {espelho.period.start_date} a {espelho.period.end_date}
                            </div>
                        </div>
                    </div>

                    <div className="px-6 py-4 grid grid-cols-2 gap-y-4 gap-x-8 text-sm">
                        <div>
                            <div className="text-slate-500 mb-1">Nome do Empregado</div>
                            <div className="font-semibold text-slate-800">{espelho.employee.name}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 mb-1">PIS / CPF</div>
                            <div className="font-medium text-slate-800">PIS: {espelho.employee.pis || '-'} | CPF: {espelho.employee.cpf || '-'}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 mb-1">Admissão</div>
                            <div className="font-medium text-slate-800">{espelho.employee.admission_date || '-'}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 mb-1">Horário (Turno)</div>
                            <div className="font-medium text-slate-800">{espelho.employee.work_shift || 'Padrão'}</div>
                        </div>
                    </div>

                    <div className="overflow-x-auto border-t border-slate-200">
                        <table className="w-full text-sm text-left">
                            <thead className="text-xs text-slate-600 bg-slate-50 uppercase border-b border-slate-200 sticky top-0">
                                <tr>
                                    <th className="px-4 py-3 font-semibold text-center w-12">Dia</th>
                                    <th className="px-4 py-3 font-semibold text-center w-12">Sem</th>
                                    <th className="px-4 py-3 font-semibold text-center w-24">Ent 1</th>
                                    <th className="px-4 py-3 font-semibold text-center w-24">Sai 1</th>
                                    <th className="px-4 py-3 font-semibold text-center w-24">Ent 2</th>
                                    <th className="px-4 py-3 font-semibold text-center w-24">Sai 2</th>
                                    <th className="px-4 py-3 font-semibold text-center w-24 min-w-[80px]">Total Trab</th>
                                    <th className="px-4 py-3 font-semibold w-40">Ocorrências</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {espelho.days.map((day: EspelhoDay, i: number) => {
                                    const e1 = day.entries[0]
                                    const e2 = day.entries[1] as EspelhoDayEntry | undefined
                                    const isWeekend = day.day_of_week === 'Dom' || day.day_of_week === 'Sáb'

                                    return (
                                        <tr key={i} className={cn(isWeekend && "bg-slate-50/50 text-slate-500")}>
                                            <td className="px-4 py-2 text-center font-medium">{day.date.split('/')[0]}</td>
                                            <td className="px-4 py-2 text-center">{day.day_of_week}</td>
                                            {/* Turno 1 */}
                                            <td className="px-4 py-2 text-center">{e1?.clock_in || '-'}</td>
                                            <td className={cn("px-4 py-2 text-center", !e1?.clock_out && "text-amber-500")}>{e1?.break_start || e1?.clock_out || '-'}</td>
                                            {/* Turno 2 (after break) */}
                                            <td className="px-4 py-2 text-center">{e1?.break_end || e2?.clock_in || '-'}</td>
                                            <td className={cn("px-4 py-2 text-center", !e2?.clock_out && e1?.break_end && !e1?.clock_out && "text-amber-500")}>{e1?.clock_out || e2?.clock_out || '-'}</td>
                                            {/* Horas */}
                                            <td className="px-4 py-2 text-center font-medium">{day.total_hours > 0 ? `${day.total_hours.toFixed(2)}h` : '-'}</td>
                                            <td className="px-4 py-2 text-slate-500 text-xs truncate max-w-[150px]">
                                                {e1?.approval_status === 'pending' || e2?.approval_status === 'pending' ? <span className="text-amber-600">Pendente aprovação</span> : ''}
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="p-6 bg-slate-50 border-t border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4 print:bg-transparent">
                        <div className="space-y-1">
                            <div className="text-xs text-slate-500 uppercase font-semibold tracking-wider">Dias Trab.</div>
                            <div className="text-xl font-bold text-slate-800">{espelho.summary.total_work_days}</div>
                        </div>
                        <div className="space-y-1">
                            <div className="text-xs text-slate-500 uppercase font-semibold tracking-wider">Total Trab.</div>
                            <div className="text-xl font-bold text-slate-800">{espelho.summary.total_hours.toFixed(2)}h</div>
                        </div>
                        <div className="space-y-1">
                            <div className="text-xs text-slate-500 uppercase font-semibold tracking-wider">Média/Dia</div>
                            <div className="text-xl font-bold text-slate-800">{espelho.summary.average_hours_per_day.toFixed(2)}h</div>
                        </div>
                        <div className="space-y-1">
                            <div className="text-xs text-slate-500 uppercase font-semibold tracking-wider">Faltas/Atrasos</div>
                            <div className="text-xl font-bold text-slate-800">-</div>
                        </div>
                    </div>

                    {/* Footer Assinaturas */}
                    <div className="p-8 pt-12 flex items-end justify-between border-t border-slate-200">
                        <div className="w-[45%] text-center">
                            <div className="border-b border-black mb-2 pb-1">KALIBRIUM</div>
                            <div className="text-xs text-slate-500">Empregador</div>
                        </div>
                        <div className="w-[45%] text-center relative">
                            <div className={cn("border-b mb-2 pb-1", espelho.confirmation ? "border-emerald-500 text-emerald-700 font-medium" : "border-black")}>
                                {espelho.confirmation ? `ASSINADO ELETRONICAMENTE EM ${new Date(espelho.confirmation.confirmed_at).toLocaleString('pt-BR')}` : espelho.employee.name}
                            </div>
                            <div className="text-xs text-slate-500">Empregado</div>
                            {espelho.confirmation && (
                                <div className="absolute -top-10 left-1/2 -translate-x-1/2 flex flex-col items-center opacity-20 rotate-[-10deg] pointer-events-none">
                                    <CheckCircle2 className="w-16 h-16 text-emerald-600" />
                                </div>
                            )}
                        </div>
                    </div>
                    {espelho.confirmation && (
                        <div className="px-6 py-3 bg-emerald-50 text-emerald-700 text-xs text-center border-t border-emerald-100 flex items-center justify-center gap-2">
                            <Lock className="w-3.5 h-3.5" />
                            Documento assinado digitalmente. Hash da assinatura: <span className="font-mono bg-emerald-100 px-1 rounded truncate max-w-[200px]">{espelho.confirmation.content_hash}</span>
                        </div>
                    )}
                </div>
            )}

            <Modal
                open={showConfirmModal}
                onOpenChange={setShowConfirmModal}
                title="Assinatura Eletrônica"
                description={`Confirme sua senha para assinar o espelho de ponto de ${new Date(year, month - 1).toLocaleString('pt-BR', { month: 'long', year: 'numeric' })}`}
                className="max-w-md"
            >
                <div className="space-y-4 pt-4">
                    <div className="p-4 bg-amber-50 text-amber-800 text-sm rounded-lg flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                        A assinatura eletrônica possui valor legal conforme a Portaria 671/2021. Você atesta que as informações do espelho estão corretas.
                    </div>

                    <div className="space-y-2">
                        <label htmlFor="espelho-password" className="text-sm font-medium text-slate-700">Sua senha de acesso</label>
                        <Input
                            id="espelho-password"
                            type="password"
                            placeholder="••••••••"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') handleConfirm()
                            }}
                            autoFocus
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-6">
                    <Button variant="outline" onClick={() => setShowConfirmModal(false)}>Cancelar</Button>
                    <Button
                        onClick={handleConfirm}
                        disabled={!password || confirmMut.isPending}
                        loading={confirmMut.isPending}
                    >
                        Assinar Documento
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
