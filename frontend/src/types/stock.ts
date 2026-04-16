export interface Product {
  id: number
  tenant_id?: number
  category_id?: number | null
  code: string | null
  name: string
  description?: string | null
  unit?: string
  cost_price: number | string
  sell_price: number | string
  stock_qty: number | string
  stock_min: number | string
  is_active: boolean
  track_stock?: boolean
  is_kit?: boolean
  track_batch?: boolean
  track_serial?: boolean
  min_repo_point?: number | string | null
  max_stock?: number | string | null
  default_supplier_id?: number | null
  manufacturer_code?: string | null
  storage_location?: string | null
  ncm?: string | null
  image_url?: string | null
  barcode?: string | null
  brand?: string | null
  weight?: number | string | null
  width?: number | string | null
  height?: number | string | null
  depth?: number | string | null
  // Computed
  profit_margin?: number | null
  markup?: number | null
  volume?: number | null
  // Relations
  category?: { id: number; name: string } | null
  equipment_models?: { id: number; name: string; brand?: string | null; category?: string | null }[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export type WarehouseType = 'fixed' | 'vehicle' | 'technician'

export interface Warehouse {
  id: number
  name: string
  code: string
  type: WarehouseType
  is_active: boolean
  user_id: number | null
  vehicle_id: number | null
  user?: { id: number; name: string } | null
  vehicle?: { id: number; plate: string } | null
  created_at?: string
}

export interface WarehouseOption {
  id: number
  name: string
}

export interface Batch {
  id: number
  code?: string
  batch_number?: string
  product_id: number
  product?: { id: number; name: string; code?: string }
  expires_at: string | null
  manufacturing_date?: string | null
  cost_price?: number
  status?: string
  created_at?: string
}

export type StockMovementType =
  | 'entry'
  | 'exit'
  | 'reserve'
  | 'return'
  | 'adjustment'
  | 'transfer'

export interface StockMovement {
  id: number
  product: { id: number; name: string; code: string | null }
  work_order: { id: number; number: string; os_number?: string | null; business_number?: string | null } | null
  type: StockMovementType
  quantity: string
  unit_cost: string
  reference: string | null
  notes: string | null
  warehouse: { id: number; name: string } | null
  created_by_user: { id: number; name: string } | null
  created_at: string
}

export type StockIntegrationTab = 'quotes' | 'requests' | 'tags' | 'rma' | 'disposal'

export interface StockIntegrationPaginatedData {
  data?: unknown[]
  last_page?: number
  total?: number
  meta?: { last_page?: number; total?: number }
}

export interface StockIntegrationQuoteRow {
  id: number
  reference: string
  title: string
  items?: unknown[]
  suppliers?: unknown[]
  deadline: string
  status: string
}

export interface StockIntegrationRequestRow {
  id: number
  reference: string
  requester?: { name: string }
  items?: unknown[]
  priority: string
  status: string
  created_at: string
}

export interface StockIntegrationTagRow {
  id: number
  tag_code: string
  tag_type: string
  location?: string
  status: string
  last_scanned_at?: string
  last_scanner?: { name: string }
}

export interface StockIntegrationRmaRow {
  id: number
  rma_number: string
  type: string
  customer?: { name: string }
  items?: unknown[]
  status: string
  created_at: string
}

export interface StockIntegrationDisposalRow {
  id: number
  reference: string
  disposal_type: string
  disposal_method: string
  items?: unknown[]
  status: string
  created_at: string
}

export interface StockIntegrationFormItemPayload {
  product_id: number
  quantity: number
  quantity_requested: number
  specifications?: string
  defect_description?: string
}

export interface StockIntegrationDetailItemEntry {
  product_id: number
  product?: { name: string }
  quantity?: number
  quantity_requested?: number
  specifications?: string
  defect_description?: string
}

export interface StockIntegrationDetailSupplierEntry {
  supplier_id: number
  supplier?: { name: string }
  status: string
}

export interface StockIntegrationDetailRecord {
  id: number
  reference?: string
  rma_number?: string
  tag_code?: string
  title?: string
  status?: string
  priority?: string
  deadline?: string
  type?: string
  created_at?: string
  requester?: { name: string }
  customer?: { name: string }
  notes?: string
  reason?: string
  justification?: string
  items?: StockIntegrationDetailItemEntry[]
  suppliers?: StockIntegrationDetailSupplierEntry[]
}

/* ─── PWA Inventory Types ───────────────────────────────── */

export interface PwaWarehouse {
  id: number
  name: string
  code?: string | null
  type: WarehouseType
  vehicle_id?: number | null
  vehicle?: { id: number; plate: string } | null
}

export interface PwaProductItem {
  product_id: number
  product: { id: number; name: string; code?: string | null; unit?: string }
  expected_quantity: number
}

export interface PwaCountSubmission {
  warehouse_id: number
  items: Array<{ product_id: number; counted_quantity: number }>
}

export interface PwaCountResponse {
  inventory_id: number
  has_discrepancy: boolean
  data: unknown
  message?: string
}
