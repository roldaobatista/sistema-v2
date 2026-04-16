import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Save, RefreshCw, DollarSign } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { toast } from 'sonner'
import { hrApi } from '@/lib/hr-api'

interface TaxBracket {
  id?: number
  type: 'inss' | 'irrf'
  year: number
  min_salary: number
  max_salary: number | null
  rate: number
  deduction: number
}

interface MinimumWage {
  id?: number
  year: number
  value: number
}

interface TaxTablesData {
  inss: TaxBracket[]
  irrf: TaxBracket[]
  minimum_wage: MinimumWage | null
}

export default function TaxTablesPage() {
  const queryClient = useQueryClient()
  const currentYear = new Date().getFullYear()
  const [year, setYear] = useState(currentYear)
  const [activeTab, setActiveTab] = useState('inss')
  const [editingInss, setEditingInss] = useState<TaxBracket[]>([])
  const [editingIrrf, setEditingIrrf] = useState<TaxBracket[]>([])
  const [editingWage, setEditingWage] = useState<number>(0)
  const [hasChanges, setHasChanges] = useState(false)

  const { data: tablesData, isLoading } = useQuery({
    queryKey: ['tax-tables', year],
    queryFn: () => hrApi.taxTables.list(year).then(r => {
      const data = r.data?.data as TaxTablesData | undefined
      return data ?? { inss: [], irrf: [], minimum_wage: null }
    }),
  })

  const tables: TaxTablesData = tablesData ?? { inss: [], irrf: [], minimum_wage: null }

  // Sync editing state when data loads
  const inssRows = hasChanges ? editingInss : tables.inss
  const irrfRows = hasChanges ? editingIrrf : tables.irrf
  const wageValue = hasChanges ? editingWage : (tables.minimum_wage?.value ?? 0)

  const startEditing = () => {
    if (!hasChanges) {
      setEditingInss([...tables.inss])
      setEditingIrrf([...tables.irrf])
      setEditingWage(tables.minimum_wage?.value ?? 0)
    }
  }

  const updateInssRow = (index: number, field: keyof TaxBracket, value: number | null) => {
    startEditing()
    setHasChanges(true)
    setEditingInss(prev => {
      const rows = prev.length > 0 ? [...prev] : [...tables.inss]
      rows[index] = { ...rows[index], [field]: value }
      return rows
    })
  }

  const updateIrrfRow = (index: number, field: keyof TaxBracket, value: number | null) => {
    startEditing()
    setHasChanges(true)
    setEditingIrrf(prev => {
      const rows = prev.length > 0 ? [...prev] : [...tables.irrf]
      rows[index] = { ...rows[index], [field]: value }
      return rows
    })
  }

  const addRow = (type: 'inss' | 'irrf') => {
    startEditing()
    setHasChanges(true)
    const newRow: TaxBracket = { type, year, min_salary: 0, max_salary: null, rate: 0, deduction: 0 }
    if (type === 'inss') {
      setEditingInss(prev => [...(prev.length > 0 ? prev : tables.inss), newRow])
    } else {
      setEditingIrrf(prev => [...(prev.length > 0 ? prev : tables.irrf), newRow])
    }
  }

  const removeRow = (type: 'inss' | 'irrf', index: number) => {
    startEditing()
    setHasChanges(true)
    if (type === 'inss') {
      setEditingInss(prev => {
        const rows = prev.length > 0 ? [...prev] : [...tables.inss]
        rows.splice(index, 1)
        return rows
      })
    } else {
      setEditingIrrf(prev => {
        const rows = prev.length > 0 ? [...prev] : [...tables.irrf]
        rows.splice(index, 1)
        return rows
      })
    }
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const inssData = editingInss.length > 0 ? editingInss : tables.inss
      const irrfData = editingIrrf.length > 0 ? editingIrrf : tables.irrf
      const wage = editingWage || tables.minimum_wage?.value || 0
      await hrApi.taxTables.store({ type: 'inss', year, data: inssData })
      await hrApi.taxTables.store({ type: 'irrf', year, data: irrfData })
      await hrApi.taxTables.store({ type: 'minimum_wage', year, data: [{ value: wage }] })
    },
    onSuccess: () => {
      toast.success('Tabelas fiscais salvas com sucesso.')
      queryClient.invalidateQueries({ queryKey: ['tax-tables'] })
      setHasChanges(false)
    },
    onError: () => toast.error('Erro ao salvar tabelas fiscais.'),
  })

  const formatCurrency = (v: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

  const renderBracketTable = (rows: TaxBracket[], type: 'inss' | 'irrf') => (
    <div className="space-y-3">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b">
              <th className="text-left py-2 px-3">Salário Mínimo (R$)</th>
              <th className="text-left py-2 px-3">Salário Máximo (R$)</th>
              <th className="text-left py-2 px-3">Alíquota (%)</th>
              <th className="text-left py-2 px-3">Dedução (R$)</th>
              <th className="text-left py-2 px-3 w-20"></th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="text-center py-8 text-muted-foreground">
                  Nenhuma faixa cadastrada para {year}.
                </td>
              </tr>
            ) : (
              rows.map((row, i) => (
                <tr key={i} className="border-b">
                  <td className="py-2 px-3">
                    <Input
                      type="number"
                      step="0.01"
                      value={row.min_salary}
                      onChange={(e) => (type === 'inss' ? updateInssRow : updateIrrfRow)(i, 'min_salary', parseFloat(e.target.value) || 0)}
                      className="w-36"
                    />
                  </td>
                  <td className="py-2 px-3">
                    <Input
                      type="number"
                      step="0.01"
                      value={row.max_salary ?? ''}
                      placeholder="Sem limite"
                      onChange={(e) => (type === 'inss' ? updateInssRow : updateIrrfRow)(i, 'max_salary', e.target.value ? parseFloat(e.target.value) : null)}
                      className="w-36"
                    />
                  </td>
                  <td className="py-2 px-3">
                    <Input
                      type="number"
                      step="0.01"
                      value={row.rate}
                      onChange={(e) => (type === 'inss' ? updateInssRow : updateIrrfRow)(i, 'rate', parseFloat(e.target.value) || 0)}
                      className="w-28"
                    />
                  </td>
                  <td className="py-2 px-3">
                    <Input
                      type="number"
                      step="0.01"
                      value={row.deduction}
                      onChange={(e) => (type === 'inss' ? updateInssRow : updateIrrfRow)(i, 'deduction', parseFloat(e.target.value) || 0)}
                      className="w-32"
                    />
                  </td>
                  <td className="py-2 px-3">
                    <Button variant="ghost" size="sm" className="text-red-500" onClick={() => removeRow(type, i)}>
                      Remover
                    </Button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <Button variant="outline" size="sm" onClick={() => addRow(type)}>
        + Adicionar Faixa
      </Button>
    </div>
  )

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Tabelas Fiscais</h1>
          <p className="text-muted-foreground">INSS, IRRF e Salário Mínimo por ano</p>
        </div>
        <div className="flex items-center gap-3">
          <Select value={String(year)} onValueChange={(v) => { setYear(Number(v)); setHasChanges(false) }}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {[2024, 2025, 2026, 2027].map(y => (
                <SelectItem key={y} value={String(y)}>{y}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !hasChanges}>
            {saveMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
            Salvar
          </Button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="inss">INSS</TabsTrigger>
            <TabsTrigger value="irrf">IRRF</TabsTrigger>
            <TabsTrigger value="wage">Salário Mínimo</TabsTrigger>
          </TabsList>

          <TabsContent value="inss">
            <Card>
              <CardHeader>
                <CardTitle>Faixas INSS - {year}</CardTitle>
              </CardHeader>
              <CardContent>
                {renderBracketTable(inssRows, 'inss')}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="irrf">
            <Card>
              <CardHeader>
                <CardTitle>Faixas IRRF - {year}</CardTitle>
              </CardHeader>
              <CardContent>
                {renderBracketTable(irrfRows, 'irrf')}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="wage">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <DollarSign className="h-5 w-5" />
                  Salário Mínimo - {year}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="max-w-sm space-y-4">
                  <div>
                    <Label>Valor (R$)</Label>
                    <Input
                      type="number"
                      step="0.01"
                      value={wageValue}
                      onChange={(e) => {
                        startEditing()
                        setHasChanges(true)
                        setEditingWage(parseFloat(e.target.value) || 0)
                      }}
                      className="mt-1"
                    />
                  </div>
                  {tables.minimum_wage && (
                    <p className="text-sm text-muted-foreground">
                      Valor atual: {formatCurrency(tables.minimum_wage.value)}
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      )}
    </div>
  )
}
