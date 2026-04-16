import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { z } from 'zod';
import api, { getApiErrorMessage, unwrapData } from '@/lib/api';
import { PageHeader } from '@/components/ui/pageheader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { Plus, Trash2, Save, FileCheck } from 'lucide-react';
import type { CalibrationReading } from '@/types/calibration';

const readingSchema = z.object({
  reference_value: z.string().min(1, 'Valor referência é obrigatório').refine(
    (val) => !isNaN(parseFloat(val)) && parseFloat(val) > 0,
    'Deve ser um número positivo'
  ),
  indication_increasing: z.string(),
  indication_decreasing: z.string(),
  k_factor: z.string(),
  repetition: z.number().int().min(1, 'Repetição deve ser no mínimo 1'),
  unit: z.string().min(1, 'Unidade é obrigatória'),
}).refine(
  (data) => (data.indication_increasing !== '' && data.indication_increasing != null) ||
            (data.indication_decreasing !== '' && data.indication_decreasing != null),
  { message: 'Informe pelo menos uma indicação (crescente ou decrescente)', path: ['indication_increasing'] }
);

const readingsFormSchema = z.object({
  readings: z.array(readingSchema).min(1, 'Adicione pelo menos uma leitura'),
});

type ReadingErrors = Record<number, Record<string, string>>;
type FormErrors = { readings?: string };

type Reading = Pick<CalibrationReading, 'reference_value' | 'indication_increasing' | 'indication_decreasing' | 'k_factor' | 'repetition' | 'unit'>;

