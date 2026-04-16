import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    ArrowLeft, Clock, User, MapPin, ClipboardList,
    Briefcase, Package, Plus, Trash2, Pencil, Download, Save, X,
    CheckCircle2, AlertTriangle,
    DollarSign, CalendarDays, LinkIcon, Upload, Paperclip, Shield, Users, Copy, RotateCcw, Navigation, QrCode, TrendingUp, Layers, ExternalLink,
    FlaskConical, Receipt, Award,
} from 'lucide-react'
import { parseLabelQrPayload } from '@/lib/labelQr'
import { getCalibrationReadingsPath } from '@/lib/calibration-utils'
import api, { buildStorageUrl, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { fetchAddressByCep } from '@/lib/cep-api'
import { refDataApi } from '@/lib/ref-data-api'
import { safeArray } from '@/lib/safe-array'
import { workOrderStatus } from '@/lib/status-config'
import { workOrderApi } from '@/lib/work-order-api'
import type { DisplacementStop } from '@/types/work-order'
import { extractWorkOrderQrProduct, isPrivilegedFieldRole, isTechnicianLinkedToWorkOrder } from '@/lib/work-order-detail-utils'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { SignaturePad } from '@/components/signature/SignaturePad'
import { useAuthStore } from '@/stores/auth-store'
import { usePriceGate } from '@/hooks/usePriceGate'
import SLACountdown from '@/components/common/SLACountdown'
import PriceHistoryHint from '@/components/common/PriceHistoryHint'
import { ItemSearchCombobox } from '@/components/common/ItemSearchCombobox'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import AdminChatTab from '@/components/os/AdminChatTab'
import AuditTrailTab from '@/components/os/AuditTrailTab'
import GeoCheckinButton from '@/components/os/GeoCheckinButton'
import SatisfactionTab from '@/components/os/SatisfactionTab'
import { StatusTimeline } from '@/components/os/StatusTimeline'
import ExecutionTimer from '@/components/os/ExecutionTimer'
import { ExecutionActions } from '@/components/os/ExecutionActions'
import { ExecutionTimeline } from '@/components/os/ExecutionTimeline'
import BeforeAfterPhotos from '@/components/os/BeforeAfterPhotos'
import ShareOS from '@/components/os/ShareOS'
import ProfitabilityIndicator from '@/components/os/ProfitabilityIndicator'
import DragDropUpload from '@/components/os/DragDropUpload'
import TagManager from '@/components/os/TagManager'

import EquipmentHistory from '@/components/os/EquipmentHistory'
import TimeReport from '@/components/os/TimeReport'
import MissingPartsIndicator from '@/components/os/MissingPartsIndicator'
import QRTracking from '@/components/os/QRTracking'

import PhotoChecklist from '@/components/os/PhotoChecklist'
import DeliveryForecast from '@/components/os/DeliveryForecast'
import ApprovalChain from '@/components/os/ApprovalChain'
import { QrScannerModal } from '@/components/qr/QrScannerModal'
import WoExpensesTab from '@/components/os/WoExpensesTab'
import MaintenanceReportsTab from '@/components/os/MaintenanceReportsTab'

import type { ApiErrorLike } from '@/types/common'
import type {
    WorkOrder,
    WorkOrderItem,
    ItemFormPayload,
    EditFormPayload,
    ChecklistResponsePayload,
    ChecklistTemplateItem,
    ChecklistResponse,
    ProductOrService,
    WorkOrderAttachment,
    WorkOrderEquipmentRef,
    WorkOrderCalibrationRef,
    StatusHistoryEntry,
    PartsKit,
    FiscalNote,
} from '@/types/work-order'

const MAX_ATTACHMENT_SIZE_MB = 50

type BadgeVariant = 'default' | 'primary' | 'brand' | 'secondary' | 'success' | 'warning' | 'danger' | 'destructive' | 'info' | 'outline' | 'neutral'

interface WorkOrderCostEstimate {
    items: Array<{
        id: number
        type: string
        description: string
        quantity: number | string
        unit_price: number | string
        discount: number | string
        line_total: number | string
    }>
    items_subtotal: string
    items_discount: string
    displacement_value: string
    global_discount: string
    grand_total: string
    revenue: string
    total_cost: string
    profit: string
    margin_pct: number
    cost_breakdown?: {
        items_cost?: string
        displacement?: string
        commission?: string
    } | null
}

const statusConfig = workOrderStatus

const priorityConfig: Record<string, { label: string; variant: BadgeVariant }> = {
    low: { label: 'Baixa', variant: 'default' },
    normal: { label: 'Normal', variant: 'info' },
    high: { label: 'Alta', variant: 'warning' },
    urgent: { label: 'Urgente', variant: 'danger' },
}

const AGREED_PAYMENT_OPTIONS: { value: string; label: string }[] = [
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'cartao_credito', label: 'Cartão Crédito' },
    { value: 'cartao_debito', label: 'Cartão Débito' },
    { value: 'transferencia', label: 'Transferência' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'pending_after_invoice', label: 'A combinar após emissão da nota' },
]

