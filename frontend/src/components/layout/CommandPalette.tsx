import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command'
import {
    LayoutDashboard, FileText, Users, Package, DollarSign, Scale, Settings,
    Search, Briefcase, Phone, Wrench, Calendar, BarChart3, Plus,
    Upload, Warehouse, BookOpen, Wand2, Shield, History,
    Activity,
} from 'lucide-react'

interface CommandRoute {
    label: string
    path: string
    icon: React.ElementType
    group: string
    keywords?: string[]
}

const routes: CommandRoute[] = [
    // Quick Actions
    { label: 'Nova OS', path: '/os/nova', icon: Plus, group: 'Ações Rápidas', keywords: ['criar', 'ordem', 'serviço'] },
    { label: 'Novo Orçamento', path: '/orcamentos/novo', icon: Plus, group: 'Ações Rápidas', keywords: ['criar', 'orçamento'] },
    { label: 'Novo Cliente', path: '/cadastros/clientes', icon: Plus, group: 'Ações Rápidas', keywords: ['criar', 'customer'] },

    // Navigation
    { label: 'Dashboard', path: '/', icon: LayoutDashboard, group: 'Navegação' },
    { label: 'CRM - Pipeline', path: '/crm/pipeline', icon: Briefcase, group: 'Navegação', keywords: ['vendas', 'deals'] },
    { label: 'Ordens de Serviço', path: '/os', icon: FileText, group: 'Navegação', keywords: ['os', 'work order'] },
    { label: 'OS Kanban', path: '/os/kanban', icon: FileText, group: 'Navegação', keywords: ['board', 'quadro'] },
    { label: 'Orçamentos', path: '/orcamentos', icon: FileText, group: 'Navegação', keywords: ['quotes'] },
    { label: 'Chamados', path: '/chamados', icon: Phone, group: 'Navegação', keywords: ['service calls', 'suporte'] },
    { label: 'Clientes', path: '/cadastros/clientes', icon: Users, group: 'Navegação', keywords: ['customers', 'cadastro'] },
    { label: 'Produtos', path: '/cadastros/produtos', icon: Package, group: 'Navegação', keywords: ['itens'] },
    { label: 'Fornecedores', path: '/cadastros/fornecedores', icon: Package, group: 'Navegação', keywords: ['suppliers'] },
    { label: 'Catálogo', path: '/catalogo', icon: BookOpen, group: 'Navegação', keywords: ['catalog', 'serviços', 'link'] },
    { label: 'Técnicos - Agenda', path: '/tecnicos/agenda', icon: Wrench, group: 'Navegação', keywords: ['schedule'] },
    { label: 'Equipamentos', path: '/equipamentos', icon: Scale, group: 'Navegação', keywords: ['instruments', 'calibração'] },
    { label: 'Pesos Padrão', path: '/equipamentos/pesos-padrao', icon: Scale, group: 'Navegação', keywords: ['standard weights', 'peso'] },
    { label: 'Agenda Calibrações', path: '/agenda-calibracoes', icon: Calendar, group: 'Navegação' },
    { label: 'Wizard Calibração', path: '/calibracao/wizard/0', icon: Wand2, group: 'Navegação', keywords: ['certificado', 'calibrar', 'balança', 'wizard'] },

    // Finance
    { label: 'Contas a Receber', path: '/financeiro/receber', icon: DollarSign, group: 'Financeiro', keywords: ['receivables'] },
    { label: 'Contas a Pagar', path: '/financeiro/pagar', icon: DollarSign, group: 'Financeiro', keywords: ['payables'] },
    { label: 'Fluxo de Caixa', path: '/financeiro/fluxo-caixa', icon: DollarSign, group: 'Financeiro', keywords: ['cashflow'] },
    { label: 'Comissões', path: '/financeiro/comissoes', icon: DollarSign, group: 'Financeiro' },
    { label: 'Despesas', path: '/financeiro/despesas', icon: DollarSign, group: 'Financeiro', keywords: ['expenses'] },
    { label: 'Conciliação Bancária', path: '/financeiro/conciliacao-bancaria', icon: DollarSign, group: 'Financeiro', keywords: ['reconciliation', 'conciliação'] },
    { label: 'DRE', path: '/financeiro/dre', icon: BarChart3, group: 'Financeiro', keywords: ['resultado', 'exercício'] },
    { label: 'Cheques', path: '/financeiro/cheques', icon: DollarSign, group: 'Financeiro', keywords: ['checks', 'cheque'] },
    { label: 'Plano de Contas', path: '/financeiro/plano-contas', icon: DollarSign, group: 'Financeiro', keywords: ['chart', 'accounts', 'plano'] },
    { label: 'Faturamento', path: '/financeiro/faturamento', icon: FileText, group: 'Financeiro', keywords: ['invoices', 'notas', 'nf'] },
    { label: 'Contas Bancárias', path: '/financeiro/contas-bancarias', icon: DollarSign, group: 'Financeiro', keywords: ['bank', 'banco'] },
    { label: 'Consolidado', path: '/financeiro/consolidado', icon: BarChart3, group: 'Financeiro', keywords: ['consolidated', 'visao geral'] },
    { label: 'Renegociação', path: '/financeiro/renegociacao', icon: DollarSign, group: 'Financeiro', keywords: ['debt', 'dívida', 'renegociação'] },
    { label: 'Aprovação em Lote', path: '/financeiro/aprovacao-lote', icon: DollarSign, group: 'Financeiro', keywords: ['batch', 'approval', 'lote'] },
    { label: 'Calculadora Tributos', path: '/financeiro/calculadora-tributos', icon: DollarSign, group: 'Financeiro', keywords: ['tax', 'imposto', 'tributo'] },
    { label: 'Relatórios', path: '/relatorios', icon: BarChart3, group: 'Financeiro' },

    // Admin
    { label: 'Estoque', path: '/estoque', icon: Warehouse, group: 'Administração', keywords: ['inventory'] },
    { label: 'Importação', path: '/importacao', icon: Upload, group: 'Administração' },
    { label: 'Intel. INMETRO', path: '/inmetro', icon: Search, group: 'Administração' },
    { label: 'Usuários', path: '/iam/usuarios', icon: Users, group: 'Administração', keywords: ['users', 'iam'] },
    { label: 'Roles', path: '/iam/roles', icon: Shield, group: 'Administração', keywords: ['perfis', 'cargos', 'grupos', 'iam'] },
    { label: 'Permissões', path: '/iam/permissoes', icon: Shield, group: 'Administração', keywords: ['acessos', 'matriz', 'iam'] },
    { label: 'Auditoria IAM', path: '/iam/auditoria', icon: History, group: 'Administração', keywords: ['logs', 'histórico', 'rastreabilidade', 'iam'] },
    { label: 'Configurações', path: '/configuracoes', icon: Settings, group: 'Administração', keywords: ['settings'] },
    { label: 'Observabilidade', path: '/configuracoes/observabilidade', icon: Activity, group: 'Administração', keywords: ['monitoramento', 'health', 'latência', 'logs'] },
]