export default function CalibrationReadingsPage() {
  const { calibrationId } = useParams();
  const navigate = useNavigate();
  const qc = useQueryClient();

  const [readings, setReadings] = useState<Reading[]>([
    { reference_value: '', indication_increasing: '', indication_decreasing: '', k_factor: '2.00', repetition: 1, unit: 'kg' },
  ]);
  const [fieldErrors, setFieldErrors] = useState<ReadingErrors>({});
  const [formErrors, setFormErrors] = useState<FormErrors>({});

  const { data: existingReadings } = useQuery<Reading[]>({
    queryKey: ['calibration-readings', calibrationId],
    queryFn: () => api.get(`/calibration/${calibrationId}/readings`).then(r => unwrapData<Reading[]>(r)),
    enabled: !!calibrationId,
  });

  useEffect(() => {
    if (!existingReadings?.length) return;

    setReadings(existingReadings.map((reading) => ({
      reference_value: String(reading.reference_value),
      indication_increasing: String(reading.indication_increasing ?? ''),
      indication_decreasing: String(reading.indication_decreasing ?? ''),
      k_factor: String(reading.k_factor),
      repetition: reading.repetition,
      unit: reading.unit,
    })));
  }, [existingReadings]);

  const saveMutation = useMutation({
    mutationFn: (data: { readings: Reading[] }) =>
      api.post(`/calibration/${calibrationId}/readings`, data).then(r => unwrapData(r)),
    onSuccess: () => {
      toast.success('Leituras salvas com sucesso');
      qc.invalidateQueries({ queryKey: ['calibration-readings'] });
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar leituras')),
  });

  const generateCertMutation = useMutation({
    mutationFn: () => api.post(`/calibration/${calibrationId}/generate-certificate`).then(r => unwrapData<{ certificate_number?: string }>(r)),
    onSuccess: (data) => {
      toast.success(`Certificado ${data.certificate_number} gerado com sucesso!`);
      qc.invalidateQueries({ queryKey: ['calibration-readings'] });
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao gerar certificado')),
  });

  const addReading = () => {
    setReadings(prev => [...prev, {
      reference_value: '', indication_increasing: '', indication_decreasing: '',
      k_factor: '2.00', repetition: 1, unit: 'kg',
    }]);
  };

  const removeReading = (index: number) => {
    setReadings(prev => (prev || []).filter((_, i) => i !== index));
  };

  const updateReading = (index: number, field: keyof Reading, value: string | number) => {
    setReadings(prev => (prev || []).map((r, i) => i === index ? { ...r, [field]: value } : r));
  };

  const validateReadings = (): boolean => {
    const result = readingsFormSchema.safeParse({ readings });
    const newFieldErrors: ReadingErrors = {};
    const newFormErrors: FormErrors = {};

    if (!result.success) {
      for (const issue of result.error.issues) {
        const path = issue.path;
        if (path[0] === 'readings' && typeof path[1] === 'number' && typeof path[2] === 'string') {
          const idx = path[1];
          if (!newFieldErrors[idx]) newFieldErrors[idx] = {};
          newFieldErrors[idx][path[2]] = issue.message;
        } else if (path[0] === 'readings' && path.length === 1) {
          newFormErrors.readings = issue.message;
        }
      }
      setFieldErrors(newFieldErrors);
      setFormErrors(newFormErrors);
      return false;
    }

    setFieldErrors({});
    setFormErrors({});
    return true;
  };

  const handleSave = () => {
    if (!validateReadings()) {
      toast.error('Corrija os erros de validação antes de salvar');
      return;
    }
    const valid = (readings || []).filter(r => r.reference_value);
    saveMutation.mutate({ readings: valid });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Leituras de Calibração"
        subtitle={`Calibração #${calibrationId} — Dados para certificado de calibração`}
      />

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Resultados da Calibração</CardTitle>
          <Button size="sm" variant="outline" onClick={addReading}>
            <Plus className="h-4 w-4 mr-1" /> Adicionar Leitura
          </Button>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left">
                  <th className="p-2">#</th>
                  <th className="p-2">Valor Referência</th>
                  <th className="p-2">Indicação Crescente</th>
                  <th className="p-2">Indicação Decrescente</th>
                  <th className="p-2">Fator k</th>
                  <th className="p-2">Repetição</th>
                  <th className="p-2">Unid.</th>
                  <th className="p-2">Erro Calculado</th>
                  <th className="p-2"></th>
                </tr>
              </thead>
              <tbody>
                {(readings || []).map((r, i) => {
                  const error = r.indication_increasing && r.reference_value
                    ? (parseFloat(r.indication_increasing) - parseFloat(r.reference_value)).toFixed(4)
                    : '—';
                  return (
                    <tr key={i} className="border-b hover:bg-muted/50">
                      <td className="p-2 text-muted-foreground">{i + 1}</td>
                      <td className="p-2">
                        <Input type="number" step="0.0001" value={r.reference_value}
                          onChange={e => updateReading(i, 'reference_value', e.target.value)}
                          className={`w-32 ${fieldErrors[i]?.reference_value ? 'border-red-500 ring-1 ring-red-500/50' : ''}`} placeholder="0.0000" />
                        {fieldErrors[i]?.reference_value && (
                          <p className="text-xs text-red-500 mt-0.5">{fieldErrors[i].reference_value}</p>
                        )}
                      </td>
                      <td className="p-2">
                        <Input type="number" step="0.0001" value={r.indication_increasing}
                          onChange={e => updateReading(i, 'indication_increasing', e.target.value)}
                          className={`w-32 ${fieldErrors[i]?.indication_increasing ? 'border-red-500 ring-1 ring-red-500/50' : ''}`} placeholder="0.0000" />
                        {fieldErrors[i]?.indication_increasing && (
                          <p className="text-xs text-red-500 mt-0.5">{fieldErrors[i].indication_increasing}</p>
                        )}
                      </td>
                      <td className="p-2">
                        <Input type="number" step="0.0001" value={r.indication_decreasing}
                          onChange={e => updateReading(i, 'indication_decreasing', e.target.value)}
                          className="w-32" placeholder="0.0000" />
                      </td>
                      <td className="p-2">
                        <Input type="number" step="0.01" value={r.k_factor}
                          onChange={e => updateReading(i, 'k_factor', e.target.value)}
                          className="w-20" />
                      </td>
                      <td className="p-2">
                        <Input type="number" min={1} value={r.repetition}
                          onChange={e => updateReading(i, 'repetition', parseInt(e.target.value) || 1)}
                          className="w-16" />
                      </td>
                      <td className="p-2">
                        <select value={r.unit} onChange={e => updateReading(i, 'unit', e.target.value)}
                          className="border rounded px-2 py-1 text-sm" aria-label="Unidade da leitura">
                          <option value="kg">kg</option>
                          <option value="g">g</option>
                          <option value="mg">mg</option>
                        </select>
                      </td>
                      <td className="p-2 font-mono text-right">
                        <span className={parseFloat(error) !== 0 ? (Math.abs(parseFloat(error)) > 1 ? 'text-red-600' : 'text-yellow-600') : 'text-green-600'}>
                          {error}
                        </span>
                      </td>
                      <td className="p-2">
                        <Button size="icon" variant="ghost" onClick={() => removeReading(i)} disabled={readings.length <= 1} aria-label="Remover leitura">
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {formErrors.readings && (
            <p className="text-sm text-red-500 mt-2">{formErrors.readings}</p>
          )}

          <div className="flex gap-3 mt-6 justify-end">
            <Button variant="outline" onClick={() => navigate(-1)}>Voltar</Button>
            <Button onClick={handleSave} disabled={saveMutation.isPending}>
              <Save className="h-4 w-4 mr-1" />
              {saveMutation.isPending ? 'Salvando...' : 'Salvar Leituras'}
            </Button>
            <Button variant="default" onClick={() => generateCertMutation.mutate()} disabled={generateCertMutation.isPending}>
              <FileCheck className="h-4 w-4 mr-1" />
              {generateCertMutation.isPending ? 'Gerando...' : 'Gerar Certificado'}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
