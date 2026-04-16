export type AssetCategory =
    | 'machinery'
    | 'vehicle'
    | 'equipment'
    | 'furniture'
    | 'it'
    | 'tooling'
    | 'other'

export type AssetStatus = 'active' | 'suspended' | 'disposed' | 'fully_depreciated'
export type DepreciationMethod = 'linear' | 'accelerated' | 'units_produced'
export type CiapCreditType = 'icms_full' | 'icms_48' | 'none'
export type DisposalReason = 'sale' | 'loss' | 'scrap' | 'donation' | 'theft'

export interface AssetRelationshipUser {
    id: number
    name: string
}

export interface AssetRelationshipSupplier {
    id: number
    name: string
}

export interface AssetRelationshipVehicle {
    id: number
    plate: string
    brand?: string | null
    model?: string | null
}

export interface FixedAssetCrmDeal {
    id: number
    title: string
    status: string
    value?: string | number | null
}

export interface FixedAssetDepreciationLog {
    id: number
    reference_month: string
    depreciation_amount: string
    accumulated_before: string
    accumulated_after: string
    book_value_after: string
    method_used: DepreciationMethod
    ciap_installment_number?: number | null
    ciap_credit_value?: string | null
    generated_by: 'automatic_job' | 'manual'
}

export interface FixedAssetDisposal {
    id: number
    disposal_date: string
    reason: DisposalReason
    disposal_value?: string | null
    book_value_at_disposal: string
    gain_loss: string
    notes?: string | null
    approved_by: number
    created_by: number
}

export interface FixedAssetMovement {
    id: number
    asset_record_id: number
    movement_type: 'transfer' | 'assignment' | 'maintenance' | 'inventory_adjustment'
    from_location?: string | null
    to_location?: string | null
    moved_at: string
    notes?: string | null
    asset_record?: Pick<FixedAsset, 'id' | 'code' | 'name'> | null
    to_responsible_user?: AssetRelationshipUser | null
    creator?: AssetRelationshipUser | null
}

export interface FixedAssetInventory {
    id: number
    asset_record_id: number
    inventory_date: string
    counted_location?: string | null
    counted_status?: AssetStatus | null
    condition_ok: boolean
    divergent: boolean
    offline_reference?: string | null
    synced_from_pwa: boolean
    notes?: string | null
    asset_record?: Pick<FixedAsset, 'id' | 'code' | 'name' | 'location' | 'status'> | null
    counted_by?: AssetRelationshipUser | null
}

export interface FixedAsset {
    id: number
    code: string
    name: string
    description?: string | null
    category: AssetCategory
    acquisition_date: string
    acquisition_value: string
    residual_value: string
    useful_life_months: number
    depreciation_method: DepreciationMethod
    depreciation_rate: string
    accumulated_depreciation: string
    current_book_value: string
    status: AssetStatus
    location?: string | null
    responsible_user_id?: number | null
    responsible_user?: AssetRelationshipUser | null
    supplier_id?: number | null
    supplier?: AssetRelationshipSupplier | null
    fleet_vehicle_id?: number | null
    fleet_vehicle?: AssetRelationshipVehicle | null
    crm_deal_id?: number | null
    crm_deal?: FixedAssetCrmDeal | null
    nf_number?: string | null
    nf_serie?: string | null
    ciap_credit_type?: CiapCreditType | null
    ciap_total_installments?: number | null
    ciap_installments_taken?: number | null
    last_depreciation_at?: string | null
    disposed_at?: string | null
    disposal_reason?: DisposalReason | null
    disposal_value?: string | null
    depreciation_logs?: FixedAssetDepreciationLog[]
    disposals?: FixedAssetDisposal[]
}

export interface PaginatedEnvelope<T> {
    data: T[]
    meta?: {
        current_page: number
        per_page: number
        total: number
        last_page?: number
    }
}

export interface FixedAssetsDashboard {
    total_assets: number
    total_acquisition_value: number
    total_current_book_value: number
    total_accumulated_depreciation: number
    by_category: Record<string, { count: number; book_value: number }>
    disposals_this_year: number
    ciap_credits_pending: number
}

export interface FixedAssetFilters {
    category?: AssetCategory | ''
    status?: AssetStatus | ''
    location?: string
    responsible_user_id?: string
    per_page?: number
}

export interface FixedAssetPayload {
    name: string
    description?: string
    category: AssetCategory
    acquisition_date: string
    acquisition_value: number
    residual_value: number
    useful_life_months: number
    depreciation_method: DepreciationMethod
    location?: string
    responsible_user_id?: number
    supplier_id?: number
    fleet_vehicle_id?: number
    nf_number?: string
    nf_serie?: string
    ciap_credit_type?: CiapCreditType
}

export interface DisposeAssetPayload {
    disposal_date: string
    reason: DisposalReason
    disposal_value?: number
    notes?: string
    approved_by: number
}

export interface RunDepreciationPayload {
    reference_month: string
}

export interface FixedAssetMovementPayload {
    movement_type: 'transfer' | 'assignment' | 'maintenance' | 'inventory_adjustment'
    to_location?: string
    to_responsible_user_id?: number
    moved_at: string
    notes?: string
}

export interface FixedAssetInventoryPayload {
    inventory_date: string
    counted_location?: string
    counted_status?: AssetStatus
    condition_ok?: boolean
    offline_reference?: string
    synced_from_pwa?: boolean
    notes?: string
}
