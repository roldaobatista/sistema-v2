import { useState, useEffect } from 'react'
import { MapPin, Save, Loader2, Navigation, Ruler, KeyRound, Bell, MessageSquare, Mail } from 'lucide-react'
import { useBaseConfig, useUpdateBaseConfig, type InmetroBaseConfig } from '@/hooks/useInmetro'
import { toast } from 'sonner'

export function InmetroBaseConfigSection() {
    const { data: config, isLoading } = useBaseConfig()
    const updateMutation = useUpdateBaseConfig()

    const [form, setForm] = useState({
        base_lat: '',
        base_lng: '',
        base_address: '',
        base_city: '',
        base_state: '',
        max_distance_km: 200,
        psieUsername: '',
        psie_password: '',
        notification_roles: '' as string,
        whatsapp_message_template: '',
        email_subject_template: '',
        email_body_template: '',
    })

    useEffect(() => {
        if (config) {
            setForm({
                base_lat: config.base_lat?.toString() ?? '',
                base_lng: config.base_lng?.toString() ?? '',
                base_address: config.base_address ?? '',
                base_city: config.base_city ?? '',
                base_state: config.base_state ?? '',
                max_distance_km: config.max_distance_km ?? 200,
                psieUsername: config.psieUsername ?? '',
                psie_password: config.psie_password ?? '',
                notification_roles: (config.notification_roles ?? []).join(', '),
                whatsapp_message_template: config.whatsapp_message_template ?? '',
                email_subject_template: config.email_subject_template ?? '',
                email_body_template: config.email_body_template ?? '',
            })
        }
    }, [config])

    const handleSave = () => {
        updateMutation.mutate({
            base_lat: form.base_lat ? parseFloat(form.base_lat) : null,
            base_lng: form.base_lng ? parseFloat(form.base_lng) : null,
            base_address: form.base_address || null,
            base_city: form.base_city || null,
            base_state: form.base_state || null,
            max_distance_km: form.max_distance_km,
            psieUsername: form.psieUsername || null,
            psie_password: form.psie_password || null,
            notification_roles: form.notification_roles ? form.notification_roles.split(',').map(r => r.trim()).filter(Boolean) : null,
            whatsapp_message_template: form.whatsapp_message_template || null,
            email_subject_template: form.email_subject_template || null,
            email_body_template: form.email_body_template || null,
        } as Partial<InmetroBaseConfig>)
    }

    const handleGetCurrentLocation = () => {
        if (!navigator.geolocation) {
            toast.error('Geolocalização não suportada pelo navegador')
            return
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setForm(prev => ({
                    ...prev,
                    base_lat: pos.coords.latitude.toFixed(7),
                    base_lng: pos.coords.longitude.toFixed(7),
                }))
                toast.success('Localização capturada')
            },
            () => toast.error('Não foi possível obter localização'),
            { enableHighAccuracy: true }
        )
    }

    if (isLoading) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-6 animate-pulse">
                <div className="h-6 w-48 bg-surface-100 rounded mb-4" />
                <div className="space-y-3">
                    <div className="h-10 bg-surface-100 rounded" />
                    <div className="h-10 bg-surface-100 rounded" />
                </div>
            </div>
        )
    }

    return (
        <div className="rounded-xl border border-default bg-surface-0 overflow-hidden">
            <div className="px-5 py-4 border-b border-default bg-surface-25">
                <div className="flex items-center gap-2">
                    <MapPin className="h-5 w-5 text-brand-600" />
                    <h3 className="font-semibold text-surface-900">Base de Operações</h3>
                </div>
                <p className="text-xs text-surface-500 mt-1">
                    Configure a localização da sua base para cálculo de distâncias e priorização geográfica.
                </p>
            </div>

            <div className="p-5 space-y-4">
                {/* Address */}
                <div>
                    <label htmlFor="base_address" className="block text-sm font-medium text-surface-700 mb-1">
                        Endereço
                    </label>
                    <input
                        id="base_address"
                        type="text"
                        value={form.base_address}
                        onChange={e => setForm(prev => ({ ...prev, base_address: e.target.value }))}
                        placeholder="Rua, número, bairro..."
                        className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                    />
                </div>

                {/* City + State */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="col-span-2">
                        <label htmlFor="base_city" className="block text-sm font-medium text-surface-700 mb-1">
                            Cidade
                        </label>
                        <input
                            id="base_city"
                            type="text"
                            value={form.base_city}
                            onChange={e => setForm(prev => ({ ...prev, base_city: e.target.value }))}
                            placeholder="São Paulo"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label htmlFor="base_state" className="block text-sm font-medium text-surface-700 mb-1">
                            UF
                        </label>
                        <input
                            id="base_state"
                            type="text"
                            value={form.base_state}
                            onChange={e => setForm(prev => ({ ...prev, base_state: e.target.value.toUpperCase().slice(0, 2) }))}
                            placeholder="SP"
                            maxLength={2}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm uppercase"
                        />
                    </div>
                </div>

                {/* Coordinates */}
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="base_lat" className="block text-sm font-medium text-surface-700 mb-1">
                            Latitude
                        </label>
                        <input
                            id="base_lat"
                            type="number"
                            step="0.0000001"
                            value={form.base_lat}
                            onChange={e => setForm(prev => ({ ...prev, base_lat: e.target.value }))}
                            placeholder="-23.5505199"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm font-mono"
                        />
                    </div>
                    <div>
                        <label htmlFor="base_lng" className="block text-sm font-medium text-surface-700 mb-1">
                            Longitude
                        </label>
                        <input
                            id="base_lng"
                            type="number"
                            step="0.0000001"
                            value={form.base_lng}
                            onChange={e => setForm(prev => ({ ...prev, base_lng: e.target.value }))}
                            placeholder="-46.6333094"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm font-mono"
                        />
                    </div>
                </div>

                <button
                    onClick={handleGetCurrentLocation}
                    type="button"
                    className="inline-flex items-center gap-1.5 text-xs text-brand-600 hover:text-brand-700 font-medium"
                >
                    <Navigation className="h-3.5 w-3.5" />
                    Usar minha localização atual
                </button>

                {/* Max Distance */}
                <div>
                    <label htmlFor="max_distance" className="block text-sm font-medium text-surface-700 mb-1">
                        <Ruler className="h-4 w-4 inline mr-1" />
                        Raio máximo (km)
                    </label>
                    <div className="flex items-center gap-3">
                        <input
                            id="max_distance"
                            type="range"
                            min={10}
                            max={2000}
                            step={10}
                            value={form.max_distance_km}
                            onChange={e => setForm(prev => ({ ...prev, max_distance_km: parseInt(e.target.value) }))}
                            className="flex-1 accent-brand-600"
                        />
                        <span className="text-sm font-semibold text-surface-700 min-w-[60px] text-right">
                            {form.max_distance_km} km
                        </span>
                    </div>
                    <p className="text-xs text-surface-400 mt-1">
                        Leads dentro deste raio receberão prioridade geográfica.
                    </p>
                </div>

                {/* Mini map preview */}
                {form.base_lat && form.base_lng && (
                    <div className="rounded-lg border border-default overflow-hidden">
                        <img
                            src={`https://maps.googleapis.com/maps/api/staticmap?center=${form.base_lat},${form.base_lng}&zoom=12&size=400x200&markers=color:red|${form.base_lat},${form.base_lng}&key=`}
                            alt="Localização da base"
                            className="w-full h-[200px] object-cover bg-surface-100"
                            onError={e => {
                                (e.target as HTMLImageElement).style.display = 'none'
                            }}
                        />
                    </div>
                )}

                {/* Save */}
                <div className="flex justify-end pt-2">
                    <button
                        onClick={handleSave}
                        disabled={updateMutation.isPending}
                        className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                    >
                        {updateMutation.isPending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        Salvar Base
                    </button>
                </div>
            </div>

            {/* PSIE Credentials Section */}
            <div className="px-5 py-4 border-t border-default bg-surface-25">
                <div className="flex items-center gap-2">
                    <KeyRound className="h-5 w-5 text-amber-600" />
                    <h3 className="font-semibold text-surface-900">Credenciais PSIE</h3>
                </div>
                <p className="text-xs text-surface-500 mt-1">
                    Login do portal PSIE do INMETRO para scraping autenticado (bypass de CAPTCHA).
                </p>
            </div>
            <div className="p-5 space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="psieUsername" className="block text-sm font-medium text-surface-700 mb-1">Usuário PSIE</label>
                        <input
                            id="psieUsername"
                            type="text"
                            value={form.psieUsername}
                            onChange={e => setForm(prev => ({ ...prev, psieUsername: e.target.value }))}
                            placeholder="Login do portal PSIE"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label htmlFor="psie_password" className="block text-sm font-medium text-surface-700 mb-1">Senha PSIE</label>
                        <input
                            id="psie_password"
                            type="password"
                            value={form.psie_password}
                            onChange={e => setForm(prev => ({ ...prev, psie_password: e.target.value }))}
                            placeholder="••••••••"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                    </div>
                </div>
                <p className="text-xs text-surface-400">As credenciais são armazenadas de forma criptografada no banco de dados.</p>
            </div>

            {/* Notification Config Section */}
            <div className="px-5 py-4 border-t border-default bg-surface-25">
                <div className="flex items-center gap-2">
                    <Bell className="h-5 w-5 text-blue-600" />
                    <h3 className="font-semibold text-surface-900">Notificações e Templates</h3>
                </div>
                <p className="text-xs text-surface-500 mt-1">
                    Roles que recebem alertas e modelos de mensagem para contato com proprietários.
                </p>
            </div>
            <div className="p-5 space-y-4">
                <div>
                    <label htmlFor="notification_roles" className="block text-sm font-medium text-surface-700 mb-1">
                        <Bell className="h-4 w-4 inline mr-1" /> Roles de notificação
                    </label>
                    <input
                        id="notification_roles"
                        type="text"
                        value={form.notification_roles}
                        onChange={e => setForm(prev => ({ ...prev, notification_roles: e.target.value }))}
                        placeholder="admin, comercial, secretaria, vendedor"
                        className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                    />
                    <p className="text-xs text-surface-400 mt-1">Separe múltiplos roles por vírgula.</p>
                </div>

                <div>
                    <label htmlFor="whatsapp_template" className="block text-sm font-medium text-surface-700 mb-1">
                        <MessageSquare className="h-4 w-4 inline mr-1" /> Template WhatsApp
                    </label>
                    <textarea
                        id="whatsapp_template"
                        rows={3}
                        value={form.whatsapp_message_template}
                        onChange={e => setForm(prev => ({ ...prev, whatsapp_message_template: e.target.value }))}
                        placeholder="Olá {nome}, somos da Solution e identificamos que seu instrumento necessita de verificação..."
                        className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                    />
                    <p className="text-xs text-surface-400 mt-1">Variáveis: {'{nome}'}, {'{documento}'}, {'{instrumento}'}, {'{status}'}</p>
                </div>

                <div>
                    <label htmlFor="email_subject" className="block text-sm font-medium text-surface-700 mb-1">
                        <Mail className="h-4 w-4 inline mr-1" /> Assunto do Email
                    </label>
                    <input
                        id="email_subject"
                        type="text"
                        value={form.email_subject_template}
                        onChange={e => setForm(prev => ({ ...prev, email_subject_template: e.target.value }))}
                        placeholder="Serviço de Verificação Metrológica — {nome}"
                        className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                    />
                </div>

                <div>
                    <label htmlFor="email_body" className="block text-sm font-medium text-surface-700 mb-1">
                        Corpo do Email
                    </label>
                    <textarea
                        id="email_body"
                        rows={5}
                        value={form.email_body_template}
                        onChange={e => setForm(prev => ({ ...prev, email_body_template: e.target.value }))}
                        placeholder="Prezado(a) {nome}, somos uma empresa permissionária do INMETRO..."
                        className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                    />
                    <p className="text-xs text-surface-400 mt-1">Variáveis: {'{nome}'}, {'{documento}'}, {'{instrumento}'}, {'{status}'}, {'{empresa}'}</p>
                </div>

                {/* Save All */}
                <div className="flex justify-end pt-2">
                    <button
                        onClick={handleSave}
                        disabled={updateMutation.isPending}
                        className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                    >
                        {updateMutation.isPending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        Salvar Tudo
                    </button>
                </div>
            </div>
        </div>
    )
}