export function WorkOrderDetailPage() {
    const { id } = useParams()
    const navigate = useNavigate()
    const qc = useQueryClient()
    const { hasPermission, user } = useAuthStore()
    const { canViewPrices } = usePriceGate()
    const canUpdate = hasPermission('os.work_order.update')
    const canChangeStatus = hasPermission('os.work_order.change_status')
    const canAuthorizeDispatch = hasPermission('os.work_order.authorize_dispatch')
    const canViewExpenses = hasPermission('expenses.expense.view')
    const canCreateExpense = hasPermission('expenses.expense.create')
    const canViewInternalChat = hasPermission('os.work_order.view')
    const canViewAuditTrail = hasPermission('os.work_order.view')
    const canViewSatisfaction = hasPermission('os.work_order.view')

    // State
    const [showStatusModal, setShowStatusModal] = useState(false)
    const [newStatus, setNewStatus] = useState('')
    const [statusNotes, setStatusNotes] = useState('')
    const [agreedPaymentMethod, setAgreedPaymentMethod] = useState('')
    const [agreedPaymentNotes, setAgreedPaymentNotes] = useState('')

    const [showItemModal, setShowItemModal] = useState(false)
    const [itemForm, setItemForm] = useState({
        type: 'service' as 'product' | 'service',
        reference_id: '' as string | number, description: '',
        quantity: '1', unit_price: '0', discount: '0',
        warehouse_id: '' as string | number,
    })
    const [editingItem, setEditingItem] = useState<WorkOrderItem | null>(null)

    // Inline editing states
    const [isEditing, setIsEditing] = useState(false)
    const [editForm, setEditForm] = useState({
        description: '', priority: '', technical_report: '', internal_notes: '',
        displacement_value: '0',
        is_warranty: false,
        assigned_to: '' as string | number | null,
        seller_id: '' as string | number | null,
        driver_id: '' as string | number | null,
        technician_ids: [] as number[],
        lead_source: '' as string,
        scheduled_date: '',
        service_type: '',
        address: '',
        city: '',
        state: '',
        zip_code: '',
        contact_phone: '',
        delivery_forecast: '',
        checklist_id: '' as string | number | null,
        branch_id: '' as string | number | null,
        tags: [] as string[],
        agreed_payment_method: '',
        agreed_payment_notes: '',
        os_number: '',
        sla_policy_id: '' as string | number | null,
    })
    const [activeTab, setActiveTab] = useState<'details' | 'checklist' | 'chat' | 'audit' | 'satisfaction' | 'expenses' | 'maintenance'>('details')

    useEffect(() => {
        if (activeTab === 'chat' && !canViewInternalChat) {
            setActiveTab('details')
        }
        if (activeTab === 'audit' && !canViewAuditTrail) {
            setActiveTab('details')
        }
        if (activeTab === 'satisfaction' && !canViewSatisfaction) {
            setActiveTab('details')
        }
    }, [activeTab, canViewAuditTrail, canViewInternalChat, canViewSatisfaction])

    // Delete confirmation state
    const [deleteItemId, setDeleteItemId] = useState<number | null>(null)
    const [deleteAttachId, setDeleteAttachId] = useState<number | null>(null)
    const [showQrScanner, setShowQrScanner] = useState(false)

    // Equipment attach/detach state
    const [showEquipmentModal, setShowEquipmentModal] = useState(false)
    const [detachEquipId, setDetachEquipId] = useState<number | null>(null)

    // Cost estimate & kit states
    const [showCostEstimate, setShowCostEstimate] = useState(false)
    const [showKitModal, setShowKitModal] = useState(false)

    // Confirmation modal states (replacing window.confirm)
    const [showUninvoiceConfirm, setShowUninvoiceConfirm] = useState(false)
    const [showReceivableConfirm, setShowReceivableConfirm] = useState(false)
    const [showDeductStockConfirm, setShowDeductStockConfirm] = useState(false)
    const [showEmitNfseConfirm, setShowEmitNfseConfirm] = useState(false)
    const [showEmitNfeConfirm, setShowEmitNfeConfirm] = useState(false)

    const woId = Number(id)
    // Queries
    const { data: res, isLoading, isError, error, refetch: refetchOrder } = useQuery({
        queryKey: queryKeys.workOrders.detail(woId),
        queryFn: () => workOrderApi.detail(woId),
    })
    const order = res ? unwrapData<WorkOrder>(res) : undefined
    const fieldRoles = Array.isArray(user?.roles)
        ? user.roles
        : (Array.isArray(user?.all_roles) ? user.all_roles : [])
    const isAdminFieldOperator = isPrivilegedFieldRole(fieldRoles)
    const technicianLinkedToWorkOrder = isTechnicianLinkedToWorkOrder(order, user?.id, isAdminFieldOperator)
    const canExecuteWorkOrderFlow = !!order && canChangeStatus && technicianLinkedToWorkOrder
    const executionBlockedMessage = !canChangeStatus
        ? 'Seu perfil nao tem permissao para executar transicoes desta OS em campo.'
        : 'Esta OS nao esta vinculada ao seu usuario tecnico.'

    const { data: productsRes } = useQuery({
        queryKey: queryKeys.products.options,
        queryFn: () => refDataApi.products(),
    })

    const { data: servicesRes } = useQuery({
        queryKey: queryKeys.services.options,
        queryFn: () => refDataApi.services(),
    })

    // Queries for team selectors (only loaded when editing)
    const { data: techsRes } = useQuery({
        queryKey: ['technicians-by-role'],
        queryFn: () => refDataApi.technicians(),
        enabled: isEditing,
    })
    const { data: allUsersRes } = useQuery({
        queryKey: ['users-for-selectors'],
        queryFn: () => refDataApi.allUsers(),
        enabled: isEditing,
    })
    const technicians = techsRes ?? []
    const allUsers = allUsersRes ?? []

    // Warehouse selector for item modal (admin only)
    const canChooseWarehouse = hasPermission('estoque.warehouse.view')
    const { data: warehousesRes } = useQuery({
        queryKey: ['warehouses-for-items'],
        queryFn: () => refDataApi.warehouses(),
        enabled: canChooseWarehouse && showItemModal,
        staleTime: 0,
    })
    const warehouses = warehousesRes ?? []

    const { data: checklistRes } = useQuery({
        queryKey: queryKeys.workOrders.checklist(id ?? ''),
        queryFn: () => workOrderApi.checklistResponses(woId),
    })
    const checklistResponses: ChecklistResponse[] = checklistRes?.data?.data ?? []

    const { data: checklistTemplateRes } = useQuery({
        queryKey: queryKeys.workOrders.checklistTemplate(order?.checklist_id ?? undefined),
        queryFn: () => refDataApi.checklistDetail(order?.checklist_id ?? 0),
        enabled: !!order?.checklist_id,
    })
    const checklistTemplate = checklistTemplateRes?.data?.data ?? checklistTemplateRes?.data ?? null
    const checklistTemplateItems: ChecklistTemplateItem[] = checklistTemplate?.items ?? []

    const [checklistForm, setChecklistForm] = useState<Record<number, { value: string; notes: string }>>({})

    const products = productsRes ?? []
    const services = servicesRes ?? []

    // Mutations
    const saveChecklistMut = useMutation({
        mutationFn: (responses: ChecklistResponsePayload[]) => workOrderApi.saveChecklistResponses(woId, responses),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.checklist(id ?? '') })
            toast.success('Checklist salvo com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar checklist')),
    })

    const statusMut = useMutation({
        mutationFn: (data: { status: string; notes: string; agreed_payment_method?: string; agreed_payment_notes?: string }) =>
            workOrderApi.updateStatus(woId, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.dashboardCache })
            broadcastQueryInvalidation(['work-orders', 'dashboard'], 'Ordem de Serviço')
            setShowStatusModal(false)
            setAgreedPaymentMethod('')
            setAgreedPaymentNotes('')
            toast.success('Status atualizado com sucesso!')
        },
        onError: (err: unknown) => {
            const e = err as ApiErrorLike
            const msg = getApiErrorMessage(err, 'Erro ao alterar status')
            const errors = e?.response?.data?.errors
            const paymentErr = errors?.agreed_payment_method?.[0]
            toast.error(paymentErr || msg)
        },
    })

    const addItemMut = useMutation({
        mutationFn: (data: ItemFormPayload) => workOrderApi.addItem(woId, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.stock.all })
            qc.invalidateQueries({ queryKey: queryKeys.products.all })
            broadcastQueryInvalidation(['work-orders', 'stock', 'products'], 'Item de OS')
            setShowItemModal(false)
            toast.success('Item adicionado com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao adicionar item')),
    })

    const updateItemMut = useMutation({
        mutationFn: (data: ItemFormPayload) => workOrderApi.updateItem(woId, editingItem?.id ?? 0, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.stock.all })
            qc.invalidateQueries({ queryKey: queryKeys.products.all })
            broadcastQueryInvalidation(['work-orders', 'stock', 'products'], 'Item de OS')
            setShowItemModal(false)
            setEditingItem(null)
            toast.success('Item atualizado com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar item')),
    })

    const delItemMut = useMutation({
        mutationFn: (itemId: number) => workOrderApi.deleteItem(woId, itemId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.stock.all })
            qc.invalidateQueries({ queryKey: queryKeys.products.all })
            broadcastQueryInvalidation(['work-orders', 'stock', 'products'], 'Item de OS')
            setDeleteItemId(null)
            toast.success('Item removido com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao remover item')),
    })

    const updateMut = useMutation({
        mutationFn: (data: EditFormPayload) => workOrderApi.update(woId, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            broadcastQueryInvalidation(['work-orders'], 'Ordem de Serviço')
            toast.success('Alterações salvas com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar alterações')),
    })

    // Attachment mutations
    const uploadAttachmentMut = useMutation({
        mutationFn: (formData: FormData) => workOrderApi.addAttachment(woId, formData),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            toast.success('Anexo enviado com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao enviar anexo')),
    })

    const deleteAttachmentMut = useMutation({
        mutationFn: (attachmentId: number) => workOrderApi.deleteAttachment(woId, attachmentId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            setDeleteAttachId(null)
            toast.success('Anexo removido com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao remover anexo')),
    })

    const signMut = useMutation({
        mutationFn: (data: { signature: string; signer_name: string }) =>
            workOrderApi.signature(woId, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            toast.success('Assinatura registrada com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar assinatura')),
    })

    // Equipment attach/detach mutations
    const { data: customerEquipmentsRes } = useQuery({
        queryKey: queryKeys.workOrders.customerEquipments(order?.customer_id ?? 0),
        queryFn: () => refDataApi.customerEquipments(order?.customer_id ?? 0).then(items => items as unknown as WorkOrderEquipmentRef[]),
        enabled: !!order?.customer_id && showEquipmentModal,
    })
    const customerEquipments = customerEquipmentsRes ?? []

    const attachEquipMut = useMutation({
        mutationFn: (equipmentId: number) => workOrderApi.attachEquipment(woId, equipmentId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            setShowEquipmentModal(false)
            toast.success('Equipamento vinculado com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao vincular equipamento')),
    })

    const detachEquipMut = useMutation({
        mutationFn: (equipmentId: number) => workOrderApi.detachEquipment(woId, equipmentId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            setDetachEquipId(null)
            toast.success('Equipamento desvinculado!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao desvincular equipamento')),
    })

    // Cost estimate query (lazy)
    const { data: costEstimateRes, isLoading: costEstimateLoading, isError: costEstimateError } = useQuery({
        queryKey: queryKeys.workOrders.costEstimate(id ?? ''),
        queryFn: () => workOrderApi.costEstimate(woId),
        enabled: showCostEstimate && canViewPrices,
    })
    const costEstimate: WorkOrderCostEstimate | null = costEstimateRes?.data?.data ?? costEstimateRes?.data ?? null

    // Commission events for this work order
    const { data: commissionEventsRes } = useQuery({
        queryKey: ['wo-commission-events', id],
        queryFn: () => financialApi.commissions.events({ work_order_id: woId, per_page: 50 }),
        enabled: canViewPrices && !!woId,
    })
    const commissionEvents: Array<{ id: number; user?: { name?: string }; user_name?: string; commission_amount: number; status: string; notes?: string; rule?: { name?: string }; rule_name?: string }> =
        commissionEventsRes?.data?.data ?? commissionEventsRes?.data ?? []

    // Parts kits query (lazy - loaded when modal opens)
    const { data: partsKitsRes } = useQuery({
        queryKey: queryKeys.workOrders.partsKitsOptions,
        queryFn: () => api.get('/parts-kits', { params: { per_page: 100 } }).then((r) => safeArray<PartsKit>(r.data)),
        enabled: showKitModal,
    })
    const partsKits = partsKitsRes ?? []

    // Fiscal notes linked to this work order
    const { data: fiscalNotesRes } = useQuery({
        queryKey: ['work-order-fiscal-notes', woId],
        queryFn: () => workOrderApi.fiscalNotes(woId).then((r: { data: unknown }) => safeArray<FiscalNote>(r.data)),
        enabled: !!order,
    })
    const fiscalNotes = fiscalNotesRes ?? []

    const applyKitMut = useMutation({
        mutationFn: (kitId: number) => workOrderApi.applyKit(woId, kitId),
        onSuccess: (res: { data?: { message?: string } }) => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.stock.all })
            qc.invalidateQueries({ queryKey: queryKeys.products.all })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.costEstimate(id ?? '') })
            setShowKitModal(false)
            toast.success(res?.data?.message || 'Kit aplicado com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao aplicar kit')),
    })

    const duplicateMut = useMutation({
        mutationFn: () => workOrderApi.duplicate(woId),
        onSuccess: (res: { data?: { data?: { id: number }; id?: number } }) => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            broadcastQueryInvalidation(['work-orders', 'dashboard'], 'Ordem de Serviço')
            toast.success('OS duplicada com sucesso!')
            navigate(`/os/${res.data?.data?.id ?? res.data?.id}`)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao duplicar OS')),
    })

    const reopenMut = useMutation({
        mutationFn: () => workOrderApi.reopen(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            broadcastQueryInvalidation(['work-orders', 'dashboard'], 'Ordem de Serviço')
            toast.success('OS reaberta com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao reabrir OS')),
    })

    const uninvoiceMut = useMutation({
        mutationFn: () => workOrderApi.uninvoice(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            qc.invalidateQueries({ queryKey: queryKeys.dashboardCache })
            broadcastQueryInvalidation(['work-orders', 'dashboard', 'financial'], 'Ordem de Serviço')
            toast.success('OS desfaturada com sucesso! Invoice e títulos financeiros cancelados.')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao desfaturar OS')),
    })

    const dispatchMut = useMutation({
        mutationFn: () => workOrderApi.authorizeDispatch(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success('Deslocamento autorizado!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao autorizar deslocamento')),
    })

    // Inter-module: Emitir NFS-e a partir da OS
    const emitNfseMut = useMutation({
        mutationFn: () => workOrderApi.emitNfse(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['work-order-fiscal-notes', woId] })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            broadcastQueryInvalidation(['work-orders', 'fiscal'], 'NFS-e')
            setShowEmitNfseConfirm(false)
            toast.success('NFS-e gerada com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao emitir NFS-e')),
    })

    // Inter-module: Emitir NF-e a partir da OS
    const emitNfeMut = useMutation({
        mutationFn: () => workOrderApi.emitNfe(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['work-order-fiscal-notes', woId] })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            broadcastQueryInvalidation(['work-orders', 'fiscal'], 'NF-e')
            setShowEmitNfeConfirm(false)
            toast.success('NF-e gerada com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao emitir NF-e')),
    })

    // Inter-module: Gerar conta a receber a partir da OS
    const generateReceivableMut = useMutation({
        mutationFn: () => workOrderApi.generateReceivable(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            broadcastQueryInvalidation(['work-orders', 'financial', 'accounts-receivable'], 'Conta a Receber')
            setShowReceivableConfirm(false)
            toast.success('Conta a receber gerada com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao gerar conta a receber')),
    })

    // Inter-module: Deduzir estoque automático
    const autoDeductStockMut = useMutation({
        mutationFn: () => workOrderApi.autoDeductStock(woId),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(woId) })
            qc.invalidateQueries({ queryKey: queryKeys.stock.all })
            qc.invalidateQueries({ queryKey: queryKeys.products.all })
            broadcastQueryInvalidation(['work-orders', 'stock', 'products'], 'Estoque')
            setShowDeductStockConfirm(false)
            toast.success('Estoque deduzido com sucesso!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao deduzir estoque')),
    })

    // Helpers
    const downloadPdf = async () => {
        try {
            const response = await workOrderApi.pdf(woId)
            const blob = new Blob([response.data], { type: 'application/pdf' })
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            const osIdentifier = String(order?.business_number ?? order?.os_number ?? order?.number ?? id ?? woId)
                .replace(/[^\w.-]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '')
            link.download = `os-${osIdentifier || woId}.pdf`
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao gerar PDF'))
        }
    }

    const openWorkOrderExpenses = (openNewForm = false) => {
        if (!id) return
        const params = new URLSearchParams()
        params.set('work_order_id', String(id))
        if (openNewForm) {
            params.set('new', '1')
        }
        navigate(`/financeiro/despesas?${params.toString()}`)
    }

    if (isLoading || !order) {
        return (
            <div className="space-y-5">
                <div className="animate-pulse flex items-center gap-3">
                    <div className="h-8 w-8 rounded-lg bg-surface-200" />
                    <div className="space-y-1">
                        <div className="h-5 w-32 rounded bg-surface-200" />
                        <div className="h-3 w-48 rounded bg-surface-100" />
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-5">
                        <div className="animate-pulse rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-3">
                            <div className="h-4 w-28 rounded bg-surface-200" />
                            <div className="h-4 w-full rounded bg-surface-100" />
                            <div className="h-4 w-3/4 rounded bg-surface-100" />
                        </div>
                        <div className="animate-pulse rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-3">
                            <div className="h-4 w-20 rounded bg-surface-200" />
                            <div className="h-12 w-full rounded bg-surface-100" />
                            <div className="h-12 w-full rounded bg-surface-100" />
                        </div>
                    </div>
                    <div className="space-y-5">
                        <div className="animate-pulse rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-3">
                            <div className="h-4 w-20 rounded bg-surface-200" />
                            <div className="h-4 w-full rounded bg-surface-100" />
                            <div className="h-4 w-2/3 rounded bg-surface-100" />
                        </div>
                    </div>
                </div>
            </div>
        )
    }

    if (isError) {
        return (
            <div className="py-16 text-center">
                <AlertTriangle className="mx-auto h-12 w-12 text-red-300" />
                <p className="mt-3 text-sm text-surface-500">{(error as ApiErrorLike)?.response?.data?.message ?? 'Erro ao carregar ordem de serviço'}</p>
                <Button className="mt-3" variant="outline" onClick={() => refetchOrder()}>Tentar novamente</Button>
            </div>
        )
    }

    const formatBRL = (v: string | number) => formatCurrency(parseFloat(String(v)))

    const formatDate = (d: string | null) =>
        d ? new Date(d).toLocaleDateString('pt-BR', {
            day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit',
        }) : '—'

    const sc = statusConfig[order.status] ?? statusConfig.open
    const _StatusIcon = sc.icon

    const openItemForm = (item?: WorkOrderItem) => {
        if (item) {
            setEditingItem(item)
            setItemForm({
                type: item.type,
                reference_id: item.reference_id ?? '',
                description: item.description,
                quantity: String(item.quantity ?? ''),
                unit_price: String(item.unit_price ?? ''),
                discount: String(item.discount ?? ''),
                warehouse_id: item.warehouse_id ?? '',
            })
        } else {
            setEditingItem(null)
            setItemForm({ type: 'service', reference_id: '', description: '', quantity: '1', unit_price: '0', discount: '0', warehouse_id: '' })
        }
        setShowItemModal(true)
    }

    const handleRefChange = (val: string) => {
        const list = itemForm.type === 'product' ? products : services
        const ref = list.find((r: ProductOrService) => r.id === Number(val))
        setItemForm(prev => ({
            ...prev,
            reference_id: val,
            description: ref?.name ?? prev.description,
            unit_price: itemForm.type === 'product' ? (ref?.sell_price ?? '0') : (ref?.default_price ?? '0'),
        }))
    }

    const handleScanLabel = () => setShowQrScanner(true)

    const handleQrScanned = async (raw: string) => {
        const productId = parseLabelQrPayload(raw)
        if (!productId) {
            toast.error('Código inválido. Use o formato P seguido do número (ex: P123).')
            return
        }
        try {
            const response = await refDataApi.productDetail(productId)
            const product = extractWorkOrderQrProduct(response)
            if (!product) {
                toast.error('Produto não encontrado.')
                return
            }
            setEditingItem(null)
            setItemForm({
                type: 'product',
                reference_id: String(product.id),
                description: product.name ?? '',
                quantity: '1',
                unit_price: String(product.sell_price ?? '0'),
                discount: '0',
                warehouse_id: '',
            })
            setShowItemModal(true)
        } catch {
            toast.error('Produto não encontrado.')
        }
    }


    const startEditing = () => {
        const techIds = ((order.technicians || []) as { id: number; pivot?: { role?: string } }[])
            .filter(t => t.pivot?.role !== 'motorista')
            .map(t => t.id)
        setEditForm({
            description: order.description ?? '',
            priority: order.priority ?? 'normal',
            technical_report: order.technical_report ?? '',
            internal_notes: order.internal_notes ?? '',
            displacement_value: String(order.displacement_value ?? '0'),
            is_warranty: order.is_warranty ?? false,
            assigned_to: order.assigned_to ?? '',
            seller_id: order.seller_id ?? '',
            driver_id: order.driver_id ?? '',
            technician_ids: techIds,
            lead_source: (order as Record<string, unknown>).lead_source as string ?? '',
            scheduled_date: order.scheduled_date ? new Date(order.scheduled_date).toISOString().slice(0, 16) : '',
            service_type: order.service_type ?? '',
            address: order.address ?? '',
            city: order.city ?? '',
            state: order.state ?? '',
            zip_code: order.zip_code ?? '',
            contact_phone: order.contact_phone ?? '',
            delivery_forecast: order.delivery_forecast ? String(order.delivery_forecast).slice(0, 10) : '',
            checklist_id: order.checklist_id ?? '',
            branch_id: order.branch_id ?? '',
            tags: order.tags ?? [],
            agreed_payment_method: order.agreed_payment_method ?? '',
            agreed_payment_notes: order.agreed_payment_notes ?? '',
            os_number: order.os_number ?? '',
            sla_policy_id: order.sla_policy_id ?? '',
        })
        setIsEditing(true)
    }

    const cancelEditing = () => setIsEditing(false)

    const saveEditing = () => {
        const payload: EditFormPayload = {
            ...editForm,
            assigned_to: editForm.assigned_to || null,
            seller_id: editForm.seller_id || null,
            driver_id: editForm.driver_id || null,
            technician_ids: editForm.technician_ids,
            lead_source: editForm.lead_source || undefined,
            scheduled_date: editForm.scheduled_date || undefined,
            service_type: editForm.service_type || undefined,
            address: editForm.address || undefined,
            city: editForm.city || undefined,
            state: editForm.state || undefined,
            zip_code: editForm.zip_code || undefined,
            contact_phone: editForm.contact_phone || undefined,
            delivery_forecast: editForm.delivery_forecast || undefined,
            checklist_id: editForm.checklist_id || null,
            branch_id: editForm.branch_id || null,
            tags: editForm.tags.length > 0 ? editForm.tags : undefined,
            agreed_payment_method: editForm.agreed_payment_method || undefined,
            agreed_payment_notes: editForm.agreed_payment_notes || undefined,
            os_number: editForm.os_number || undefined,
            sla_policy_id: editForm.sla_policy_id || null,
        }
        updateMut.mutate(payload, { onSuccess: () => setIsEditing(false) })
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <IconButton label="Voltar" icon={<ArrowLeft className="h-5 w-5" />} onClick={() => navigate('/os')} />
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-lg font-semibold text-surface-900 tracking-tight">{order.business_number ?? order.os_number ?? order.number}</h1>
                            <Badge variant={sc.variant} dot>{sc.label}</Badge>
                            {order.priority !== 'normal' && (
                                <Badge variant={priorityConfig[order.priority]?.variant ?? 'default'}>
                                    {priorityConfig[order.priority]?.label}
                                </Badge>
                            )}
                            {order.is_warranty && (
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold rounded-full border border-amber-200">
                                    GARANTIA
                                </span>
                            )}
                            <SLACountdown dueAt={order.sla_due_at ?? null} status={order.status} />
                        </div>
                        <p className="text-sm text-surface-500">
                            Criada em {formatDate(order.created_at ?? null)}
                            {order.creator?.name && <> por <span className="font-medium text-surface-700">{order.creator.name}</span></>}
                            {order.origin_type && order.origin_type !== 'manual' && (
                                <> &middot; Origem: <span className="font-medium text-surface-700">
                                    {order.origin_type === 'quote' ? 'Orçamento' : order.origin_type === 'service_call' ? 'Chamado' : order.origin_type === 'recurring_contract' ? 'Contrato Recorrente' : order.origin_type}
                                </span></>
                            )}
                        </p>
                        <div className="mt-1 flex items-center gap-2">
                            {order.quote_id && (
                                <button onClick={() => navigate(`/orcamentos/${order.quote_id}`)}
                                    className="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 hover:bg-amber-100 transition-colors">
                                    <LinkIcon className="h-3 w-3" /> Orçamento #{String(order.quote?.quote_number ?? order.quote?.number ?? order.quote_id ?? '')}
                                </button>
                            )}
                            {order.service_call_id && (
                                <button onClick={() => navigate(`/chamados/${order.service_call_id}`)}
                                    className="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-100 transition-colors">
                                    <LinkIcon className="h-3 w-3" /> Chamado #{String(order.service_call?.call_number ?? order.service_call_id ?? '')}
                                </button>
                            )}
                            {order.recurring_contract_id && (
                                <button onClick={() => navigate('/os/contratos-recorrentes')}
                                    className="inline-flex items-center gap-1 rounded-md bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 hover:bg-teal-100 transition-colors">
                                    <LinkIcon className="h-3 w-3" /> Contrato Recorrente
                                </button>
                            )}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {canUpdate && (
                        !isEditing ? (
                            <Button variant="outline" icon={<Pencil className="h-4 w-4" />} onClick={startEditing}>
                                Editar
                            </Button>
                        ) : (
                            <>
                                <Button variant="outline" icon={<X className="h-4 w-4" />} onClick={cancelEditing}>
                                    Cancelar
                                </Button>
                                <Button icon={<Save className="h-4 w-4" />} onClick={saveEditing} loading={updateMut.isPending}>
                                    Salvar
                                </Button>
                            </>
                        )
                    )}
                    {hasPermission('os.work_order.create') && (
                        <Button variant="outline" icon={<Copy className="h-4 w-4" />}
                            onClick={() => duplicateMut.mutate()} loading={duplicateMut.isPending}>
                            Duplicar
                        </Button>
                    )}
                    {order.status === 'cancelled' && canChangeStatus && (
                        <Button variant="outline" icon={<RotateCcw className="h-4 w-4" />}
                            onClick={() => reopenMut.mutate()} loading={reopenMut.isPending}>
                            Reabrir
                        </Button>
                    )}
                    {order.status === 'invoiced' && canChangeStatus && (
                        <Button variant="outline" className="text-amber-600 border-amber-300 hover:bg-amber-50"
                            icon={<RotateCcw className="h-4 w-4" />}
                            onClick={() => setShowUninvoiceConfirm(true)}
                            loading={uninvoiceMut.isPending}>
                            Desfaturar
                        </Button>
                    )}
                    {canExecuteWorkOrderFlow && (
                        <GeoCheckinButton
                            workOrderId={Number(id)}
                            hasCheckin={!!order.checkin_at}
                            hasCheckout={!!order.checkout_at}
                        />
                    )}
                    {hasPermission('os.work_order.view') && (
                        <Button variant="outline" icon={<Download className="h-4 w-4" />}
                            onClick={downloadPdf}>
                            Baixar PDF
                        </Button>
                    )}
                    {(canViewExpenses || canCreateExpense) && (
                        <Button
                            variant="outline"
                            icon={<Receipt className="h-4 w-4" />}
                            onClick={() => openWorkOrderExpenses(canCreateExpense)}
                        >
                            {canCreateExpense ? 'Lancar Despesa' : 'Ver Despesas'}
                        </Button>
                    )}
                    {canChangeStatus && (
                        <>
                            <Button onClick={() => { setNewStatus(''); setStatusNotes(''); setAgreedPaymentMethod(''); setAgreedPaymentNotes(''); setShowStatusModal(true) }}>
                                Alterar Status
                            </Button>
                            {order.status === 'delivered' && (
                                <Button
                                    variant="brand"
                                    icon={<DollarSign className="h-4 w-4" />}
                                    onClick={() => {
                                        setNewStatus('invoiced')
                                        setStatusNotes('')
                                        setAgreedPaymentMethod('')
                                        setAgreedPaymentNotes('')
                                        setShowStatusModal(true)
                                    }}
                                >
                                    Faturar esta OS
                                </Button>
                            )}
                            {(order.status === 'completed' || order.status === 'delivered' || order.status === 'invoiced') && hasPermission('finance.receivable.create') && (
                                <Button
                                    variant="outline"
                                    icon={<DollarSign className="h-4 w-4" />}
                                    onClick={() => setShowReceivableConfirm(true)}
                                    loading={generateReceivableMut.isPending}
                                >
                                    Gerar Conta a Receber
                                </Button>
                            )}
                            {order.status !== 'cancelled' && hasPermission('estoque.movement.create') && (order.items?.length ?? 0) > 0 && (
                                <Button
                                    variant="outline"
                                    icon={<Package className="h-4 w-4" />}
                                    onClick={() => setShowDeductStockConfirm(true)}
                                    loading={autoDeductStockMut.isPending}
                                >
                                    Deduzir Estoque
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </div>

            <StatusTimeline
                currentStatus={order.status}
                statusHistory={(order.status_history ?? []).map(h => ({ status: (h as StatusHistoryEntry).to_status ?? '', created_at: (h as StatusHistoryEntry).created_at }))}
            />

            <ExecutionActions
                workOrderId={order.id}
                status={order.status}
                onStatusChange={() => refetchOrder()}
                canExecute={canExecuteWorkOrderFlow}
                blockedMessage={executionBlockedMessage}
                className="bg-surface-0 rounded-xl border border-default shadow-card p-5"
            />

            <div className="flex items-center gap-1 border-b border-subtle mb-6">
                <button
                    onClick={() => setActiveTab('details')}
                    className={cn(
                        "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px",
                        activeTab === 'details' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                    )}
                >
                    Informações Gerais
                </button>
                <button
                    onClick={() => setActiveTab('checklist')}
                    className={cn(
                        "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px",
                        activeTab === 'checklist' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                    )}
                >
                    Checklist
                </button>
                {canViewInternalChat && (
                    <button
                        onClick={() => setActiveTab('chat')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2",
                            activeTab === 'chat' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                        )}
                    >
                        Chat Interno
                        <Badge variant="brand" className="px-1.5 py-0 text-xs">Beta</Badge>
                    </button>
                )}
                {canViewAuditTrail && (
                    <button
                        onClick={() => setActiveTab('audit')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px",
                            activeTab === 'audit' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                        )}
                    >
                        Auditoria
                    </button>
                )}
                {canViewSatisfaction && (order.status === 'completed' || order.status === 'delivered' || order.status === 'invoiced') && (
                    <button
                        onClick={() => setActiveTab('satisfaction')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px",
                            activeTab === 'satisfaction' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                        )}
                    >
                        Satisfação
                    </button>
                )}
                {canViewExpenses && (
                    <button
                        onClick={() => setActiveTab('expenses')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2",
                            activeTab === 'expenses' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                        )}
                    >
                        Despesas e Custos
                    </button>
                )}
                {(order.requires_maintenance || (order.maintenance_reports && order.maintenance_reports.length > 0)) && (
                    <button
                        onClick={() => setActiveTab('maintenance')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px",
                            activeTab === 'maintenance' ? "border-brand-500 text-brand-600" : "border-transparent text-surface-500 hover:text-surface-700"
                        )}
                    >
                        Manutenção
                    </button>
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-5">
                    {activeTab === 'checklist' && (
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <h3 className="text-sm font-semibold text-surface-900 mb-4 flex items-center gap-2">
                                <ClipboardList className="h-4 w-4 text-brand-500" />
                                Checklist de Serviço
                            </h3>
                            {!order.checklist_id ? (
                                <p className="py-8 text-center text-sm text-surface-400">
                                    Nenhum checklist vinculado a esta OS.
                                </p>
                            ) : checklistTemplateItems.length === 0 ? (
                                <div className="py-8 text-center">
                                    <div className="animate-pulse space-y-2">
                                        <div className="h-4 w-40 mx-auto rounded bg-surface-200" />
                                        <div className="h-4 w-56 mx-auto rounded bg-surface-100" />
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {(checklistTemplateItems || []).map((item: ChecklistTemplateItem) => {
                                        const existing = checklistResponses.find((r: ChecklistResponse) => r.checklist_item_id === item.id)
                                        const currentVal = checklistForm[item.id]?.value ?? existing?.value ?? ''
                                        const currentNotes = checklistForm[item.id]?.notes ?? existing?.notes ?? ''
                                        const isAnswered = !!existing?.value || !!checklistForm[item.id]?.value

                                        const updateField = (field: 'value' | 'notes', val: string) => {
                                            setChecklistForm(prev => ({
                                                ...prev,
                                                [item.id]: {
                                                    value: field === 'value' ? val : (prev[item.id]?.value ?? existing?.value ?? ''),
                                                    notes: field === 'notes' ? val : (prev[item.id]?.notes ?? existing?.notes ?? ''),
                                                },
                                            }))
                                        }

                                        return (
                                            <div key={item.id} className={cn(
                                                'rounded-lg border p-4 transition-colors',
                                                isAnswered ? 'border-emerald-200 bg-emerald-50/30' : 'border-default'
                                            )}>
                                                <div className="flex items-start gap-3">
                                                    <div className={cn(
                                                        'mt-0.5 flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold',
                                                        isAnswered ? 'bg-emerald-500 text-white' : 'bg-surface-200 text-surface-500'
                                                    )}>
                                                        {isAnswered ? '✓' : item.order_index + 1}
                                                    </div>
                                                    <div className="flex-1 space-y-2">
                                                        <p className="text-sm font-medium text-surface-800">
                                                            {item.description}
                                                            {item.is_required && <span className="ml-1 text-red-500">*</span>}
                                                        </p>
                                                        {item.type === 'check' || item.type === 'yes_no' ? (
                                                            <div className="flex gap-2">
                                                                {(item.type === 'yes_no' ? ['Sim', 'Não'] : ['OK', 'NOK', 'N/A']).map(opt => (
                                                                    <button key={opt} type="button"
                                                                        onClick={() => canUpdate ? updateField('value', opt) : undefined}
                                                                        className={cn(
                                                                            'rounded-lg border px-3 py-1.5 text-xs font-medium transition-all',
                                                                            currentVal === opt
                                                                                ? opt === 'OK' || opt === 'Sim' ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                                                    : opt === 'NOK' || opt === 'Não' ? 'border-red-400 bg-red-50 text-red-700'
                                                                                        : 'border-surface-400 bg-surface-100 text-surface-700'
                                                                                : 'border-default text-surface-500 hover:border-surface-400',
                                                                            !canUpdate && 'opacity-70 cursor-default'
                                                                        )}
                                                                    >
                                                                        {opt}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : item.type === 'number' ? (
                                                            <input type="number" step="0.01" value={currentVal}
                                                                onChange={e => updateField('value', e.target.value)}
                                                                readOnly={!canUpdate}
                                                                placeholder="Valor numérico..."
                                                                className="w-40 rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                        ) : (
                                                            <input type="text" value={currentVal}
                                                                onChange={e => updateField('value', e.target.value)}
                                                                readOnly={!canUpdate}
                                                                placeholder="Resposta..."
                                                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                        )}
                                                        <input type="text" value={currentNotes}
                                                            onChange={e => updateField('notes', e.target.value)}
                                                            readOnly={!canUpdate}
                                                            placeholder="Observações (opcional)..."
                                                            className="w-full rounded-lg border border-default bg-surface-50 px-3 py-1.5 text-xs text-surface-500 focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                    </div>
                                                </div>
                                            </div>
                                        )
                                    })}
                                    {canUpdate && (
                                        <div className="flex justify-end pt-2">
                                            <Button
                                                onClick={() => {
                                                    const responses = Object.entries(checklistForm).map(([itemId, data]) => ({
                                                        checklist_item_id: Number(itemId),
                                                        value: data.value,
                                                        notes: data.notes,
                                                    }))
                                                    if (responses.length === 0) {
                                                        toast.info('Preencha ao menos um item do checklist.')
                                                        return
                                                    }
                                                    saveChecklistMut.mutate(responses)
                                                }}
                                                loading={saveChecklistMut.isPending}
                                                icon={<Save className="h-4 w-4" />}
                                            >
                                                Salvar Checklist
                                            </Button>
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2 text-xs text-surface-400 pt-2 border-t border-subtle">
                                        <span>{checklistResponses.length} / {checklistTemplateItems.length} respondidos</span>
                                        {checklistTemplate?.name && <span>· {checklistTemplate.name}</span>}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {canViewInternalChat && activeTab === 'chat' && (
                        <AdminChatTab workOrderId={Number(id)} />
                    )}

                    {canViewAuditTrail && activeTab === 'audit' && (
                        <AuditTrailTab workOrderId={Number(id)} />
                    )}

                    {canViewSatisfaction && activeTab === 'satisfaction' && (
                        <SatisfactionTab workOrderId={Number(id)} />
                    )}

                    {canViewExpenses && activeTab === 'expenses' && (
                        <WoExpensesTab workOrderId={Number(id)} />
                    )}

                    {activeTab === 'maintenance' && (
                        <MaintenanceReportsTab
                            workOrderId={Number(id)}
                            equipmentId={order.equipment_id ?? 0}
                        />
                    )}

                    {activeTab === 'details' && (
                        <>
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="text-sm font-semibold text-surface-900 mb-2">Defeito Relatado</h3>
                                {isEditing ? (
                                    <textarea
                                        aria-label="Defeito relatado"
                                        placeholder="Descreva o defeito relatado"
                                        value={editForm.description}
                                        onChange={e => setEditForm(p => ({ ...p, description: e.target.value }))}
                                        rows={4}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                                    />
                                ) : (
                                    <p className="text-sm text-surface-700 whitespace-pre-wrap">{order.description}</p>
                                )}

                                {isEditing && (
                                    <div className="mt-4 border-t border-subtle pt-4 space-y-4">
                                        <div>
                                            <h3 className="text-sm font-semibold text-surface-900 mb-2">Prioridade</h3>
                                            <div className="flex gap-2">
                                                {Object.entries(priorityConfig).map(([key, conf]) => (
                                                    <button key={key} type="button" onClick={() => setEditForm(p => ({ ...p, priority: key }))}
                                                        className={cn('rounded-lg border px-3 py-1.5 text-xs font-medium transition-all',
                                                            editForm.priority === key
                                                                ? 'border-brand-500 bg-brand-50 text-brand-700'
                                                                : 'border-default text-surface-600 hover:border-surface-400')}>
                                                        {conf.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="flex items-center gap-2 text-sm text-surface-700 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={editForm.is_warranty}
                                                    onChange={e => setEditForm(p => ({ ...p, is_warranty: e.target.checked }))}
                                                    className="rounded border-surface-400 text-brand-600 focus:ring-brand-500"
                                                />
                                                OS de Garantia
                                                <span className="text-xs text-surface-400">(não gera comissão)</span>
                                            </label>
                                        </div>

                                        {/* Equipe e Origem */}
                                        <div className="mt-4 border-t border-subtle pt-4 space-y-3">
                                            <h3 className="text-sm font-semibold text-surface-900">Equipe e Origem</h3>
                                            <div className="grid gap-3 sm:grid-cols-4">
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Técnico Responsável</label>
                                                    <select title="Técnico Responsável" value={editForm.assigned_to ?? ''}
                                                        onChange={e => setEditForm(p => ({ ...p, assigned_to: e.target.value || null }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Sem atribuição</option>
                                                        {technicians.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Vendedor</label>
                                                    <select title="Vendedor" value={editForm.seller_id ?? ''}
                                                        onChange={e => setEditForm(p => ({ ...p, seller_id: e.target.value || null }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Nenhum</option>
                                                        {allUsers.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Motorista</label>
                                                    <select title="Motorista" value={editForm.driver_id ?? ''}
                                                        onChange={e => setEditForm(p => ({ ...p, driver_id: e.target.value || null }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Nenhum</option>
                                                        {allUsers.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Origem do Lead</label>
                                                    <select title="Origem do Lead" value={editForm.lead_source}
                                                        onChange={e => setEditForm(p => ({ ...p, lead_source: e.target.value }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Nenhuma</option>
                                                        <option value="prospeccao">Prospecção</option>
                                                        <option value="retorno">Retorno</option>
                                                        <option value="contato_direto">Contato Direto</option>
                                                        <option value="indicacao">Indicação</option>
                                                        <option value="site">Site</option>
                                                        <option value="telefone">Telefone</option>
                                                        <option value="email">E-mail</option>
                                                        <option value="visita">Visita</option>
                                                        <option value="outro">Outro</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-xs font-medium text-surface-600">Técnicos (múltiplos)</label>
                                                <div className="flex flex-wrap gap-2">
                                                    {technicians.map(t => (
                                                        <button key={t.id} type="button"
                                                            onClick={() => setEditForm(p => ({
                                                                ...p,
                                                                technician_ids: p.technician_ids.includes(t.id)
                                                                    ? p.technician_ids.filter(id => id !== t.id)
                                                                    : [...p.technician_ids, t.id]
                                                            }))}
                                                            className={cn('rounded-lg border px-3 py-1.5 text-xs font-medium transition-all',
                                                                editForm.technician_ids.includes(t.id)
                                                                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                                                                    : 'border-default text-surface-600 hover:border-surface-400')}>
                                                            {t.name}
                                                        </button>
                                                    ))}
                                                    {technicians.length === 0 && <p className="text-xs text-surface-400">Carregando técnicos...</p>}
                                                </div>
                                            </div>

                                            {/* Agendamento & Tipo */}
                                            <h3 className="text-sm font-semibold text-surface-900 mt-4">Agendamento & Tipo</h3>
                                            <div className="grid gap-3 sm:grid-cols-4">
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Data Agendada</label>
                                                    <input type="datetime-local" value={editForm.scheduled_date}
                                                        onChange={e => setEditForm(p => ({ ...p, scheduled_date: e.target.value }))}
                                                        placeholder="Selecione data e hora"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Previsão Entrega</label>
                                                    <input type="date" value={editForm.delivery_forecast}
                                                        onChange={e => setEditForm(p => ({ ...p, delivery_forecast: e.target.value }))}
                                                        placeholder="Selecione a data"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Tipo de Serviço</label>
                                                    <select title="Tipo de Serviço" value={editForm.service_type}
                                                        onChange={e => setEditForm(p => ({ ...p, service_type: e.target.value }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Nenhum</option>
                                                        <option value="diagnostico">Diagnóstico</option>
                                                        <option value="manutencao_corretiva">Manutenção Corretiva</option>
                                                        <option value="preventiva">Preventiva</option>
                                                        <option value="calibracao">Calibração</option>
                                                        <option value="instalacao">Instalação</option>
                                                        <option value="retorno">Retorno</option>
                                                        <option value="garantia">Garantia</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Nº OS Manual</label>
                                                    <input type="text" value={editForm.os_number}
                                                        onChange={e => setEditForm(p => ({ ...p, os_number: e.target.value }))}
                                                        placeholder="Ex: OS-001234"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                            </div>

                                            {/* Endereço & Contato */}
                                            <h3 className="text-sm font-semibold text-surface-900 mt-4">Endereço & Contato</h3>
                                            <div className="grid gap-3 sm:grid-cols-4">
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">CEP</label>
                                                    <input type="text" value={editForm.zip_code}
                                                        onChange={e => {
                                                            const v = e.target.value.replace(/\D/g, '').slice(0, 8)
                                                            const formatted = v.length > 5 ? `${v.slice(0, 5)}-${v.slice(5)}` : v
                                                            setEditForm(p => ({ ...p, zip_code: formatted }))
                                                        }}
                                                        onBlur={async () => {
                                                            const result = await fetchAddressByCep(editForm.zip_code)
                                                            if (result) {
                                                                setEditForm(p => ({
                                                                    ...p,
                                                                    address: [result.address, result.neighborhood].filter(Boolean).join(', '),
                                                                    city: result.city,
                                                                    state: result.state,
                                                                }))
                                                            }
                                                        }}
                                                        placeholder="00000-000"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Endereço</label>
                                                    <input type="text" value={editForm.address}
                                                        onChange={e => setEditForm(p => ({ ...p, address: e.target.value }))}
                                                        placeholder="Rua, Bairro"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Tel. Contato</label>
                                                    <input type="text" value={editForm.contact_phone}
                                                        onChange={e => {
                                                            const v = e.target.value.replace(/\D/g, '').slice(0, 11)
                                                            const formatted = v.length <= 2 ? v : v.length <= 6 ? `(${v.slice(0, 2)}) ${v.slice(2)}` : v.length <= 10 ? `(${v.slice(0, 2)}) ${v.slice(2, 6)}-${v.slice(6)}` : `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`
                                                            setEditForm(p => ({ ...p, contact_phone: formatted }))
                                                        }}
                                                        placeholder="(99) 99999-9999"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Cidade</label>
                                                    <input type="text" value={editForm.city}
                                                        onChange={e => setEditForm(p => ({ ...p, city: e.target.value }))}
                                                        placeholder="Nome da cidade"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">UF</label>
                                                    <input type="text" maxLength={2} value={editForm.state}
                                                        onChange={e => setEditForm(p => ({ ...p, state: e.target.value.toUpperCase() }))}
                                                        placeholder="SP"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                            </div>

                                            {/* Pagamento */}
                                            <h3 className="text-sm font-semibold text-surface-900 mt-4">Pagamento</h3>
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Forma de Pagamento</label>
                                                    <select title="Forma de Pagamento" value={editForm.agreed_payment_method}
                                                        onChange={e => setEditForm(p => ({ ...p, agreed_payment_method: e.target.value }))}
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                                        <option value="">Nenhuma</option>
                                                        {AGREED_PAYMENT_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Obs. Pagamento</label>
                                                    <input type="text" value={editForm.agreed_payment_notes}
                                                        onChange={e => setEditForm(p => ({ ...p, agreed_payment_notes: e.target.value }))}
                                                        placeholder="Observações de pagamento"
                                                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                                </div>
                                                <div>
                                                    <label className="mb-1.5 block text-xs font-medium text-surface-600">Valor Deslocamento</label>
                                                    <CurrencyInput value={editForm.displacement_value}
                                                        onChange={v => setEditForm(p => ({ ...p, displacement_value: v }))} />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="mt-4 border-t border-subtle pt-4">
                                    <h3 className="text-sm font-semibold text-surface-900 mb-2">Laudo Técnico</h3>
                                    {isEditing ? (
                                        <textarea
                                            value={editForm.technical_report}
                                            onChange={e => setEditForm(p => ({ ...p, technical_report: e.target.value }))}
                                            rows={3}
                                            placeholder="Escreva o laudo técnico..."
                                            className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                                        />
                                    ) : (
                                        order.technical_report
                                            ? <p className="text-sm text-surface-700 whitespace-pre-wrap">{order.technical_report}</p>
                                            : <p className="text-sm text-surface-400 italic">Nenhum laudo registrado</p>
                                    )}
                                </div>

                                <div className="mt-4 border-t border-subtle pt-4">
                                    <h3 className="text-sm font-semibold text-surface-500 mb-1">Observações Internas</h3>
                                    {isEditing ? (
                                        <textarea
                                            value={editForm.internal_notes}
                                            onChange={e => setEditForm(p => ({ ...p, internal_notes: e.target.value }))}
                                            rows={2}
                                            placeholder="Notas internas..."
                                            className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                                        />
                                    ) : (
                                        order.internal_notes
                                            ? <p className="text-xs text-surface-500 italic">{order.internal_notes}</p>
                                            : <p className="text-xs text-surface-400 italic">Nenhuma observação interna</p>
                                    )}
                                </div>

                                <div className="mt-4 border-t border-subtle pt-4">
                                    <h3 className="text-sm font-semibold text-surface-500 mb-1">Deslocamento</h3>
                                    {isEditing && canViewPrices ? (
                                        <CurrencyInput
                                            label="Valor do Deslocamento (R$)"
                                            value={parseFloat(editForm.displacement_value) || 0}
                                            onChange={(val) => setEditForm(p => ({ ...p, displacement_value: String(val) }))}
                                        />
                                    ) : canViewPrices ? (
                                        parseFloat(String(order.displacement_value ?? 0)) > 0
                                            ? <p className="text-sm text-emerald-600 font-medium">+ {formatBRL(order.displacement_value ?? 0)}</p>
                                            : <p className="text-sm text-surface-400 italic">Nenhum valor de deslocamento</p>
                                    ) : null}
                                </div>

                                {order.displacement_started_at && (
                                    <div className="mt-4 border-t border-subtle pt-4">
                                        <h3 className="text-sm font-semibold text-surface-900 mb-3">Deslocamento do Técnico</h3>
                                        <div className="space-y-2 text-sm">
                                            <div className="flex justify-between">
                                                <span className="text-surface-500">Início</span>
                                                <span className="font-medium">{order.displacement_started_at ? new Date(order.displacement_started_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'}</span>
                                            </div>
                                            {order.displacement_arrived_at && (
                                                <>
                                                    <div className="flex justify-between">
                                                        <span className="text-surface-500">Chegada</span>
                                                        <span className="font-medium">{new Date(order.displacement_arrived_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
                                                    </div>
                                                    {order.displacement_duration_minutes != null && (
                                                        <div className="flex justify-between">
                                                            <span className="text-surface-500">Tempo em deslocamento</span>
                                                            <span className="font-medium text-emerald-600">{order.displacement_duration_minutes} min</span>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                            {order.displacement_stops && order.displacement_stops.length > 0 && (
                                                <div className="mt-3 pt-3 border-t border-subtle">
                                                    <p className="text-xs font-medium text-surface-500 mb-2">Paradas (ida)</p>
                                                    <ul className="space-y-1.5">
                                                        {((order.displacement_stops || []) as DisplacementStop[]).filter(s => !s.notes?.startsWith('[RETORNO]')).map(s => {
                                                            const typeLabels: Record<string, string> = { lunch: 'Almoço', hotel: 'Hotel', br_stop: 'Parada BR', fueling: 'Abastecimento', technical_stop: 'Parada Técnica', other: 'Outro' }
                                                            const start = new Date(s.started_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                                                            const end = s.ended_at ? new Date(s.ended_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : 'em andamento'
                                                            return (
                                                                <li key={s.id} className="flex justify-between text-xs">
                                                                    <span>{typeLabels[s.type] ?? s.type} ({start} – {end})</span>
                                                                </li>
                                                            )
                                                        })}
                                                    </ul>
                                                </div>
                                            )}

                                            {order.return_started_at && (
                                                <div className="mt-3 pt-3 border-t border-subtle">
                                                    <p className="text-xs font-semibold text-surface-700 mb-2">Retorno (volta)</p>
                                                    <div className="space-y-1.5 text-xs">
                                                        <div className="flex justify-between">
                                                            <span className="text-surface-500">Início retorno</span>
                                                            <span className="font-medium">{new Date(order.return_started_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
                                                        </div>
                                                        {order.return_destination && (
                                                            <div className="flex justify-between">
                                                                <span className="text-surface-500">Destino</span>
                                                                <span className="font-medium">{({ base: 'Base', hotel: 'Hotel', next_client: 'Próx. Cliente', other: 'Outro' } as Record<string, string>)[order.return_destination as string] ?? String(order.return_destination ?? '')}</span>
                                                            </div>
                                                        )}
                                                        {order.return_arrived_at && (
                                                            <div className="flex justify-between">
                                                                <span className="text-surface-500">Chegada</span>
                                                                <span className="font-medium">{new Date(order.return_arrived_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
                                                            </div>
                                                        )}
                                                        {order.return_duration_minutes != null && (
                                                            <div className="flex justify-between">
                                                                <span className="text-surface-500">Tempo de retorno</span>
                                                                <span className="font-medium text-emerald-600">{order.return_duration_minutes} min</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                    {order.displacement_stops && (order.displacement_stops as DisplacementStop[]).filter(s => s.notes?.startsWith('[RETORNO]')).length > 0 && (
                                                        <div className="mt-2">
                                                            <p className="text-xs font-medium text-surface-500 mb-1">Paradas (retorno)</p>
                                                            <ul className="space-y-1.5">
                                                                {(order.displacement_stops as DisplacementStop[]).filter(s => s.notes?.startsWith('[RETORNO]')).map(s => {
                                                                    const typeLabels: Record<string, string> = { lunch: 'Almoço', hotel: 'Hotel', br_stop: 'Parada BR', fueling: 'Abastecimento', technical_stop: 'Parada Técnica', other: 'Outro' }
                                                                    const start = new Date(s.started_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                                                                    const end = s.ended_at ? new Date(s.ended_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : 'em andamento'
                                                                    return (
                                                                        <li key={s.id} className="flex justify-between text-xs">
                                                                            <span>{typeLabels[s.type] ?? s.type} ({start} – {end})</span>
                                                                        </li>
                                                                    )
                                                                })}
                                                            </ul>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {order.total_duration_minutes != null && (
                                                <div className="mt-3 pt-3 border-t border-emerald-200 bg-emerald-50/50 -mx-1 px-1 rounded">
                                                    <div className="flex justify-between text-sm">
                                                        <span className="font-semibold text-emerald-700">Tempo total da OS</span>
                                                        <span className="font-bold text-emerald-700">{order.total_duration_minutes} min</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Sub-OS (Parent / Children) */}
                            {(order.parent_id || (order.children && order.children.length > 0) || order.is_master) && (
                                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                    <div className="flex items-center justify-between mb-3">
                                        <h3 className="text-sm font-semibold text-surface-900">Sub-OS</h3>
                                        <Button variant="ghost" size="sm" onClick={() => navigate(`/os/nova?parent_id=${order.id}`)} icon={<Plus className="h-4 w-4" />}>
                                            Criar Sub-OS
                                        </Button>
                                    </div>

                                    {order.parent_id && (
                                        <div className="mb-3 rounded-lg border border-brand-200 bg-brand-50/50 px-3 py-2">
                                            <span className="text-sm text-surface-600">OS Pai: </span>
                                            <Link to={`/os/${order.parent_id}`} className="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">
                                                #{order.parent?.business_number ?? order.parent_id}
                                            </Link>
                                        </div>
                                    )}

                                    {order.children && order.children.length > 0 ? (
                                        <div className="space-y-2">
                                            {order.children.map((child) => (
                                                <Link key={child.id} to={`/os/${child.id}`} className="flex items-center gap-3 rounded-lg border border-default p-3 hover:bg-surface-50 transition-colors">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-sm font-medium text-surface-800">#{child.business_number ?? child.number ?? child.id}</span>
                                                            <Badge variant={(statusConfig[child.status]?.variant as BadgeVariant) ?? 'default'} size="sm">
                                                                {statusConfig[child.status]?.label ?? child.status}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-xs text-surface-500 mt-0.5 truncate">
                                                            {child.customer?.name && <span>{child.customer.name} &mdash; </span>}
                                                            {child.description}
                                                        </p>
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>
                                    ) : !order.parent_id && (
                                        <p className="text-sm text-surface-400 italic">Nenhuma sub-OS criada</p>
                                    )}
                                </div>
                            )}

                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-sm font-semibold text-surface-900">Itens</h3>
                                    {canUpdate && (
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => openItemForm()} icon={<Plus className="h-4 w-4" />}>
                                                Adicionar
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => setShowKitModal(true)} icon={<Layers className="h-4 w-4" />} title="Aplicar kit de peças">
                                                Aplicar Kit
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={handleScanLabel} icon={<QrCode className="h-4 w-4" />} title="Escanear etiqueta (QR da peça)">
                                                Escanear etiqueta
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {order.items?.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-surface-400">Nenhum item</p>
                                ) : (
                                    <div className="space-y-2">
                                        {(order.items || []).map((item: WorkOrderItem) => (
                                            <div key={item.id} className="flex items-center gap-3 rounded-lg border border-default p-3 hover:bg-surface-50">
                                                <div className={cn('rounded-md p-1.5', item.type === 'product' ? 'bg-brand-50' : 'bg-emerald-50')}>
                                                    {item.type === 'product'
                                                        ? <Package className="h-4 w-4 text-brand-600" />
                                                        : <Briefcase className="h-4 w-4 text-emerald-600" />}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-surface-800">{item.description}</p>
                                                    <p className="text-xs text-surface-400">
                                                        {item.quantity}{canViewPrices ? ` × ${formatBRL(item.unit_price)}` : ` un.`}
                                                    </p>
                                                </div>
                                                {canViewPrices && (
                                                    <span className="text-sm font-semibold text-surface-900">{formatBRL(item.total ?? 0)}</span>
                                                )}
                                                {canUpdate && (
                                                    <div className="flex gap-1">
                                                        <IconButton label="Editar item" icon={<Pencil className="h-3.5 w-3.5" />} onClick={() => openItemForm(item)} className="hover:text-brand-600" />
                                                        <IconButton label="Remover item" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setDeleteItemId(item.id ?? null)} className="hover:text-red-600" />
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                        {canViewPrices && (
                                            <>
                                                <div className="flex items-center justify-between border-t border-subtle pt-3 mt-3">
                                                    <span className="text-sm font-medium text-surface-600">Desconto fixo</span>
                                                    <span className="text-sm text-surface-600">{formatBRL(Number(order.discount ?? 0))}</span>
                                                </div>
                                                {parseFloat(String(order.discount_percentage ?? 0)) > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm font-medium text-surface-600">Desconto (%)</span>
                                                        <span className="text-sm text-surface-600">{order.discount_percentage}% ({formatBRL(order.discount_amount ?? 0)})</span>
                                                    </div>
                                                )}
                                                {parseFloat(String(order.displacement_value ?? 0)) > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm font-medium text-surface-600">Deslocamento</span>
                                                        <span className="text-sm text-emerald-600">+ {formatBRL(order.displacement_value ?? 0)}</span>
                                                    </div>
                                                )}
                                                <div className="flex items-center justify-between">
                                                    <span className="text-base font-bold text-surface-900">Total</span>
                                                    <span className="text-base font-bold text-brand-600">{formatBRL(order.total ?? 0)}</span>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                                        <Paperclip className="h-4 w-4 text-brand-500" />
                                        Anexos
                                    </h3>
                                    {canUpdate && (
                                        <label className="cursor-pointer">
                                            <input
                                                type="file"
                                                className="hidden"
                                                accept=".jpg,.jpeg,.png,.gif,.pdf,.mp4,.mov,.avi,.mkv"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0]
                                                    if (!file) return
                                                    if (file.size > MAX_ATTACHMENT_SIZE_MB * 1024 * 1024) {
                                                        toast.error(`Arquivo excede ${MAX_ATTACHMENT_SIZE_MB}MB`)
                                                        e.target.value = ''
                                                        return
                                                    }
                                                    const fd = new FormData()
                                                    fd.append('file', file)
                                                    uploadAttachmentMut.mutate(fd)
                                                    e.target.value = ''
                                                }}
                                            />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                icon={<Upload className="h-4 w-4" />}
                                                loading={uploadAttachmentMut.isPending}
                                                disabled={uploadAttachmentMut.isPending}
                                            >
                                                <span>Enviar</span>
                                            </Button>
                                        </label>
                                    )}
                                </div>

                                {/* Drag & Drop Upload */}
                                {canUpdate && <DragDropUpload workOrderId={order.id} />}

                                {(!order.attachments || order.attachments.length === 0) ? (
                                    <p className="py-4 text-center text-sm text-surface-400">Nenhum anexo</p>
                                ) : (
                                    <div className="space-y-2">
                                        {(order.attachments || []).map((att: WorkOrderAttachment) => (
                                            <div key={att.id} className="flex items-center gap-3 rounded-lg border border-default p-3 hover:bg-surface-50">
                                                <div className="rounded-md bg-surface-100 p-1.5">
                                                    <Paperclip className="h-4 w-4 text-surface-500" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-surface-800 truncate">{att.file_name}</p>
                                                    <p className="text-xs text-surface-400">
                                                        {att.uploader?.name ?? 'Sistema'} · {(att.file_size / 1024).toFixed(0)}KB
                                                    </p>
                                                </div>
                                                <div className="flex gap-1">
                                                    <IconButton label="Baixar anexo" icon={<Download className="h-3.5 w-3.5" />} onClick={() => {
                                                        const attachmentUrl = buildStorageUrl(att.file_path)
                                                        if (attachmentUrl) {
                                                            window.open(attachmentUrl, '_blank')
                                                        }
                                                    }} />
                                                    {canUpdate && (
                                                        <IconButton label="Remover anexo" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setDeleteAttachId(att.id)} className="hover:text-red-600" />
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-surface-900">
                            <User className="h-4 w-4 text-brand-500" />
                            Cliente
                        </h3>
                        <div className="space-y-3">
                            <div>
                                <p className="text-xs text-surface-500">Nome</p>
                                {order.customer_id ? (
                                    <button onClick={() => navigate(`/clientes/${order.customer_id}`)} className="font-medium text-brand-600 hover:text-brand-700 hover:underline inline-flex items-center gap-1">
                                        {order.customer?.name}
                                        <ExternalLink className="h-3 w-3" />
                                    </button>
                                ) : (
                                    <p className="font-medium text-surface-900">{order.customer?.name ?? '—'}</p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs text-surface-500">Documento</p>
                                <p className="font-medium text-surface-900">{order.customer?.document || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-surface-500">Contato</p>
                                <p className="font-medium text-surface-900">
                                    {order.customer?.contacts?.[0]?.phone || order.customer?.email || '—'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 pt-2 border-t border-subtle">
                                {order.waze_link && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full justify-start text-[#33ccff] border-[#33ccff]/30 hover:bg-[#33ccff]/5"
                                        onClick={() => { const u = order.waze_link; if (u != null && u !== '') window.open(u); }}
                                    >
                                        <Navigation className="h-4 w-4 mr-2" /> Navegar com Waze
                                    </Button>
                                )}
                                {order.google_maps_link && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full justify-start text-[#4285F4] border-[#4285F4]/30 hover:bg-[#4285F4]/5"
                                        onClick={() => { const u = order.google_maps_link; if (u != null && u !== '') window.open(u, '_blank'); }}
                                    >
                                        <MapPin className="h-4 w-4 mr-2" /> Navegar com Google Maps
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-surface-900">
                                <Shield className="h-4 w-4 text-brand-500" />
                                Equipamentos
                            </h3>
                            {canUpdate && (
                                <Button variant="ghost" size="sm" onClick={() => setShowEquipmentModal(true)} icon={<Plus className="h-4 w-4" />}>
                                    Vincular
                                </Button>
                            )}
                        </div>
                        {!order.equipment && (!order.equipments_list || (order.equipments_list?.length ?? 0) === 0) ? (
                            <p className="py-4 text-center text-sm text-surface-400">Nenhum equipamento vinculado</p>
                        ) : (
                            <div className="space-y-2">
                                {order.equipment && (
                                    <button onClick={() => navigate(`/cadastros/equipamentos/${order.equipment_id}`)} className="w-full text-left flex items-center gap-2 rounded-lg border border-default p-2.5 hover:bg-surface-50 hover:border-brand-300 transition-colors group">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-surface-800 group-hover:text-brand-600">{order.equipment.type} {order.equipment.brand ?? ''} {order.equipment.model ?? ''}</p>
                                            {order.equipment.serial_number && <p className="text-xs text-surface-400">S/N: {order.equipment.serial_number}</p>}
                                        </div>
                                        <ExternalLink className="h-3.5 w-3.5 text-surface-300 group-hover:text-brand-500 flex-shrink-0" />
                                    </button>
                                )}
                                {((order.equipments_list || []) as WorkOrderEquipmentRef[]).map(eq => (
                                    <div key={eq.id} className="flex items-center gap-2 rounded-lg border border-default p-2.5 hover:bg-surface-50 transition-colors">
                                        <button onClick={() => navigate(`/cadastros/equipamentos/${eq.id}`)} className="flex-1 min-w-0 text-left group">
                                            <p className="text-sm font-medium text-surface-800 group-hover:text-brand-600">{eq.type} {eq.brand ?? ''} {eq.model ?? ''}</p>
                                            {eq.serial_number && <p className="text-xs text-surface-400">S/N: {eq.serial_number}</p>}
                                        </button>
                                        <div className="flex items-center gap-1 flex-shrink-0">
                                            <ExternalLink className="h-3 w-3 text-surface-300" />
                                            {canUpdate && (
                                                <IconButton
                                                    label="Desvincular equipamento"
                                                    icon={<X className="h-3.5 w-3.5" />}
                                                    onClick={() => setDetachEquipId(eq.id)}
                                                    className="hover:text-red-600"
                                                />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Calibrações vinculadas */}
                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-surface-900">
                                <FlaskConical className="h-4 w-4 text-green-600" />
                                Calibrações
                            </h3>
                            {order.equipment_id && canUpdate && (
                                <Button variant="ghost" size="sm" onClick={() => navigate(`/calibracao/wizard/${order.equipment_id}?os=${id}`)} icon={<Plus className="h-4 w-4" />}>
                                    Iniciar Calibração
                                </Button>
                            )}
                        </div>
                        {(order.calibrations?.length ?? 0) > 0 ? (
                            <div className="space-y-2">
                                {((order.calibrations || []) as WorkOrderCalibrationRef[]).map(cal => (
                                    <button key={cal.id} type="button" onClick={() => cal.certificate_number ? navigate(getCalibrationReadingsPath(cal.id)) : navigate(`/calibracao/wizard/${cal.equipment_id}/${cal.id}?os=${id}`)} className="w-full text-left flex items-center gap-2 rounded-lg border border-default p-2.5 hover:bg-surface-50 transition-colors">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-surface-800">{cal.certificate_number || 'Rascunho'}</p>
                                            <p className="text-xs text-surface-400">{cal.calibration_date ? new Date(cal.calibration_date).toLocaleDateString('pt-BR') : '—'} · {cal.result === 'aprovado' ? 'Aprovado' : cal.result === 'reprovado' ? 'Reprovado' : 'Ressalva'}</p>
                                        </div>
                                        {cal.certificate_number ? <CheckCircle2 className="h-4 w-4 text-green-600 shrink-0" /> : <Clock className="h-4 w-4 text-amber-500 shrink-0" />}
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <p className="py-4 text-center text-sm text-surface-400">
                                {order.equipment_id ? 'Nenhuma calibração vinculada. Clique em "Iniciar Calibração" para começar.' : 'Vincule um equipamento para iniciar uma calibração.'}
                            </p>
                        )}
                    </div>

                    {
                        (order.technicians?.length ?? 0) > 0 && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <Users className="h-4 w-4 text-brand-500" />
                                    Técnicos
                                </h3>
                                <div className="flex flex-wrap gap-1.5">
                                    {((order.technicians || []) as { id: number; name: string }[]).map(t => (
                                        <button key={t.id} onClick={() => navigate(`/admin/users/${t.id}`)} className="rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100 transition-colors inline-flex items-center gap-1">
                                            {t.name}
                                            <ExternalLink className="h-2.5 w-2.5" />
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                            <Navigation className="h-4 w-4 text-brand-500" />
                            Deslocamento
                        </h3>
                        {order.dispatch_authorized_at ? (
                            <div className="space-y-2">
                                <Badge variant="success" dot>Autorizado</Badge>
                                <div>
                                    <p className="text-xs text-surface-500">Autorizado em</p>
                                    <p className="text-sm font-medium text-surface-900">{formatDate(order.dispatch_authorized_at)}</p>
                                </div>
                                {order.dispatch_authorizer && (
                                    <div>
                                        <p className="text-xs text-surface-500">Por</p>
                                        <p className="text-sm font-medium text-surface-900">{order.dispatch_authorizer.name}</p>
                                    </div>
                                )}
                                {order.driver && (
                                    <div>
                                        <p className="text-xs text-surface-500">Motorista</p>
                                        <button onClick={() => navigate(`/admin/users/${order.driver_id}`)} className="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline inline-flex items-center gap-1">
                                            {order.driver.name}
                                            <ExternalLink className="h-3 w-3" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <Badge variant="warning" dot>Aguardando autorização</Badge>
                                {order.driver && (
                                    <div>
                                        <p className="text-xs text-surface-500">Motorista designado</p>
                                        <button onClick={() => navigate(`/admin/users/${order.driver_id}`)} className="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline inline-flex items-center gap-1">
                                            {order.driver.name}
                                            <ExternalLink className="h-3 w-3" />
                                        </button>
                                    </div>
                                )}
                                {canAuthorizeDispatch && ['open', 'awaiting_dispatch'].includes(order.status) && !order.dispatch_authorized_at && (
                                    <Button
                                        variant="outline" size="sm" className="w-full"
                                        icon={<Navigation className="h-4 w-4" />}
                                        onClick={() => dispatchMut.mutate()}
                                        loading={dispatchMut.isPending}
                                    >
                                        Autorizar Deslocamento
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Timer de Execução */}
                    <ExecutionTimer workOrderId={order.id} status={order.status} />

                    {/* Timeline de Execução */}
                    <div className="bg-surface-0 rounded-xl shadow-card border border-default p-5">
                        <h3 className="text-sm font-semibold text-surface-900 mb-4">Timeline de Execução</h3>
                        <ExecutionTimeline workOrderId={order.id} />
                    </div>

                    {/* Fotos Antes/Depois */}
                    <BeforeAfterPhotos workOrderId={order.id} canUpload={canUpdate} />

                    {/* Compartilhamento */}
                    <ShareOS
                        workOrderId={order.id}
                        osNumber={String(order.business_number ?? order.os_number ?? order.number ?? '')}
                        customerName={order.customer?.name ?? ''}
                        status={statusConfig[order.status]?.label ?? order.status}
                    />

                    {/* Indicador de Rentabilidade */}
                    {canViewPrices && order.estimated_profit && (
                        <ProfitabilityIndicator
                            revenue={parseFloat(order.estimated_profit.revenue ?? '0')}
                            totalCost={parseFloat(order.estimated_profit.costs ?? '0')}
                        />
                    )}

                    {/* Tags Personalizadas */}
                    <TagManager workOrderId={order.id} currentTags={(order.tags ?? []) as string[]} canEdit={canUpdate} />

                    {/* Histórico do Equipamento */}
                    {order.equipment?.id && (
                        <EquipmentHistory equipmentId={order.equipment.id} currentWorkOrderId={order.id} />
                    )}

                    {/* Relatório Tempo por Técnico */}
                    <TimeReport workOrderId={order.id} />

                    {/* Peças em Falta */}
                    {(order.items?.length ?? 0) > 0 && (
                        <MissingPartsIndicator items={order.items ?? []} />
                    )}

                    {/* QR Code Rastreamento */}
                    <QRTracking
                        workOrderId={order.id}
                        osNumber={String(order.business_number ?? order.os_number ?? order.number ?? '')}
                    />

                    {/* Previsão de Entrega */}
                    <DeliveryForecast
                        workOrderId={order.id}
                        currentForecast={order.delivery_forecast as string | null | undefined}
                        canEdit={canUpdate}
                    />

                    {/* Checklist com Fotos */}
                    <PhotoChecklist
                        workOrderId={order.id}
                        initialChecklist={order.photo_checklist}
                        canEdit={canUpdate}
                    />

                    {/* Cadeia de Aprovação */}
                    <ApprovalChain
                        workOrderId={order.id}
                        currentUserId={user?.id ?? 0}
                    />

                    {/* Estimativa de Custo */}
                    {canViewPrices && (
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <button
                                onClick={() => setShowCostEstimate(prev => !prev)}
                                className="flex w-full items-center justify-between text-sm font-semibold text-surface-900"
                            >
                                <span className="flex items-center gap-2">
                                    <TrendingUp className="h-4 w-4 text-brand-500" />
                                    Estimativa de Custo
                                </span>
                                <span className="text-xs text-surface-400">{showCostEstimate ? '▲' : '▼'}</span>
                            </button>
                            {showCostEstimate && (
                                costEstimateLoading ? (
                                    <div className="mt-3 space-y-2">
                                        <div className="h-4 w-full rounded bg-surface-100 animate-pulse" />
                                        <div className="h-4 w-2/3 rounded bg-surface-100 animate-pulse" />
                                    </div>
                                ) : costEstimateError ? (
                                    <p className="mt-3 text-sm text-red-600">
                                        Nao foi possivel carregar a estimativa de custo desta OS.
                                    </p>
                                ) : costEstimate ? (
                                    <div className="mt-3 space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Peças</span>
                                            <span className="font-medium">{formatBRL(costEstimate.cost_breakdown?.items_cost ?? costEstimate.total_cost)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Comissões</span>
                                            <span className="font-medium">{formatBRL(costEstimate.cost_breakdown?.commission ?? 0)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Deslocamento</span>
                                            <span className="font-medium">{formatBRL(costEstimate.cost_breakdown?.displacement ?? costEstimate.displacement_value)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Subtotal itens</span>
                                            <span className="font-medium">{formatBRL(costEstimate.items_subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Desconto itens</span>
                                            <span className="font-medium">{formatBRL(costEstimate.items_discount)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Desconto global</span>
                                            <span className="font-medium">{formatBRL(costEstimate.global_discount)}</span>
                                        </div>
                                        <div className="flex justify-between border-t border-subtle pt-2">
                                            <span className="font-semibold text-surface-900">Custo Total</span>
                                            <span className="font-semibold">{formatBRL(costEstimate.total_cost)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-surface-500">Receita OS</span>
                                            <span className="font-medium">{formatBRL(costEstimate.revenue)}</span>
                                        </div>
                                        <div className="flex justify-between border-t border-subtle pt-2">
                                            <span className="font-semibold text-surface-900">Total calculado</span>
                                            <span className="font-semibold">{formatBRL(costEstimate.grand_total)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-semibold text-surface-900">Lucro estimado</span>
                                            <span className={cn('font-bold', Number(costEstimate.profit) >= 0 ? 'text-emerald-600' : 'text-red-600')}>
                                                {formatBRL(costEstimate.profit)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between border-t border-subtle pt-2">
                                            <span className="font-semibold text-surface-900">Margem</span>
                                            <span className={cn('font-bold', costEstimate.margin_pct >= 0 ? 'text-emerald-600' : 'text-red-600')}>
                                                {costEstimate.margin_pct}%
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-3 text-sm text-surface-500">
                                        Nenhuma estimativa de custo disponível para esta OS.
                                    </p>
                                )
                            )}
                        </div>
                    )}

                    {/* Comissões geradas */}
                    {canViewPrices && commissionEvents.length > 0 && (
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                <Award className="h-4 w-4 text-brand-500" />
                                Comissões ({commissionEvents.length})
                            </h3>
                            <div className="space-y-2">
                                {commissionEvents.map((ev) => (
                                    <div key={ev.id} className="flex items-center justify-between text-sm py-1 border-b border-subtle last:border-0">
                                        <div className="flex-1 min-w-0">
                                            <span className="font-medium text-surface-900">{ev.user?.name ?? ev.user_name ?? '—'}</span>
                                            <span className="text-xs text-surface-400 ml-2">{ev.rule?.name ?? ev.rule_name ?? ''}</span>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <span className="font-semibold text-emerald-600">{formatBRL(ev.commission_amount)}</span>
                                            <Badge variant={
                                                ev.status === 'paid' ? 'success'
                                                : ev.status === 'approved' ? 'info'
                                                : ev.status === 'reversed' || ev.status === 'cancelled' ? 'danger'
                                                : 'secondary'
                                            }>
                                                {ev.status === 'pending' ? 'Pendente' : ev.status === 'approved' ? 'Aprovada' : ev.status === 'paid' ? 'Paga' : ev.status === 'reversed' ? 'Estornada' : ev.status === 'cancelled' ? 'Cancelada' : ev.status}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* SLA Info */}
                    {
                        (order.sla_due_at || order.sla_responded_at) && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <CalendarDays className="h-4 w-4 text-brand-500" />
                                    SLA
                                </h3>
                                <div className="space-y-2">
                                    {order.sla_due_at && (
                                        <div>
                                            <p className="text-xs text-surface-500">Prazo SLA</p>
                                            <p className="font-medium text-surface-900">{formatDate(order.sla_due_at)}</p>
                                        </div>
                                    )}
                                    {order.sla_responded_at && (
                                        <div>
                                            <p className="text-xs text-surface-500">Respondido em</p>
                                            <p className="font-medium text-surface-900">{formatDate(order.sla_responded_at)}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )
                    }

                    {/* Garantia */}
                    {
                        (order.warranty_until || order.warranty_terms) && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <Shield className="h-4 w-4 text-emerald-500" />
                                    Garantia
                                </h3>
                                <div className="space-y-2">
                                    {order.warranty_until && (
                                        <div>
                                            <p className="text-xs text-surface-500">Válida até</p>
                                            <p className="font-medium text-surface-900">{formatDate(order.warranty_until)}</p>
                                        </div>
                                    )}
                                    {order.warranty_terms && (
                                        <div>
                                            <p className="text-xs text-surface-500">Termos</p>
                                            <p className="text-sm text-surface-700">{order.warranty_terms}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )
                    }

                    {/* Assinatura do Cliente (somente após serviço concluído) */}
                    {
                        canUpdate && ['awaiting_return', 'in_return', 'return_paused', 'completed', 'delivered'].includes(order.status) && !order.signature_path && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <Pencil className="h-4 w-4 text-brand-500" />
                                    Assinatura do Cliente
                                </h3>
                                <SignaturePad
                                    onSave={(data) => signMut.mutate(data)}
                                />
                            </div>
                        )
                    }
                    {
                        order.signature_path && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                    Assinatura Registrada
                                </h3>
                                <div className="space-y-2">
                                    <img
                                        src={buildStorageUrl(order.signature_path) ?? ''}
                                        alt="Assinatura"
                                        className="max-h-24 rounded-lg border border-default"
                                    />
                                    {(order.signature_signer || order.signed_by_name) && (
                                        <p className="text-sm text-surface-600">{order.signature_signer || order.signed_by_name}</p>
                                    )}
                                    {order.signature_at && <p className="text-xs text-surface-400">{formatDate(order.signature_at)}</p>}
                                </div>
                            </div>
                        )
                    }

                    {/* Notas Fiscais */}
                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                            <Receipt className="h-4 w-4 text-brand-500" />
                            Notas Fiscais
                        </h3>
                        {hasPermission('fiscal.manage') && (order.status === 'completed' || order.status === 'delivered' || order.status === 'invoiced') && (
                            <div className="flex flex-wrap gap-2 mb-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon={<Receipt className="h-3.5 w-3.5" />}
                                    onClick={() => setShowEmitNfseConfirm(true)}
                                    loading={emitNfseMut.isPending}
                                >
                                    Emitir NFS-e
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon={<Receipt className="h-3.5 w-3.5" />}
                                    onClick={() => setShowEmitNfeConfirm(true)}
                                    loading={emitNfeMut.isPending}
                                >
                                    Emitir NF-e
                                </Button>
                            </div>
                        )}
                        {fiscalNotes.length === 0 ? (
                            <div className="text-center py-6">
                                <Receipt className="mx-auto h-8 w-8 text-surface-300" />
                                <p className="text-sm text-surface-400 mt-2">Nenhuma nota fiscal vinculada a esta OS</p>
                                <Link to="/fiscal/notas" className="text-xs text-brand-600 hover:underline mt-1 inline-block">Ir para módulo Fiscal →</Link>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-default text-xs font-medium text-surface-600 uppercase">
                                        <tr>
                                            <th className="pb-2 pr-3">Tipo</th>
                                            <th className="pb-2 pr-3">Número</th>
                                            <th className="pb-2 pr-3">Status</th>
                                            <th className="pb-2 pr-3">Valor</th>
                                            <th className="pb-2 pr-3">Emissão</th>
                                            <th className="pb-2">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-subtle">
                                        {fiscalNotes.map(fn => {
                                            const statusCfg: Record<string, { label: string; cls: string }> = {
                                                pending: { label: 'Pendente', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
                                                processing: { label: 'Processando', cls: 'bg-sky-50 text-sky-700 border-sky-200' },
                                                authorized: { label: 'Autorizada', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                                                cancelled: { label: 'Cancelada', cls: 'bg-red-50 text-red-700 border-red-200' },
                                                rejected: { label: 'Rejeitada', cls: 'bg-red-50 text-red-700 border-red-200' },
                                            }
                                            const sc = statusCfg[fn.status] ?? { label: fn.status, cls: 'bg-surface-100 text-surface-600' }
                                            return (
                                                <tr key={fn.id} className="hover:bg-surface-50/50">
                                                    <td className="py-2 pr-3 font-medium">{fn.type === 'nfe' ? 'NF-e' : 'NFS-e'}</td>
                                                    <td className="py-2 pr-3">{fn.number ?? '—'}</td>
                                                    <td className="py-2 pr-3">
                                                        <span className={cn('inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium', sc.cls)}>
                                                            {sc.label}
                                                        </span>
                                                    </td>
                                                    <td className="py-2 pr-3">{formatCurrency(parseFloat(fn.total_amount) || 0)}</td>
                                                    <td className="py-2 pr-3 text-surface-500">{fn.issued_at ? new Date(fn.issued_at).toLocaleDateString('pt-BR') : '—'}</td>
                                                    <td className="py-2">
                                                        <div className="flex gap-1">
                                                            {fn.pdf_url && (
                                                                <a href={fn.pdf_url} target="_blank" rel="noopener noreferrer" className="text-xs text-brand-600 hover:underline" aria-label="Baixar PDF">PDF</a>
                                                            )}
                                                            {fn.xml_url && (
                                                                <a href={fn.xml_url} target="_blank" rel="noopener noreferrer" className="text-xs text-brand-600 hover:underline" aria-label="Baixar XML">XML</a>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            )
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Timeline / Histórico de Status */}
                    {
                        (order.status_history?.length ?? 0) > 0 && (
                            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                                    <Clock className="h-4 w-4 text-brand-500" />
                                    Histórico
                                </h3>
                                <div className="space-y-3">
                                    {(order.status_history || []).map((h: StatusHistoryEntry) => {
                                        const cfg = statusConfig[h.to_status]
                                        return (
                                            <div key={h.id} className="flex items-start gap-3">
                                                <div className="flex-shrink-0 mt-1">
                                                    <div className={cn('h-2.5 w-2.5 rounded-full', cfg ? `bg-${cfg.variant === 'info' ? 'sky' : cfg.variant === 'warning' ? 'amber' : cfg.variant === 'success' ? 'emerald' : cfg.variant === 'danger' ? 'red' : 'brand'}-500` : 'bg-surface-300')} />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-surface-800">{cfg?.label ?? h.to_status}</p>
                                                    {h.notes && <p className="text-xs text-surface-500 mt-0.5">{h.notes}</p>}
                                                    <p className="text-xs text-surface-400 mt-0.5">
                                                        {h.user?.name ?? 'Sistema'} · {formatDate(h.created_at)}
                                                    </p>
                                                </div>
                                            </div>
                                        )
                                    })}
                                </div>
                            </div>
                        )
                    }

                </div>
            </div>

            <Modal open={showItemModal} onOpenChange={(open: boolean) => { setShowItemModal(open); if (!open) setEditingItem(null) }} title={editingItem ? "Editar Item" : "Adicionar Item"} >
                <form onSubmit={(e: React.FormEvent) => {
                    e.preventDefault()
                    const payload = { ...itemForm, reference_id: itemForm.reference_id || null, warehouse_id: itemForm.warehouse_id || null }
                    if (editingItem) {
                        updateItemMut.mutate(payload)
                    } else {
                        addItemMut.mutate(payload)
                    }
                }} className="space-y-4">
                    <div className="flex rounded-lg border border-default overflow-hidden">
                        {(['product', 'service'] as const).map(t => (
                            <button key={t} type="button" onClick={() => setItemForm(p => ({ ...p, type: t, reference_id: '' }))}
                                className={cn('flex-1 py-2 text-sm font-medium transition-colors',
                                    itemForm.type === t ? (t === 'product' ? 'bg-brand-50 text-brand-700' : 'bg-emerald-50 text-emerald-700') : 'text-surface-500')}>
                                {t === 'product' ? 'Produto' : 'Serviço'}
                            </button>
                        ))}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">
                            {itemForm.type === 'product' ? 'Produto' : 'Serviço'}
                        </label>
                        <ItemSearchCombobox
                            items={itemForm.type === 'product' ? products : services}
                            type={itemForm.type}
                            value={itemForm.reference_id ? Number(itemForm.reference_id) : null}
                            onSelect={(id) => handleRefChange(String(id))}
                            placeholder={`Selecionar ${itemForm.type === 'product' ? 'produto' : 'serviço'}`}
                            className="w-full"
                        />
                    </div>
                    {canViewPrices && itemForm.reference_id && order?.customer_id && (
                        <PriceHistoryHint
                            customerId={order.customer_id}
                            type={itemForm.type}
                            referenceId={itemForm.reference_id}
                            onApplyPrice={(price) => setItemForm(p => ({ ...p, unit_price: String(price) }))}
                        />
                    )}
                    <Input label="Descrição" value={itemForm.description}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setItemForm(p => ({ ...p, description: e.target.value }))} required />
                    <div className={`grid gap-3 ${canViewPrices ? 'grid-cols-3' : 'grid-cols-1'}`}>
                        <Input label="Qtd" type="number" step="0.01" value={itemForm.quantity}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setItemForm(p => ({ ...p, quantity: e.target.value }))} />
                        {canViewPrices && (
                            <>
                                <CurrencyInput label="Preço Un." value={parseFloat(itemForm.unit_price) || 0}
                                    onChange={(val) => setItemForm(p => ({ ...p, unit_price: String(val) }))} />
                                <CurrencyInput label="Desconto" value={parseFloat(itemForm.discount) || 0}
                                    onChange={(val) => setItemForm(p => ({ ...p, discount: String(val) }))} />
                            </>
                        )}
                    </div>
                    {canChooseWarehouse && itemForm.type === 'product' && (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Armazém de origem</label>
                            <select title="Armazém" value={itemForm.warehouse_id}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setItemForm(p => ({ ...p, warehouse_id: e.target.value || '' }))}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="">Automático (central/técnico)</option>
                                {warehouses.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
                            </select>
                        </div>
                    )}
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" type="button" onClick={() => setShowItemModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={addItemMut.isPending || updateItemMut.isPending}>
                            {editingItem ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <QrScannerModal
                open={showQrScanner}
                onClose={() => setShowQrScanner(false)}
                onScan={handleQrScanned}
                title="Escanear etiqueta (QR da peça)"
            />

            <Modal open={showStatusModal} onOpenChange={(open: boolean) => setShowStatusModal(open)} title="Alterar Status" >
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-2">
                        {Object.entries(statusConfig)
                            .filter(([k]) => (order.allowed_transitions ?? []).includes(k))
                            .map(([k, v]) => (
                                <button key={k} onClick={() => setNewStatus(k)}
                                    className={cn('flex items-center gap-2 rounded-lg border p-3 text-sm transition-all',
                                        newStatus === k ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-default hover:border-surface-400')}>
                                    <v.icon className={cn('h-4 w-4', newStatus === k ? 'text-brand-600' : 'text-surface-400')} />
                                    <span className={cn('font-medium', newStatus === k ? 'text-surface-900' : 'text-surface-600')}>{v.label}</span>
                                </button>
                            ))}
                        {(order.allowed_transitions ?? []).length === 0 && (
                            <p className="col-span-2 py-4 text-center text-sm text-surface-400">Este status é final. Não há transições disponíveis.</p>
                        )}
                    </div>
                    <Input
                        label={newStatus === 'cancelled' ? 'Motivo do cancelamento *' : 'Observações (Opcional)'}
                        value={statusNotes}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setStatusNotes(e.target.value)}
                        placeholder={newStatus === 'cancelled' ? 'Informe o motivo do cancelamento...' : 'Observações sobre a mudança de status...'}
                    />
                    {(newStatus === 'delivered' || newStatus === 'invoiced') && (
                        <>
                            <div>
                                <label htmlFor="agreed-payment-method" className="mb-1.5 block text-sm font-medium text-surface-700">Forma de pagamento acordada com o cliente *</label>
                                <select
                                    id="agreed-payment-method"
                                    aria-label="Forma de pagamento acordada com o cliente"
                                    value={agreedPaymentMethod}
                                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setAgreedPaymentMethod(e.target.value)}
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                                >
                                    <option value="">Selecionar forma de pagamento</option>
                                    {AGREED_PAYMENT_OPTIONS.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Input
                                label="Observações sobre o pagamento (opcional)"
                                value={agreedPaymentNotes}
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setAgreedPaymentNotes(e.target.value)}
                                placeholder="Ex.: parcelado em 2x, vencimento dia 10"
                            />
                        </>
                    )}
                    {newStatus === 'cancelled' && !statusNotes.trim() && (
                        <p className="text-xs text-red-500">O motivo do cancelamento é obrigatório.</p>
                    )}
                    {(newStatus === 'delivered' || newStatus === 'invoiced') && !agreedPaymentMethod && (
                        <p className="text-xs text-red-500">Informe a forma de pagamento acordada com o cliente.</p>
                    )}
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" onClick={() => setShowStatusModal(false)}>Cancelar</Button>
                        <Button
                            onClick={() => statusMut.mutate({
                                status: newStatus,
                                notes: statusNotes,
                                agreed_payment_method: (newStatus === 'delivered' || newStatus === 'invoiced') ? agreedPaymentMethod : undefined,
                                agreed_payment_notes: (newStatus === 'delivered' || newStatus === 'invoiced') ? agreedPaymentNotes : undefined,
                            })}
                            disabled={
                                !newStatus
                                || (newStatus === 'cancelled' && !statusNotes.trim())
                                || ((newStatus === 'delivered' || newStatus === 'invoiced') && !agreedPaymentMethod)
                            }
                            loading={statusMut.isPending}
                        >
                            Confirmar
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal open={deleteItemId !== null} onOpenChange={(open: boolean) => { if (!open) setDeleteItemId(null) }} title="Confirmar Remoção" >
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja remover este item? Esta ação não pode ser desfeita.</p>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setDeleteItemId(null)}>Cancelar</Button>
                        <Button
                            variant="danger"
                            onClick={() => { if (deleteItemId) delItemMut.mutate(deleteItemId) }}
                            loading={delItemMut.isPending}
                        >
                            Remover
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal open={deleteAttachId !== null} onOpenChange={(open: boolean) => { if (!open) setDeleteAttachId(null) }} title="Confirmar Remoção" >
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja remover este anexo? Esta ação não pode ser desfeita.</p>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setDeleteAttachId(null)}>Cancelar</Button>
                        <Button
                            variant="danger"
                            onClick={() => { if (deleteAttachId) { deleteAttachmentMut.mutate(deleteAttachId) } }}
                            loading={deleteAttachmentMut.isPending}
                        >
                            Remover
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Vincular Equipamento */}
            <Modal open={showEquipmentModal} onOpenChange={(open: boolean) => setShowEquipmentModal(open)} title="Vincular Equipamento">
                <div className="space-y-3">
                    {customerEquipments.length === 0 ? (
                        <p className="py-4 text-center text-sm text-surface-400">
                            Nenhum equipamento encontrado para este cliente.
                        </p>
                    ) : (
                        <div className="max-h-64 space-y-2 overflow-y-auto">
                            {(customerEquipments as WorkOrderEquipmentRef[])
                                .filter(eq => {
                                    const list = (order?.equipments_list ?? []) as WorkOrderEquipmentRef[]
                                    const alreadyAttached = list.some(attached => attached.id === eq.id) ||
                                        order?.equipment?.id === eq.id
                                    return !alreadyAttached
                                })
                                .map(eq => (
                                    <button
                                        key={eq.id}
                                        className="flex w-full items-center gap-3 rounded-lg border border-default p-3 text-left transition-colors hover:border-brand-500 hover:bg-brand-50"
                                        onClick={() => attachEquipMut.mutate(eq.id)}
                                        disabled={attachEquipMut.isPending}
                                    >
                                        <Shield className="h-4 w-4 text-surface-400 flex-shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-surface-800 truncate">{eq.type} {eq.brand ?? ''} {eq.model ?? ''}</p>
                                            {eq.serial_number && <p className="text-xs text-surface-400">S/N: {eq.serial_number}</p>}
                                        </div>
                                    </button>
                                ))
                            }
                            {((customerEquipments || []) as WorkOrderEquipmentRef[]).filter(eq => {
                                const list = (order?.equipments_list ?? []) as WorkOrderEquipmentRef[]
                                return !list.some(attached => attached.id === eq.id) && order?.equipment?.id !== eq.id
                            }).length === 0 && (
                                    <p className="py-4 text-center text-sm text-surface-400">
                                        Todos os equipamentos do cliente já estão vinculados.
                                    </p>
                                )}
                        </div>
                    )}
                    <div className="flex justify-end pt-2">
                        <Button variant="outline" onClick={() => setShowEquipmentModal(false)}>Fechar</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Desvinculação */}
            <Modal open={detachEquipId !== null} onOpenChange={(open: boolean) => { if (!open) setDetachEquipId(null) }} title="Desvincular Equipamento">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja desvincular este equipamento da OS?</p>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setDetachEquipId(null)}>Cancelar</Button>
                        <Button
                            variant="danger"
                            onClick={() => { if (detachEquipId) detachEquipMut.mutate(detachEquipId) }}
                            loading={detachEquipMut.isPending}
                        >
                            Desvincular
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Aplicar Kit de Peças */}
            <Modal open={showKitModal} onOpenChange={(open: boolean) => setShowKitModal(open)} title="Aplicar Kit de Peças">
                <div className="space-y-3">
                    <p className="text-sm text-surface-500">Selecione um kit para adicionar todos os seus itens automaticamente à OS.</p>
                    {partsKits.length === 0 ? (
                        <p className="py-4 text-center text-sm text-surface-400">
                            Nenhum kit de peças cadastrado.
                        </p>
                    ) : (
                        <div className="max-h-64 space-y-2 overflow-y-auto">
                            {(partsKits || []).map((kit: PartsKit) => (
                                <button
                                    key={kit.id}
                                    className="flex w-full items-center gap-3 rounded-lg border border-default p-3 text-left transition-colors hover:border-brand-500 hover:bg-brand-50"
                                    onClick={() => applyKitMut.mutate(kit.id)}
                                    disabled={applyKitMut.isPending}
                                >
                                    <Layers className="h-4 w-4 text-surface-400 flex-shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-surface-800">{kit.name}</p>
                                        <p className="text-xs text-surface-400">{kit.items_count ?? kit.items?.length ?? '—'} itens</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="flex justify-end pt-2">
                        <Button variant="outline" onClick={() => setShowKitModal(false)}>Fechar</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Desfaturamento */}
            <Modal open={showUninvoiceConfirm} onOpenChange={(open: boolean) => { if (!open) setShowUninvoiceConfirm(false) }} title="Desfaturar Ordem de Serviço">
                <div className="space-y-4">
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p className="text-sm text-amber-800 font-medium">⚠️ Atenção</p>
                        <p className="text-sm text-amber-700 mt-1">Isso irá cancelar a Invoice e os títulos financeiros vinculados a esta OS.</p>
                    </div>
                    <p className="text-sm text-surface-600">Tem certeza que deseja desfaturar esta OS?</p>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setShowUninvoiceConfirm(false)}>Cancelar</Button>
                        <Button variant="danger" onClick={() => uninvoiceMut.mutate()} loading={uninvoiceMut.isPending}>Desfaturar</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Gerar Conta a Receber */}
            <Modal open={showReceivableConfirm} onOpenChange={(open: boolean) => { if (!open) setShowReceivableConfirm(false) }} title="Gerar Conta a Receber">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Uma conta a receber será criada no módulo financeiro com o valor total desta OS.</p>
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                        <p className="text-sm text-blue-800">💰 Valor: <span className="font-semibold">{order ? formatCurrency(order.total) : '—'}</span></p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setShowReceivableConfirm(false)}>Cancelar</Button>
                        <Button variant="brand" onClick={() => generateReceivableMut.mutate()} loading={generateReceivableMut.isPending}>Gerar Conta</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Dedução de Estoque */}
            <Modal open={showDeductStockConfirm} onOpenChange={(open: boolean) => { if (!open) setShowDeductStockConfirm(false) }} title="Deduzir Estoque">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">O estoque será deduzido automaticamente para todos os itens do tipo <strong>produto</strong> desta OS.</p>
                    {(order?.items ?? []).filter(i => i.type === 'product').length > 0 ? (
                        <div className="rounded-lg border border-default bg-surface-50 p-3 space-y-1">
                            {(order?.items ?? []).filter(i => i.type === 'product').map((item, idx) => (
                                <p key={idx} className="text-xs text-surface-700">• {item.description} — Qtd: {item.quantity}</p>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-surface-400 italic">Nenhum item de produto encontrado.</p>
                    )}
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setShowDeductStockConfirm(false)}>Cancelar</Button>
                        <Button variant="brand" onClick={() => autoDeductStockMut.mutate()} loading={autoDeductStockMut.isPending}>Deduzir Estoque</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Emissão NFS-e */}
            <Modal open={showEmitNfseConfirm} onOpenChange={(open: boolean) => { if (!open) setShowEmitNfseConfirm(false) }} title="Emitir NFS-e">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Uma Nota Fiscal de Serviço Eletrônica (NFS-e) será emitida com base nos itens de serviço desta OS.</p>
                    <div className="rounded-lg border border-green-200 bg-green-50 p-3">
                        <p className="text-sm text-green-800">📄 A nota será enviada para a prefeitura para autorização.</p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setShowEmitNfseConfirm(false)}>Cancelar</Button>
                        <Button variant="brand" onClick={() => emitNfseMut.mutate()} loading={emitNfseMut.isPending}>Emitir NFS-e</Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirmar Emissão NF-e */}
            <Modal open={showEmitNfeConfirm} onOpenChange={(open: boolean) => { if (!open) setShowEmitNfeConfirm(false) }} title="Emitir NF-e">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Uma Nota Fiscal Eletrônica (NF-e) será emitida com base nos itens de produto desta OS.</p>
                    <div className="rounded-lg border border-green-200 bg-green-50 p-3">
                        <p className="text-sm text-green-800">📄 A nota será enviada para a SEFAZ para autorização.</p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setShowEmitNfeConfirm(false)}>Cancelar</Button>
                        <Button variant="brand" onClick={() => emitNfeMut.mutate()} loading={emitNfeMut.isPending}>Emitir NF-e</Button>
                    </div>
                </div>
            </Modal>

        </div>
    )
}
