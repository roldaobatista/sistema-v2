export interface Role {
  id: number
  name: string
  display_name?: string | null
  label?: string
  description?: string | null
  permissions_count?: number
  users_count?: number
  permissions?: PermissionEntry[]
  is_protected?: boolean
}

export interface PermissionEntry {
  id: number
  name: string
  criticality?: string | null
}

export interface PermissionGroup {
  id: number
  name: string
  permissions: PermissionEntry[]
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  roles: Role[]
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  last_login_at: string | null
  created_at: string | null
}

export interface Branch {
  id: number
  name: string
}

export interface Session {
  id: number
  name: string | null
  last_used_at: string | null
  expires_at: string | null
}

export interface AuditEntry {
  id?: number
  action: string
  description: string
  created_at: string | null
  ip_address: string | null
}
