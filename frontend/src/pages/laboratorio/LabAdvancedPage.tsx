import { useState } from 'react'
import { useQuery} from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Card, CardContent} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Thermometer, BookOpen, Archive, BarChart3, Loader2 } from 'lucide-react'

export function LabAdvancedPage() {
  const [tab, setTab] = useState('sensors')

  const { data: sensors, isLoading: sensorsLoading } = useQuery({
    queryKey: ['lab-sensors'],
    queryFn: () => api.get('/lab-advanced/sensor-readings').then(r => r.data),
    enabled: tab === 'sensors',
  })

  const { data: logbook, isLoading: logbookLoading } = useQuery({
    queryKey: ['lab-logbook'],
    queryFn: () => api.get('/lab-advanced/logbook').then(r => r.data),
    enabled: tab === 'logbook',
  })

  const { data: samples, isLoading: samplesLoading } = useQuery({
    queryKey: ['lab-samples'],
    queryFn: () => api.get('/lab-advanced/retention-samples').then(r => r.data),
    enabled: tab === 'samples',
  })

  const { data: rrStudies, isLoading: rrLoading } = useQuery({
    queryKey: ['lab-rr-studies'],
    queryFn: () => api.get('/lab-advanced/rr-study').then(r => r.data),
    enabled: tab === 'rr',
  })

  const renderList = (items: { id?: number; title?: string; description?: string; name?: string; value?: string | number; unit?: string; date?: string; created_at?: string; status?: string }[] | undefined, loading: boolean, emptyMsg: string) => {
    if (loading) return <div className="flex justify-center py-12"><Loader2 className="w-6 h-6 animate-spin text-muted-foreground" /></div>
    if (!items?.length) return <p className="text-sm text-muted-foreground py-8 text-center">{emptyMsg}</p>
    return (
      <div className="space-y-3">
        {(items || []).map((item, i: number) => (
          <Card key={item.id ?? i}>
            <CardContent className="p-4">
              <div className="flex justify-between items-start">
                <div>
                  <p className="font-medium">{item.title ?? item.description ?? item.name ?? `Registro #${item.id}`}</p>
                  {item.value && <p className="text-sm text-muted-foreground">Valor: {item.value} {item.unit ?? ''}</p>}
                  {item.date && <p className="text-xs text-muted-foreground">{new Date(item.date).toLocaleDateString('pt-BR')}</p>}
                  {item.created_at && <p className="text-xs text-muted-foreground">{new Date(item.created_at).toLocaleDateString('pt-BR')}</p>}
                </div>
                {item.status && <Badge variant={item.status === 'approved' ? 'default' : 'secondary'}>{item.status}</Badge>}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Laboratório Avançado" description="Sensores, logbook, amostras de retenção e estudos R&R" />

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="sensors"><Thermometer className="w-4 h-4 mr-1" /> Sensores</TabsTrigger>
          <TabsTrigger value="logbook"><BookOpen className="w-4 h-4 mr-1" /> Logbook</TabsTrigger>
          <TabsTrigger value="samples"><Archive className="w-4 h-4 mr-1" /> Amostras</TabsTrigger>
          <TabsTrigger value="rr"><BarChart3 className="w-4 h-4 mr-1" /> Estudos R&R</TabsTrigger>
        </TabsList>

        <TabsContent value="sensors">
          {renderList(sensors?.data ?? sensors, sensorsLoading, 'Nenhuma leitura de sensor registrada')}
        </TabsContent>

        <TabsContent value="logbook">
          {renderList(logbook?.data ?? logbook, logbookLoading, 'Nenhum registro no logbook')}
        </TabsContent>

        <TabsContent value="samples">
          {renderList(samples?.data ?? samples, samplesLoading, 'Nenhuma amostra de retenção')}
        </TabsContent>

        <TabsContent value="rr">
          {renderList(rrStudies?.data ?? rrStudies, rrLoading, 'Nenhum estudo R&R registrado')}
        </TabsContent>
      </Tabs>
    </div>
  )
}

export default LabAdvancedPage