export function CommandPalette() {
    const [open, setOpen] = useState(false)
    const navigate = useNavigate()

    const handleKeyDown = useCallback((e: KeyboardEvent) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault()
            setOpen(prev => !prev)
        }
    }, [])

    useEffect(() => {
        document.addEventListener('keydown', handleKeyDown)
        return () => document.removeEventListener('keydown', handleKeyDown)
    }, [handleKeyDown])

    const handleSelect = (path: string) => {
        setOpen(false)
        navigate(path)
    }

    const groups = routes.reduce<Record<string, CommandRoute[]>>((acc, route) => {
        if (!acc[route.group]) acc[route.group] = []
        acc[route.group].push(route)
        return acc
    }, {})

    return (
        <CommandDialog open={open} onOpenChange={setOpen}>
            <CommandInput placeholder="Buscar páginas, ações, módulos..." />
            <CommandList>
                <CommandEmpty>
                    <div className="flex flex-col items-center gap-1 py-4">
                        <Search className="h-8 w-8 text-surface-300" />
                        <p className="text-[13px] text-surface-500">Nenhum resultado encontrado.</p>
                    </div>
                </CommandEmpty>
                {Object.entries(groups).map(([group, items], idx) => (
                    <div key={group}>
                        {idx > 0 && <CommandSeparator />}
                        <CommandGroup heading={group}>
                            {(items || []).map((item) => (
                                <CommandItem
                                    key={item.path}
                                    value={`${item.label} ${item.keywords?.join(' ') ?? ''}`}
                                    onSelect={() => handleSelect(item.path)}
                                    className="cursor-pointer"
                                >
                                    <item.icon className="mr-2 h-4 w-4 text-surface-400" />
                                    <span>{item.label}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </div>
                ))}
            </CommandList>
        </CommandDialog>
    )
}
