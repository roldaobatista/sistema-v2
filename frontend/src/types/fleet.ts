export interface FleetVehicle {
  id: number
  tenant_id?: number
  plate?: string
  brand?: string
  model?: string
  year?: number | string
  color?: string
  type?: string
  fuel_type?: string
  status?: string
  odometer_km?: number | string
  renavam?: string
  chassis?: string
  crlv_expiry?: string | null
  insurance_expiry?: string | null
  next_maintenance?: string | null
  tire_change_date?: string | null
  purchase_value?: number | string
  notes?: string | null
  cost_per_km?: number | string
  avg_fuel_consumption?: number | string
  cnh_expiry_driver?: string | null
  assigned_user_id?: number | null
  assignedUser?: { id?: number; name?: string | null } | null
  inspections_count?: number
  fines_count?: number
  created_at?: string
  updated_at?: string
}

export interface VehicleInspection {
  id: number
  fleet_vehicle_id: number
  date: string
  odometer_km?: number
  status: string
  notes?: string | null
  created_at?: string
}

export interface TrafficFine {
  id: number
  fleet_vehicle_id: number
  date: string
  amount: number | string
  infraction?: string
  status: string
  notes?: string | null
}

export interface FuelLog {
  id: number
  fleet_vehicle_id?: number
  date: string
  liters: number | string
  total_cost: number | string
  odometer_km?: number
  fuel_type?: string
  station?: string | null
  notes?: string | null
}

export interface VehicleAccident {
  id: number
  fleet_vehicle_id: number
  date: string
  description?: string
  damage_amount?: number | string
  status: string
  notes?: string | null
}

export interface VehicleOption {
  id: number
  plate?: string
  name?: string
}
