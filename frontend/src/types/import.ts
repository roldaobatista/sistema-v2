export type ImportEntity = 'customers' | 'products' | 'services' | 'equipments' | 'suppliers'

export type ImportStep = 0 | 1 | 2 | 3

export type DuplicateStrategy = 'skip' | 'update' | 'create'

export interface ImportFieldDef {
  key: string
  label: string
  required: boolean
}

export interface UploadResult {
  file_path: string
  file_name: string
  encoding: string
  separator: string
  headers: string[]
  total_rows: number
  entity_type: string
  available_fields: ImportFieldDef[]
}

export interface PreviewRow {
  line: number
  data: Record<string, string>
  status: string
  messages: string[]
}

export interface ImportResult {
  import_id: number
  total_rows: number
  inserted: number
  updated: number
  skipped: number
  errors: number
  error_log: Array<{ line: number; message: string; data: Record<string, string> }>
}

export interface EntityStats {
  total_imports: number
  success_rate: number
  total_inserted: number
  total_updated: number
  last_import_at?: string
}

export interface ImportHistoryItem {
  id: number
  file_name: string
  original_name?: string
  entity_type: ImportEntity
  status: string
  inserted: number
  updated: number
  skipped: number
  errors: number
  total_rows: number
  created_at: string
  user?: { name: string }
  duplicate_strategy?: DuplicateStrategy
  separator?: string
  mapping?: Record<string, string>
  error_log?: Array<{ line: number; message: string }>
}
