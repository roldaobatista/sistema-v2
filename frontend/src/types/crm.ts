export interface CrmPipelineStage {
  id: number
  pipeline_id: number
  name: string
  color: string | null
  sort_order: number
  probability: number
  is_won: boolean
  is_lost: boolean
  deals_count?: number
  deals_sum_value?: number
}

export interface CrmPipeline {
  id: number
  name: string
  slug: string
  color: string | null
  is_default: boolean
  is_active: boolean
  sort_order: number
  stages: CrmPipelineStage[]
}

export interface CrmDeal {
  id: number
  tenant_id: number
  customer_id: number
  pipeline_id: number
  stage_id: number
  title: string
  value: number
  probability: number
  expected_close_date: string | null
  source: string | null
  assigned_to: number | null
  quote_id: number | null
  work_order_id: number | null
  equipment_id: number | null
  status: 'open' | 'won' | 'lost'
  won_at: string | null
  lost_at: string | null
  lost_reason: string | null
  loss_reason_id: number | null
  competitor_name: string | null
  competitor_price: number | null
  score: number | null
  notes: string | null
  created_at: string
  updated_at: string
  customer?: { id: number; name: string; phone?: string; email?: string; health_score?: number }
  stage?: CrmPipelineStage
  pipeline?: { id: number; name: string }
  assignee?: { id: number; name: string }
  quote?: { id: number; quote_number: string; total: number; status: string }
  work_order?: { id: number; number: string; os_number?: string | null; business_number?: string | null; status: string; total: number }
  equipment?: { id: number; code: string; brand: string; model: string }
  activities?: CrmActivity[]
}

export interface CrmActivity {
  id: number
  type: string
  customer_id: number
  deal_id: number | null
  contact_id: number | null
  user_id: number
  title: string
  description: string | null
  scheduled_at: string | null
  completed_at: string | null
  duration_minutes: number | null
  outcome: string | null
  channel: string | null
  is_automated: boolean
  metadata: Record<string, unknown> | null
  created_at: string
  customer?: { id: number; name: string }
  deal?: { id: number; title: string }
  contact?: { id: number; name: string }
  user?: { id: number; name: string }
}

export interface CrmDashboardData {
  period?: {
    label: string
    start: string
    end: string
    period: 'month' | 'quarter' | 'year'
  }
  kpis: {
    open_deals: number
    won_month: number
    lost_month: number
    revenue_in_pipeline: number
    won_revenue: number
    avg_health_score: number
    no_contact_90d: number
    conversion_rate: number
  }
  previous_period?: {
    won_month: number
    lost_month: number
    won_revenue: number
    open_deals?: number
    revenue_in_pipeline?: number
    avg_health_score?: number
    no_contact_90d?: number
    conversion_rate?: number
  }
  email_tracking?: {
    total_sent: number
    opened: number
    clicked: number
    replied: number
    bounced: number
  }
  messaging_stats?: {
    sent_month: number
    received_month: number
    whatsapp_sent: number
    email_sent: number
    delivered: number
    failed?: number
    delivery_rate?: number
  }
  pipelines?: CrmPipeline[]
  recent_deals?: CrmDeal[]
  upcoming_activities?: CrmActivity[]
  top_customers?: { customer_id: number; total_value: number; deal_count: number; customer_name?: string | null; customer?: { id: number; name: string } | null }[]
  calibration_alerts?: { id: number; code: string; brand: string; model: string; customer_id: number; next_calibration_at: string; customer?: { id: number; name: string } }[]
}
