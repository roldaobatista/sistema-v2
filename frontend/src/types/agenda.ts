import type { ComponentType } from 'react'

export type CentralIconComponent = ComponentType<{ className?: string }>
export type CentralBadgeVariant = 'default' | 'info' | 'warning' | 'success' | 'danger'

export interface CentralUser {
  id: number
  name: string
}

export interface CentralSubtask {
  id: number
  titulo: string
  concluido: boolean
}

export interface CentralAttachment {
  id: number
  path: string
  nome: string
  uploader?: { name: string }
  size?: number
}

export interface CentralComment {
  id: number
  body: string
  user?: { name: string }
  created_at: string
}

export interface CentralHistoryEntry {
  id: number
  action: string
  from_value?: string
  to_value?: string
  created_at: string
  user?: { name: string }
}

export interface CentralTimeEntry {
  id: number
  user?: { name: string }
  stopped_at?: string
  duration_seconds: number
}

export interface CentralDependency {
  id: number
  titulo: string
  status: string
}

export interface CentralWatcher {
  id: number
  user_id: number
  role: string
  notify_status_change: boolean
  notify_comment: boolean
  notify_due_date: boolean
  notify_assignment: boolean
  added_by_type: string
  user?: CentralUser
  added_by?: CentralUser
}

export interface CentralItem {
  id: number
  titulo: string
  descricao_curta?: string | null
  status: string
  tipo: string
  prioridade: string
  visibilidade?: string
  due_at?: string | null
  remind_at?: string | null
  created_at: string
  responsavel?: CentralUser | null
  responsavelUser_id?: number | null
  criado_por?: CentralUser | null
  criado_porUser_id?: number | null
  comments_count?: number
  recurrence_pattern?: string | null
  tags?: string[]
  ref_tipo?: string
  ref_id?: number
  subtasks?: CentralSubtask[]
  attachments?: CentralAttachment[]
  comments?: CentralComment[]
  history?: CentralHistoryEntry[]
  time_entries?: CentralTimeEntry[]
  depends_on?: CentralDependency[]
  watchers?: CentralWatcher[]
  snooze_until?: string | null
  visibilityUsers?: number[]
  visibility_departments?: number[]
}

/** Preset de filtros salvos na Central (localStorage) */
export interface CentralFilterPreset {
  name: string
  tab: string
  tipo: string
  prioridade: string
  scope: string
  responsavel: number | ''
  sortBy: string
  sortDir: string
}
