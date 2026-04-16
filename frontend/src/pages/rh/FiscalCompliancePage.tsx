import { useState } from 'react'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Download, ShieldCheck, MapPin, Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import api, { getApiErrorMessage } from '@/lib/api'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'

export default function FiscalCompliancePage() {
    const { hasPermission, hasRole } = useAuthStore()
    const isAllowed = hasRole('super_admin') || hasPermission('hr.fiscal.view')

    const [startDate, setStartDate] = useState('')
    const [endDate, setEndDate] = useState('')
    const [isLoadingACJEF, setIsLoadingACJEF] = useState(false)
    const [isLoadingLocation, setIsLoadingLocation] = useState(false)

    const handleDownloadACJEF = async () => {
        if (!startDate || !endDate) {
            toast.error('Informe a data inicial e final.')
            return
        }

        try {
            setIsLoadingACJEF(true)
            const response = await api.get('/hr/fiscal/acjef', {
                params: { start_date: startDate, end_date: endDate },
                responseType: 'blob'
            })

            const url = window.URL.createObjectURL(new Blob([response.data]))
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', `ACJEF_${startDate}_${endDate}.txt`)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
            toast.success('Arquivo ACJEF exportado com sucesso.')
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Erro ao exportar arquivo ACJEF'))
        } finally {
            setIsLoadingACJEF(false)
        }
    }

    const handleDownloadLocation = async () => {
        if (!startDate || !endDate) {
            toast.error('Informe a data inicial e final.')
            return
        }

        try {
            setIsLoadingLocation(true)
            const response = await api.get('/hr/fiscal/location-history', {
                params: { start_date: startDate, end_date: endDate },
                responseType: 'blob'
            })

            const url = window.URL.createObjectURL(new Blob([response.data]))
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', `Localizacao_${startDate}_${endDate}.csv`)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
            toast.success('Histórico de localização exportado.')
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Erro ao exportar histórico'))
        } finally {
            setIsLoadingLocation(false)
        }
    }

    if (!isAllowed) {
        return (
            <div className="flex flex-col items-center justify-center py-20 min-h-[50vh]">
                <ShieldCheck className="w-16 h-16 text-slate-200 mb-4" />
                <h2 className="text-xl font-semibold text-slate-700">Acesso Restrito</h2>
                <p className="text-slate-500 mt-2 max-w-md text-center">
                    Você não tem permissão para acessar o painel de compliance fiscal.
                </p>
            </div>
        )
    }

    return (
        <div className="space-y-6 max-w-4xl mx-auto">
            <PageHeader
                title="Compliance & Painel Fiscal"
                subtitle="Ferramentas obrigatórias pela Portaria 671/2021"
            />

            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                    <Search className="w-5 h-5 text-brand-500" />
                    Parâmetros de Geração
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-lg">
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-slate-700">Data Inicial</label>
                        <Input
                            type="date"
                            value={startDate}
                            onChange={e => setStartDate(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-slate-700">Data Final</label>
                        <Input
                            type="date"
                            value={endDate}
                            onChange={e => setEndDate(e.target.value)}
                        />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                {/* Exportação ACJEF */}
                <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div className="w-10 h-10 bg-brand-50 rounded-lg flex items-center justify-center mb-4">
                            <ShieldCheck className="w-5 h-5 text-brand-600" />
                        </div>
                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Arquivo ACJEF/AFD</h3>
                        <p className="text-sm text-slate-500 mb-6">
                            Gera o arquivo de Controle de Jornada para Efeitos Fiscais, em formato TXT padrão ABNT especificado pela Portaria MTP nº 671/2021.
                        </p>
                    </div>
                    <Button
                        onClick={handleDownloadACJEF}
                        loading={isLoadingACJEF}
                        disabled={!startDate || !endDate}
                        className="w-full justify-center"
                        icon={<Download className="w-4 h-4" />}
                    >
                        Exportar ACJEF
                    </Button>
                </div>

                {/* Exportação de Rastreio (GPS) */}
                <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div className="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center mb-4">
                            <MapPin className="w-5 h-5 text-emerald-600" />
                        </div>
                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Histórico de Localização</h3>
                        <p className="text-sm text-slate-500 mb-6">
                            Exporta relatório completo em CSV contendo latitude, longitude e status de conformidade da geolocalização dos colaboradores, para fins de comprovação e auditoria interna.
                        </p>
                    </div>
                    <Button
                        onClick={handleDownloadLocation}
                        loading={isLoadingLocation}
                        disabled={!startDate || !endDate}
                        className="w-full justify-center bg-emerald-600 hover:bg-emerald-700 text-white"
                        icon={<Download className="w-4 h-4" />}
                    >
                        Exportar Relatório CSV
                    </Button>
                </div>

            </div>
        </div>
    )
}
