/**
 * Reference Data API — Centralized queries for products, services, users, etc.
 * Used by WorkOrderCreatePage, WorkOrderDetailPage, and other components
 * that need lookup data for combo boxes, selects, etc.
 */
import api from './api'
import { safeArray } from './safe-array'

export interface ProductOrServiceRef {
  id: number
  name: string
  sell_price?: string
  default_price?: string
  code?: string | null
}

export interface UserRef {
  id: number
  name: string
  email?: string
}

export interface BranchRef {
  id: number
  name: string
}

export interface WarehouseRef {
  id: number
  name: string
}

export interface ChecklistRef {
  id: number
  name: string
}

export interface EquipmentRef {
  id: number
  type: string
  brand?: string | null
  model?: string | null
  serial_number?: string | null
}

export interface PartsKitRef {
  id: number
  name: string
  items_count?: number
  items?: { id: number; name: string; quantity: number; unit_price?: string | number }[]
}

export const refDataApi = {
  products: (params?: { per_page?: number; is_active?: boolean }) =>
    api.get('/products', { params: { per_page: 500, is_active: true, ...params } })
      .then((r) => safeArray<ProductOrServiceRef>(r.data)),

  services: (params?: { per_page?: number; is_active?: boolean }) =>
    api.get('/services', { params: { per_page: 500, is_active: true, ...params } })
      .then((r) => safeArray<ProductOrServiceRef>(r.data)),

  technicians: (params?: { per_page?: number }) =>
    api.get('/users/by-role/tecnico', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<UserRef>(r.data)),

  allUsers: (params?: { per_page?: number }) =>
    api.get('/users', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<UserRef>(r.data)),

  branches: (params?: { per_page?: number }) =>
    api.get('/branches', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<BranchRef>(r.data)),

  warehouses: (params?: { per_page?: number }) =>
    api.get('/stock/warehouses', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<WarehouseRef>(r.data)),

  checklists: (params?: { per_page?: number }) =>
    api.get('/service-checklists', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<ChecklistRef>(r.data)),

  customerEquipments: (customerId: number, params?: { per_page?: number }) =>
    api.get('/equipments', { params: { customer_id: customerId, per_page: 100, ...params } })
      .then((r) => safeArray<EquipmentRef>(r.data)),

  partsKits: (params?: { per_page?: number }) =>
    api.get('/parts-kits', { params: { per_page: 100, ...params } })
      .then((r) => safeArray<PartsKitRef>(r.data)),

  checklistDetail: (id: number) =>
    api.get(`/service-checklists/${id}`),

  productDetail: (id: number) =>
    api.get(`/products/${id}`),
}
