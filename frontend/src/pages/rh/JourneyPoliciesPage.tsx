import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Settings2, Plus, Pencil, Trash2, Loader2 } from 'lucide-react'
import { journeyApi } from '@/lib/journey-api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import type { JourneyPolicy } from '@/types/journey'

const REGIME_LABELS: Record<string, string> = {
  clt_mensal: 'CLT Mensal',
  clt_6meses: 'CLT 6 Meses',
  cct_anual: 'CCT Anual',
}

function formatMinutesToHours(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m > 0 ? `${h}h${m}min` : `${h}h`
}

export default function JourneyPoliciesPage() {
  const qc = useQueryClient()
  const [editingId, setEditingId] = useState<number | null>(null)

  const { data: listData, isLoading } = useQuery({
    queryKey: ['journey-policies'],
    queryFn: () => journeyApi.listPolicies({ per_page: 50 }),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => journeyApi.deletePolicy(id),
    onSuccess: () => {
      toast.success('Política removida')
      qc.invalidateQueries({ queryKey: ['journey-policies'] })
    },
    onError: () => toast.error('Erro ao remover política'),
  })

  const policies = listData?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Políticas de Jornada"
        subtitle="Configure regras de classificação de tempo por regime/sindicato"
        icon={Settings2}
      />

      <div className="flex justify-end">
        <Button size="sm" aria-label="Criar nova política">
          <Plus className="mr-1 h-4 w-4" />
          Nova Política
        </Button>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : policies.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma política configurada. Crie uma para começar.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-muted-foreground">
                <th className="px-3 py-2">Nome</th>
                <th className="px-3 py-2">Regime</th>
                <th className="px-3 py-2">Jornada Diária</th>
                <th className="px-3 py-2">Intervalo</th>
                <th className="px-3 py-2">Desloc. = Trabalho</th>
                <th className="px-3 py-2">Sáb. HE</th>
                <th className="px-3 py-2">Dom. HE</th>
                <th className="px-3 py-2">Padrão</th>
                <th className="px-3 py-2">Ações</th>
              </tr>
            </thead>
            <tbody>
              {policies.map((p: JourneyPolicy) => (
                <tr key={p.id} className="border-b hover:bg-muted/50">
                  <td className="px-3 py-2 font-medium">{p.name}</td>
                  <td className="px-3 py-2">{REGIME_LABELS[p.regime_type] ?? p.regime_type}</td>
                  <td className="px-3 py-2">{formatMinutesToHours(p.daily_hours_limit)}</td>
                  <td className="px-3 py-2">{p.break_minutes}min</td>
                  <td className="px-3 py-2">{p.displacement_counts_as_work ? 'Sim' : 'Não'}</td>
                  <td className="px-3 py-2">{p.saturday_is_overtime ? 'Sim' : 'Não'}</td>
                  <td className="px-3 py-2">{p.sunday_is_overtime ? 'Sim' : 'Não'}</td>
                  <td className="px-3 py-2">
                    {p.is_default && (
                      <span className="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800">
                        Padrão
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      <Button
                        size="icon"
                        variant="ghost"
                        className="h-7 w-7"
                        onClick={() => setEditingId(p.id)}
                        aria-label={`Editar política ${p.name}`}
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        className="h-7 w-7 text-destructive"
                        onClick={() => {
                          if (confirm(`Remover política "${p.name}"?`)) {
                            deleteMut.mutate(p.id)
                          }
                        }}
                        disabled={deleteMut.isPending}
                        aria-label={`Remover política ${p.name}`}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
