import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Loader2, Plus, Trash2, Wrench } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import api from '@/lib/api'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { equipmentApi } from '@/lib/equipment-api'
import { normalizeMaintenanceType } from '@/lib/equipment-utils'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import type { EquipmentMaintenance } from '@/types/equipment'

interface EquipmentOption {
  id: number
  code: string
  brand: string | null
  model: string | null
}

interface MaintenanceFormState {
  equipment_id: string
  type: string
  description: string
  next_maintenance_at: string
  cost: string
}

const maintenanceTypeLabels: Record<string, string> = {
  preventiva: 'Preventiva',
  corretiva: 'Corretiva',
  ajuste: 'Ajuste',
  limpeza: 'Limpeza',
}

const initialForm: MaintenanceFormState = {
  equipment_id: '',
  type: 'preventiva',
  description: '',
  next_maintenance_at: '',
  cost: '',
}

export function EquipmentMaintenancePage() {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState<MaintenanceFormState>(initialForm)

  const { data, isLoading } = useQuery({
    queryKey: ['equipment-maintenances'],
    queryFn: () => equipmentApi.listMaintenances(),
  })

  const { data: equipmentOptions } = useQuery({
    queryKey: ['equipment-maintenance-options'],
    queryFn: () =>
      api
        .get('/equipments', { params: { per_page: 200 } })
        .then(unwrapData<EquipmentOption[]>),
  })

  const maintenances = data?.data ?? []

  const saveMut = useMutation({
    mutationFn: () =>
      equipmentApi.createMaintenance({
        equipment_id: Number(form.equipment_id),
        type: normalizeMaintenanceType(form.type),
        description: form.description,
        next_maintenance_at: form.next_maintenance_at || null,
        cost: form.cost ? Number(form.cost) : null,
      }),
    onSuccess: () => {
      toast.success('Manutenção registrada')
      setShowForm(false)
      setForm(initialForm)
      qc.invalidateQueries({ queryKey: ['equipment-maintenances'] })
      broadcastQueryInvalidation(['equipment-maintenances'], 'Manutenções')
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao registrar manutenção')),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => equipmentApi.deleteMaintenance(id),
    onSuccess: () => {
      toast.success('Manutenção excluída')
      qc.invalidateQueries({ queryKey: ['equipment-maintenances'] })
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao excluir manutenção')),
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="Manutenções de Equipamento"
        description="Registre e acompanhe manutenções preventivas e corretivas"
        action={(
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-1 h-4 w-4" /> Nova Manutenção
          </Button>
        )}
      />

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : !maintenances.length ? (
        <p className="py-12 text-center text-sm text-muted-foreground">Nenhuma manutenção registrada.</p>
      ) : (
        <div className="space-y-3">
          {maintenances.map((maintenance) => (
            <MaintenanceCard
              key={maintenance.id}
              maintenance={maintenance}
              onDelete={() => {
                if (confirm('Excluir manutenção?')) {
                  deleteMut.mutate(maintenance.id)
                }
              }}
            />
          ))}
        </div>
      )}

      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nova Manutenção</DialogTitle>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-surface-700" htmlFor="equipment_id">
                Equipamento
              </label>
              <select
                id="equipment_id"
                aria-label="Equipamento"
                className="w-full rounded-md border px-3 py-2 text-sm"
                value={form.equipment_id}
                onChange={(event) => setForm((current) => ({ ...current, equipment_id: event.target.value }))}
              >
                <option value="">Selecione...</option>
                {(equipmentOptions ?? []).map((equipment) => (
                  <option key={equipment.id} value={equipment.id}>
                    {equipment.code} - {equipment.brand ?? 'Sem marca'} {equipment.model ?? ''}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-surface-700" htmlFor="type">
                Tipo
              </label>
              <select
                id="type"
                aria-label="Tipo de manutenção"
                className="w-full rounded-md border px-3 py-2 text-sm"
                value={form.type}
                onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))}
              >
                <option value="preventiva">Preventiva</option>
                <option value="corretiva">Corretiva</option>
                <option value="ajuste">Ajuste</option>
                <option value="limpeza">Limpeza</option>
              </select>
            </div>

            <Input
              aria-label="Descrição"
              placeholder="Descrição"
              value={form.description}
              onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
            />

            <Input
              aria-label="Próxima manutenção"
              type="date"
              value={form.next_maintenance_at}
              onChange={(event) => setForm((current) => ({ ...current, next_maintenance_at: event.target.value }))}
            />

            <Input
              aria-label="Custo"
              type="number"
              placeholder="Custo"
              value={form.cost}
              onChange={(event) => setForm((current) => ({ ...current, cost: event.target.value }))}
            />

            <Button
              onClick={() => saveMut.mutate()}
              disabled={saveMut.isPending || !form.equipment_id || !form.description.trim()}
            >
              {saveMut.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : null}
              Registrar
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function MaintenanceCard({
  maintenance,
  onDelete,
}: {
  maintenance: EquipmentMaintenance
  onDelete: () => void
}) {
  return (
    <Card>
      <CardContent className="flex items-center justify-between p-4">
        <div className="flex items-center gap-3">
          <Wrench className="h-5 w-5 text-muted-foreground" />
          <div>
            <p className="font-medium">{maintenance.description}</p>
            <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
              <span>{maintenanceTypeLabels[maintenance.type] ?? maintenance.type}</span>
              {maintenance.next_maintenance_at && (
                <span>
                  Próxima: {new Date(`${maintenance.next_maintenance_at}T12:00:00`).toLocaleDateString('pt-BR')}
                </span>
              )}
              {maintenance.cost && (
                <span>
                  R$ {Number(maintenance.cost).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                </span>
              )}
            </div>
          </div>
        </div>

        <Button variant="ghost" size="icon" aria-label="Excluir manutenção" onClick={onDelete}>
          <Trash2 className="h-4 w-4 text-destructive" />
        </Button>
      </CardContent>
    </Card>
  )
}

export default EquipmentMaintenancePage
