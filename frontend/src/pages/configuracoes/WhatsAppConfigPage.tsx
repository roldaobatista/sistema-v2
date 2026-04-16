import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import type { Resolver } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { AxiosError } from 'axios';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { getApiErrorMessage, unwrapData } from '@/lib/api';
import { maskPhone, normalizeBrazilPhone } from '@/lib/form-masks';
import { PageHeader } from '@/components/ui/pageheader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FormField } from '@/components/ui/form-field';
import { toast } from 'sonner';
import { MessageCircle, Send, CheckCircle, Settings, Eye, EyeOff } from 'lucide-react';
import { handleFormError } from '@/lib/form-utils';
import { optionalString } from '@/schemas/common';
import { z } from 'zod';

const whatsAppConfigSchema = z.object({
  provider: z.enum(['evolution', 'z-api', 'meta']).default('evolution'),
  api_url: optionalString,
  api_key: optionalString,
  instance_name: optionalString,
  phone_number: optionalString,
});

type WhatsAppConfigFormData = z.infer<typeof whatsAppConfigSchema>;

const defaultValues: WhatsAppConfigFormData = {
  provider: 'evolution',
  api_url: '',
  api_key: '',
  instance_name: '',
  phone_number: '',
};

export default function WhatsAppConfigPage() {
  const qc = useQueryClient();
  const [testPhone, setTestPhone] = useState('');
  const [showApiKey, setShowApiKey] = useState(false);

  const { register, handleSubmit, reset, setError, setValue, watch, formState: { errors } } = useForm<WhatsAppConfigFormData>({
    resolver: zodResolver(whatsAppConfigSchema) as Resolver<WhatsAppConfigFormData>,
    defaultValues,
  });

  const { data: config } = useQuery({
    queryKey: ['whatsapp-config'],
    queryFn: () => api.get('/whatsapp/config').then((r) => unwrapData(r)),
  });

  useEffect(() => {
    if (config) {
      reset({
        provider: (config.provider as 'evolution' | 'z-api' | 'meta') || 'evolution',
        api_url: config.api_url || '',
        api_key: '',
        instance_name: config.instance_name || '',
        phone_number: config.phone_number ? maskPhone(config.phone_number) : '',
      });
    }
  }, [config, reset]);

  const saveMut = useMutation({
    mutationFn: (data: WhatsAppConfigFormData) => api.post('/whatsapp/config', {
      ...data,
      phone_number: data.phone_number ? normalizeBrazilPhone(data.phone_number) : '',
    }),
    onSuccess: () => { toast.success('Configuração salva'); qc.invalidateQueries({ queryKey: ['whatsapp-config'] }); },
    onError: (err: unknown) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar configuração'),
  });

  const testMut = useMutation({
    mutationFn: (phone: string) => api.post('/whatsapp/test', { phone: normalizeBrazilPhone(phone) }).then((r) => unwrapData<{ success?: boolean }>(r)),
    onSuccess: (data) => data.success ? toast.success('Mensagem de teste enviada!') : toast.error('Falha no envio'),
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao enviar teste')),
  });

  return (
    <div className="space-y-6">
      <PageHeader title="Integração WhatsApp" subtitle="Configure a API do WhatsApp para envio de notificações e mensagens" />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2"><Settings className="h-5 w-5" /> Configuração da API</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <form onSubmit={handleSubmit((data: WhatsAppConfigFormData) => saveMut.mutate(data))} className="space-y-4">
              <FormField label="Provedor" error={errors.provider?.message}>
                <select {...register('provider')} className="w-full border rounded px-3 py-2 text-sm border-default bg-surface-50 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                  <option value="evolution">Evolution API (self-hosted)</option>
                  <option value="z-api">Z-API (SaaS)</option>
                  <option value="meta">Meta Cloud API (oficial)</option>
                </select>
              </FormField>
              <FormField label="URL da API" error={errors.api_url?.message}>
                <Input {...register('api_url')}
                  placeholder={watch('provider') === 'evolution' ? 'https://sua-evolution.com' : watch('provider') === 'z-api' ? 'https://api.z-api.io/instances/...' : 'https://graph.facebook.com/v18.0/PHONE_ID'} />
              </FormField>
              <FormField label="Chave da API" error={errors.api_key?.message}>
                <div className="relative">
                  <Input {...register('api_key')} type={showApiKey ? 'text' : 'password'}
                    placeholder={config ? '••••••• (já configurada)' : 'Cole sua chave aqui'} />
                  <button type="button" onClick={() => setShowApiKey(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 transition-colors" aria-label={showApiKey ? 'Ocultar chave' : 'Mostrar chave'}>
                    {showApiKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </FormField>
              {watch('provider') === 'evolution' && (
                <FormField label="Nome da Instância" error={errors.instance_name?.message}>
                  <Input {...register('instance_name')} placeholder="kalibrium" />
                </FormField>
              )}
              <FormField label="Número do WhatsApp" error={errors.phone_number?.message}>
                <Input
                  {...register('phone_number')}
                  placeholder="(66) 99235-6105"
                  maxLength={15}
                  inputMode="tel"
                  onChange={(e) => setValue('phone_number', maskPhone(e.target.value), { shouldDirty: true })}
                />
              </FormField>
              <Button type="submit" disabled={saveMut.isPending} className="w-full">
                {saveMut.isPending ? 'Salvando...' : 'Salvar Configuração'}
              </Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2"><MessageCircle className="h-5 w-5" /> Teste de Conexão</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {config ? (
              <>
                <div className="flex items-center gap-2 p-3 bg-green-50 rounded-lg">
                  <CheckCircle className="h-5 w-5 text-green-600" />
                  <div>
                    <p className="font-medium text-green-800">Configurado</p>
                    <p className="text-sm text-green-700">Provedor: {config.provider} | Ativo: {config.is_active ? 'Sim' : 'Não'}</p>
                  </div>
                </div>
                <div>
                  <label className="text-sm font-medium">Número para teste</label>
                  <Input value={testPhone} onChange={e => setTestPhone(maskPhone(e.target.value))} placeholder="(66) 99235-6105" maxLength={15} inputMode="tel" />
                </div>
                <Button onClick={() => testMut.mutate(testPhone)} disabled={testMut.isPending || !testPhone} className="w-full">
                  <Send className="h-4 w-4 mr-2" />
                  {testMut.isPending ? 'Enviando...' : 'Enviar Mensagem de Teste'}
                </Button>
              </>
            ) : (
              <div className="text-center py-8 text-muted-foreground">
                <MessageCircle className="h-12 w-12 mx-auto mb-3 opacity-30" />
                <p>Configure a API ao lado para habilitar o teste.</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
