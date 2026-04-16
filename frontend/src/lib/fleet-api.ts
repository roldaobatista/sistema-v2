import api, { unwrapData } from './api'
import type { FleetVehicle, FuelLog, VehicleAccident } from '@/types/fleet'

export interface FleetDashboard {
  total_vehicles: number
  active_vehicles: number
  in_maintenance: number
  avg_fuel_consumption: number
  total_fines: number
  total_accidents: number
  pending_inspections: number
  alerts: Array<{ type: string; message: string; vehicle_id?: number }>
}

export interface DriverScore {
  user_id: number
  user_name: string
  score: number
  trips: number
  fines: number
  accidents: number
}

export interface VehicleInspection {
  id: number
  fleet_vehicle_id: number
  date: string
  odometer_km?: number
  status: string
  notes?: string | null
  inspector_name?: string
  vehicle?: FleetVehicle
}

export interface VehicleInsurance {
  id: number
  fleet_vehicle_id: number
  provider: string
  policy_number?: string
  start_date: string
  end_date: string
  amount: number | string
  status: string
  vehicle?: FleetVehicle
}

export interface TollTransaction {
  id: number
  fleet_vehicle_id: number
  date: string
  location?: string
  amount: number | string
  tag_id?: string
}

export const fleetApi = {
  // ─── Dashboard ───
  dashboard: () =>
    api.get<{ data: FleetDashboard }>('/fleet/dashboard').then(r => unwrapData<FleetDashboard>(r)),

  // ─── Vehicles ───
  vehicles: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: FleetVehicle[] }>('/fleet/vehicles', { params }),
    show: (id: number) =>
      api.get<{ data: FleetVehicle }>(`/fleet/vehicles/${id}`),
    create: (data: Partial<FleetVehicle>) =>
      api.post('/fleet/vehicles', data),
    update: (id: number, data: Partial<FleetVehicle>) =>
      api.put(`/fleet/vehicles/${id}`, data),
    delete: (id: number) =>
      api.delete(`/fleet/vehicles/${id}`),
  },

  // ─── Inspections ───
  inspections: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: VehicleInspection[] }>('/fleet/inspections', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/inspections', data),
    delete: (id: number) =>
      api.delete(`/fleet/inspections/${id}`),
  },

  // ─── Fines ───
  fines: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: unknown[] }>('/fleet/fines', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/fines', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/fleet/fines/${id}`, data),
    delete: (id: number) =>
      api.delete(`/fleet/fines/${id}`),
  },

  // ─── Fuel Logs ───
  fuelLogs: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: FuelLog[] }>('/fleet/fuel-logs', { params }),
    show: (id: number) =>
      api.get<{ data: FuelLog }>(`/fleet/fuel-logs/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/fuel-logs', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/fleet/fuel-logs/${id}`, data),
    delete: (id: number) =>
      api.delete(`/fleet/fuel-logs/${id}`),
  },

  // ─── Accidents ───
  accidents: {
    list: () =>
      api.get<{ data: VehicleAccident[] }>('/fleet/accidents'),
    show: (id: number) =>
      api.get<{ data: VehicleAccident }>(`/fleet/accidents/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/accidents', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/fleet/accidents/${id}`, data),
    delete: (id: number) =>
      api.delete(`/fleet/accidents/${id}`),
  },

  // ─── Insurance ───
  insurances: {
    list: () =>
      api.get<{ data: VehicleInsurance[] }>('/fleet/insurances'),
    alerts: () =>
      api.get<{ data: unknown[] }>('/fleet/insurances/alerts'),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/insurances', data),
    delete: (id: number) =>
      api.delete(`/fleet/insurances/${id}`),
  },

  // ─── Tires ───
  tires: {
    list: () =>
      api.get<{ data: unknown[] }>('/fleet/tires'),
    show: (id: number) =>
      api.get<{ data: unknown }>(`/fleet/tires/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/tires', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/fleet/tires/${id}`, data),
    delete: (id: number) =>
      api.delete(`/fleet/tires/${id}`),
  },

  // ─── Pool Requests ───
  poolRequests: {
    list: () =>
      api.get<{ data: unknown[] }>('/fleet/pool-requests'),
    show: (id: number) =>
      api.get<{ data: unknown }>(`/fleet/pool-requests/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/pool-requests', data),
    updateStatus: (id: number, data: { status: string }) =>
      api.patch(`/fleet/pool-requests/${id}/status`, data),
    delete: (id: number) =>
      api.delete(`/fleet/pool-requests/${id}`),
  },

  // ─── Tolls ───
  tolls: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: TollTransaction[] }>('/fleet/tolls', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/fleet/tolls', data),
    dashboard: (params?: Record<string, unknown>) =>
      api.get<{ data: unknown }>('/fleet/tolls/dashboard', { params }),
  },

  // ─── Driver Score ───
  driverScore: {
    ranking: () =>
      api.get<{ data: DriverScore[] }>('/fleet/driver-ranking'),
    show: (driverId: number) =>
      api.get<{ data: DriverScore }>(`/fleet/driver-score/${driverId}`),
  },

  // ─── Analytics ───
  analytics: () =>
    api.get<{ data: unknown }>('/fleet/analytics'),
}

export default fleetApi
