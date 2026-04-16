import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    User, Shield, Building2, Key, Save, CheckCircle, Eye, EyeOff,
    Bell, Moon,
} from 'lucide-react'
import { toast } from 'sonner'
import api, { getApiErrorMessage } from '@/lib/api'
import { maskPhone } from '@/lib/form-masks'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { useAuthStore } from '@/stores/auth-store'
import { PushNotificationSettings } from '@/components/pwa/PushNotificationSettings'
import { resetModeSelection } from '@/components/pwa/ModeSelectionScreen'

type ValidationErrorPayload = {
    message?: string
    error?: string
    errors?: Record<string, string[]>
}

function getFirstValidationError(err: unknown): string | null {
    const response = (err as { response?: { status?: number; data?: ValidationErrorPayload } })?.response
    if (response?.status !== 422 || !response.data?.errors) {
        return null
    }

    return Object.values(response.data.errors).flat().find((message): message is string => typeof message === 'string') ?? null
}

export function ProfilePage() {
    const { hasPermission } = useAuthStore()

    const qc = useQueryClient()
    const { setUser } = useAuthStore()
    const [showPassword, setShowPassword] = useState(false)
    const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' })
    const [saved, setSaved] = useState(false)
    const [pwSaved, setPwSaved] = useState(false)

    const { data: res, isLoading } = useQuery({
        queryKey: ['profile'],
        queryFn: () => api.get('/profile'),
    })
    const profile = res?.data?.data?.user ?? res?.data?.user ?? res?.data

    const [form, setForm] = useState<{ name: string; email: string; phone: string }>({
        name: '', email: '', phone: '',
    })

    useEffect(() => {
        if (profile) {
            setForm({ name: profile.name ?? '', email: profile.email ?? '', phone: profile.phone ? maskPhone(profile.phone) : '' })
        }
    }, [profile])

    const updateMut = useMutation({
        mutationFn: (data: typeof form) => api.put('/profile', data),
        onSuccess: (res) => {
            qc.invalidateQueries({ queryKey: ['profile'] })
            if (res.data?.user) setUser(res.data.user)
            setSaved(true)
            setTimeout(() => setSaved(false), 3000)
        },
        onError: (err: unknown) => {
            toast.error(getFirstValidationError(err) ?? getApiErrorMessage(err, 'Erro ao atualizar perfil.'))
        },
    })

    const passwordMut = useMutation({
        mutationFn: (data: typeof passwordForm) => api.post('/profile/change-password', data),
        onSuccess: () => {
            toast.success('Senha alterada com sucesso!')
            setPwSaved(true)
            setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' })
            setTimeout(() => setPwSaved(false), 3000)
        },
        onError: (err: unknown) => {
            toast.error(getFirstValidationError(err) ?? getApiErrorMessage(err, 'Erro ao alterar senha.'))
        },
    })

    const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm(f => ({ ...f, [key]: key === 'phone' ? maskPhone(e.target.value) : e.target.value }))

    const setPw = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setPasswordForm(f => ({ ...f, [key]: e.target.value }))

    if (isLoading || !profile) {
        return (
            <div className="mx-auto max-w-3xl space-y-5 animate-fade-in">
                <div className="flex items-center gap-4">
                    <div className="skeleton h-16 w-16 rounded-2xl" />
                    <div>
                        <div className="skeleton h-7 w-40" />
                        <div className="skeleton mt-2 h-4 w-56" />
                    </div>
                </div>
                <div className="skeleton h-56 rounded-xl" />
                <div className="skeleton h-48 rounded-xl" />
                <div className="skeleton h-36 rounded-xl" />
            </div>
        )
    }

    return (
        <div className="mx-auto max-w-3xl space-y-5 animate-fade-in">
            {/* Header */}
            <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-2xl font-bold text-white shadow-lg">
                    {profile.name?.charAt(0)?.toUpperCase() ?? 'U'}
                </div>
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">{profile.name}</h1>
                    <p className="text-[13px] text-surface-500">{profile.email}</p>
                    <div className="mt-1 flex items-center gap-2">
                        {(profile.roles || []).map((r: string) => (
                            <Badge key={r} variant="brand">{r}</Badge>
                        ))}
                    </div>
                </div>
            </div>

            {/* Dados pessoais */}
            <div className="animate-slide-up rounded-xl border border-default bg-surface-0 p-6 shadow-card hover:shadow-elevated transition-shadow duration-200">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                    <User className="h-5 w-5 text-brand-500" />
                    Dados Pessoais
                </h2>
                <form onSubmit={e => { e.preventDefault(); updateMut.mutate(form) }} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Nome" value={form.name} onChange={set('name')} required />
                        <Input label="Telefone" value={form.phone} onChange={set('phone')} maxLength={15} inputMode="tel" placeholder="(00) 00000-0000" />
                    </div>
                    <Input label="E-mail" type="email" value={form.email} onChange={set('email')} required />
                    <div className="flex items-center justify-between">
                        {saved && (
                            <span className="flex items-center gap-1 text-sm font-medium text-emerald-600">
                                <CheckCircle className="h-4 w-4" /> Salvo com sucesso
                            </span>
                        )}
                        <div className="ml-auto" />
                        <Button type="submit" loading={updateMut.isPending} icon={<Save className="h-4 w-4" />}>
                            Salvar Alterações
                        </Button>
                    </div>
                </form>
            </div>

            {/* Alterar Senha */}
            <div className="animate-slide-up stagger-2 rounded-xl border border-default bg-surface-0 p-6 shadow-card hover:shadow-elevated transition-shadow duration-200">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                    <Key className="h-5 w-5 text-brand-500" />
                    Alterar Senha
                </h2>
                <form onSubmit={e => { e.preventDefault(); passwordMut.mutate(passwordForm) }} className="space-y-4">
                    <Input
                        label="Senha Atual"
                        type={showPassword ? 'text' : 'password'}
                        value={passwordForm.current_password}
                        onChange={setPw('current_password')}
                        required
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Nova Senha"
                            type={showPassword ? 'text' : 'password'}
                            value={passwordForm.new_password}
                            onChange={setPw('new_password')}
                            required
                        />
                        <Input
                            label="Confirmar Nova Senha"
                            type={showPassword ? 'text' : 'password'}
                            value={passwordForm.new_password_confirmation}
                            onChange={setPw('new_password_confirmation')}
                            required
                        />
                    </div>
                    <div className="flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="flex items-center gap-1.5 text-xs text-surface-500 hover:text-surface-700"
                        >
                            {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                            {showPassword ? 'Ocultar senhas' : 'Mostrar senhas'}
                        </button>
                        <div className="flex items-center gap-3">
                            {pwSaved && (
                                <span className="flex items-center gap-1 text-sm font-medium text-emerald-600">
                                    <CheckCircle className="h-4 w-4" /> Senha alterada
                                </span>
                            )}
                            <Button type="submit" loading={passwordMut.isPending} icon={<Save className="h-4 w-4" />}>
                                Alterar Senha
                            </Button>
                        </div>
                    </div>
                    {passwordMut.isError && (
                        <p className="text-sm text-red-600">
                            {(passwordMut.error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Erro ao alterar senha'}
                        </p>
                    )}
                </form>
            </div>

            {/* Info card */}
            <div className="animate-slide-up stagger-3 rounded-xl border border-default bg-surface-0 p-6 shadow-card hover:shadow-elevated transition-shadow duration-200">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                    <Shield className="h-5 w-5 text-brand-500" />
                    Informações da Conta
                </h2>
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span className="text-surface-500">Tenant</span>
                        <p className="mt-0.5 flex items-center gap-1.5 font-medium text-surface-800">
                            <Building2 className="h-3.5 w-3.5 text-brand-500" />
                            {profile.tenant?.name ?? '—'}
                        </p>
                    </div>
                    <div>
                        <span className="text-surface-500">Permissões</span>
                        <p className="mt-0.5 font-medium text-surface-800">{profile.permissions?.length ?? 0} atribuídas</p>
                    </div>
                    <div>
                        <span className="text-surface-500">Membro desde</span>
                        <p className="mt-0.5 font-medium text-surface-800">
                            {profile.created_at ? new Date(profile.created_at).toLocaleDateString('pt-BR') : '—'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Preferências de Modo */}
            <div className="animate-slide-up stagger-4 rounded-xl border border-default bg-surface-0 p-6 shadow-card hover:shadow-elevated transition-shadow duration-200">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                    <Shield className="h-5 w-5 text-brand-500" />
                    Preferências de Modo
                </h2>
                <p className="text-sm text-surface-500 mb-4">
                    Se você marcou "Lembrar minha escolha" na seleção de modo, pode resetar aqui para voltar a ver a tela de escolha no próximo login.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        resetModeSelection()
                        toast.success('Preferência de modo resetada. Na próxima vez que acessar, você poderá escolher novamente.')
                    }}
                >
                    Resetar Preferência de Modo
                </Button>
            </div>

            {/* Preferências de Notificação da Central */}
            <CentralNotificationPrefsSection />

            {/* Notificações Push */}
            <div className="animate-slide-up stagger-5">
                <PushNotificationSettings />
            </div>
        </div>
    )
}

function CentralNotificationPrefsSection() {
    const { data: prefsRes, isLoading } = useQuery({
        queryKey: ['central-notification-prefs'],
        queryFn: () => api.get('/agenda/notification-prefs'),
    })
    const prefs = prefsRes?.data

    const updateMut = useMutation({
        mutationFn: (data: Record<string, boolean | string | { start: string; end: string } | null>) => api.patch('/agenda/notification-prefs', data),
        onSuccess: () => toast.success('Preferências salvas'),
        onError: () => toast.error('Erro ao salvar preferências'),
    })

    const [quietStart, setQuietStart] = useState('')
    const [quietEnd, setQuietEnd] = useState('')

    if (isLoading || !prefs) {
        return (
            <div className="animate-slide-up stagger-5 rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                    <Bell className="h-5 w-5 text-brand-500" />
                    Notificações da Central
                </h2>
                <div className="h-32 flex items-center justify-center">
                    <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" />
                </div>
            </div>
        )
    }

    const togglePref = (key: string, current: boolean) => {
        updateMut.mutate({ [key]: !current })
    }

    const toggleItems: { key: string; label: string; desc: string; value: boolean }[] = [
        { key: 'notify_assigned_to_me', label: 'Atribuído a mim', desc: 'Quando alguém atribui uma tarefa para mim', value: prefs.notify_assigned_to_me },
        { key: 'notify_created_by_me', label: 'Criado por mim', desc: 'Atualizações em tarefas que eu criei', value: prefs.notify_created_by_me },
        { key: 'notify_watching', label: 'Seguindo', desc: 'Itens onde sou seguidor/observador', value: prefs.notify_watching },
        { key: 'notify_mentioned', label: 'Mencionado', desc: 'Quando alguém me menciona (@) em comentários', value: prefs.notify_mentioned },
    ]

    const channels: { key: string; label: string; value: string; options: { v: string; l: string }[] }[] = [
        { key: 'channel_in_app', label: 'No sistema', value: prefs.channel_in_app, options: [{ v: 'on', l: 'Ativo' }, { v: 'off', l: 'Desativado' }] },
        { key: 'channel_push', label: 'Push (navegador)', value: prefs.channel_push, options: [{ v: 'on', l: 'Ativo' }, { v: 'off', l: 'Desativado' }] },
        { key: 'channel_email', label: 'E-mail', value: prefs.channel_email, options: [{ v: 'on', l: 'Ativo' }, { v: 'off', l: 'Desativado' }, { v: 'digest', l: 'Resumo' }] },
    ]

    return (
        <div className="animate-slide-up stagger-5 rounded-xl border border-default bg-surface-0 p-6 shadow-card hover:shadow-elevated transition-shadow duration-200">
            <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-surface-900">
                <Bell className="h-5 w-5 text-brand-500" />
                Notificações da Central
            </h2>
            <p className="text-sm text-surface-500 mb-5">
                Configure quando e como você recebe notificações de tarefas, lembretes e itens da Central.
            </p>

            {/* Tipos de notificação */}
            <div className="space-y-3 mb-6">
                <h3 className="text-sm font-semibold text-surface-700">Quando notificar</h3>
                {toggleItems.map(item => (
                    <label key={item.key} className="flex items-center justify-between gap-3 rounded-lg border border-default p-3 cursor-pointer hover:bg-surface-50 transition-colors">
                        <div>
                            <p className="text-sm font-medium text-surface-800">{item.label}</p>
                            <p className="text-xs text-surface-500">{item.desc}</p>
                        </div>
                        <span
                            role="switch"
                            tabIndex={0}
                            aria-checked={item.value ? 'true' : 'false'}
                            aria-label={item.label}
                            onClick={() => togglePref(item.key, item.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); togglePref(item.key, item.value) } }}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out ${item.value ? 'bg-brand-500' : 'bg-surface-200'}`}
                        >
                            <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200 ease-in-out ${item.value ? 'translate-x-5' : 'translate-x-0'}`} />
                        </span>
                    </label>
                ))}
            </div>

            {/* Canais */}
            <div className="space-y-3 mb-6">
                <h3 className="text-sm font-semibold text-surface-700">Canais de notificação</h3>
                {channels.map(ch => (
                    <div key={ch.key} className="flex items-center justify-between gap-3 rounded-lg border border-default p-3">
                        <p className="text-sm font-medium text-surface-800">{ch.label}</p>
                        <select
                            value={ch.value}
                            onChange={(e) => updateMut.mutate({ [ch.key]: e.target.value })}
                            className="rounded-lg border border-default bg-surface-50 px-3 py-1.5 text-sm"
                            aria-label={ch.label}
                        >
                            {ch.options.map(o => <option key={o.v} value={o.v}>{o.l}</option>)}
                        </select>
                    </div>
                ))}
            </div>

            {/* Horário Silencioso */}
            <div className="space-y-3">
                <h3 className="text-sm font-semibold text-surface-700 flex items-center gap-1.5">
                    <Moon className="h-4 w-4" /> Horário silencioso
                </h3>
                <p className="text-xs text-surface-500">Não enviar notificações neste período (exceto urgentes).</p>
                <div className="flex items-center gap-3">
                    <Input
                        label="Início"
                        type="time"
                        value={quietStart || prefs.quiet_hours?.start || ''}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setQuietStart(e.target.value)}
                    />
                    <span className="text-surface-400 mt-5">até</span>
                    <Input
                        label="Fim"
                        type="time"
                        value={quietEnd || prefs.quiet_hours?.end || ''}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setQuietEnd(e.target.value)}
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-5"
                        onClick={() => {
                            const start = quietStart || prefs.quiet_hours?.start
                            const end = quietEnd || prefs.quiet_hours?.end
                            if (start && end) {
                                updateMut.mutate({ quiet_hours: { start, end } })
                            } else {
                                updateMut.mutate({ quiet_hours: null })
                            }
                        }}
                    >
                        Salvar
                    </Button>
                </div>
                {prefs.quiet_hours?.start && prefs.quiet_hours?.end && (
                    <p className="text-xs text-surface-500">
                        Atualmente: {prefs.quiet_hours.start} - {prefs.quiet_hours.end}
                        <button type="button" onClick={() => updateMut.mutate({ quiet_hours: null })} className="ml-2 text-red-500 hover:text-red-700">Remover</button>
                    </p>
                )}
            </div>
        </div>
    )
}
