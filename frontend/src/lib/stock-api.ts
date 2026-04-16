import api from './api'
import type { PwaWarehouse, PwaProductItem, PwaCountSubmission, PwaCountResponse } from '@/types/stock'

export const stockApi = {
  summary: () =>
    api.get('/stock/summary'),
  lowAlerts: () =>
    api.get('/stock/low-alerts'),
  warehouses: {
    list: (params?: Record<string, unknown>) =>
      api.get('/warehouses', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/warehouses', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/warehouses/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/warehouses/${id}`),
  },
  movements: {
    list: (params?: Record<string, unknown>) =>
      api.get('/stock/movements', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/stock/movements', data),
    importXml: (formData: FormData) =>
      api.post('/stock/import-xml', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  },
  warehousesOptions: () =>
    api.get('/stock/warehouses'),
  inventories: {
    list: (params?: Record<string, unknown>) =>
      api.get('/stock/inventories', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/stock/inventories', data),
    detail: (id: number) =>
      api.get(`/stock/inventories/${id}`),
    updateItem: (inventoryId: number, itemId: number, data: { counted_quantity: number }) =>
      api.put(`/stock/inventories/${inventoryId}/items/${itemId}`, data),
    complete: (id: number) =>
      api.post(`/stock/inventories/${id}/complete`),
  },
  transfers: {
    list: (params?: Record<string, unknown>) =>
      api.get('/stock/transfers', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/stock/transfers', data),
    accept: (id: number) =>
      api.post(`/stock/transfers/${id}/accept`),
    reject: (id: number, data: { rejection_reason: string }) =>
      api.post(`/stock/transfers/${id}/reject`, data),
    suggest: () =>
      api.get('/stock-advanced/transfers/suggest'),
  },
  batches: {
    list: (params?: Record<string, unknown>) =>
      api.get('/batches', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/batches', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/batches/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/batches/${id}`),
  },
  serialNumbers: {
    list: (params?: Record<string, unknown>) =>
      api.get('/stock/serial-numbers', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/stock/serial-numbers', data),
  },
  kardex: (productId: number, params?: Record<string, unknown>) =>
    api.get(`/stock/products/${productId}/kardex`, { params }),
  usedItems: {
    list: (params?: Record<string, unknown>) =>
      api.get('/stock/used-items', { params }),
    report: (id: number, data: { disposition_type: string; disposition_notes?: string }) =>
      api.post(`/stock/used-items/${id}/report`, data),
    confirmReturn: (id: number) =>
      api.post(`/stock/used-items/${id}/confirm-return`),
    confirmWriteOff: (id: number) =>
      api.post(`/stock/used-items/${id}/confirm-write-off`),
  },
  intelligence: {
    abcCurve: (params: { months?: number }) =>
      api.get('/stock/intelligence/abc-curve', { params }),
    turnover: (params: { months?: number }) =>
      api.get('/stock/intelligence/turnover', { params }),
    averageCost: () =>
      api.get('/stock/intelligence/average-cost'),
    reorderPoints: () =>
      api.get('/stock/intelligence/reorder-points'),
    expiringBatches: (params?: { days?: number }) =>
      api.get('/stock/intelligence/expiring-batches', { params }),
    staleProducts: (params?: { days?: number }) =>
      api.get('/stock/intelligence/stale-products', { params }),
  },
  purchaseQuotes: {
    list: (params?: Record<string, unknown>) =>
      api.get('/purchase-quotes', { params }),
    detail: (id: number) =>
      api.get(`/purchase-quotes/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/purchase-quotes', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/purchase-quotes/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/purchase-quotes/${id}`),
  },
  materialRequests: {
    list: (params?: Record<string, unknown>) =>
      api.get('/material-requests', { params }),
    detail: (id: number) =>
      api.get(`/material-requests/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/material-requests', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/material-requests/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/material-requests/${id}`),
  },
  assetTags: {
    list: (params?: Record<string, unknown>) =>
      api.get('/asset-tags', { params }),
    detail: (id: number) =>
      api.get(`/asset-tags/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/asset-tags', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/asset-tags/${id}`, data),
    scan: (id: number) =>
      api.post(`/asset-tags/${id}/scan`),
  },
  rma: {
    list: (params?: Record<string, unknown>) =>
      api.get('/rma', { params }),
    detail: (id: number) =>
      api.get(`/rma/${id}`),
    create: (data: Record<string, unknown>) =>
      api.post('/rma', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/rma/${id}`, data),
  },
  inventoryPwa: {
    myWarehouses: () =>
      api.get<{ data: PwaWarehouse[] }>('/stock/inventory-pwa/my-warehouses'),
    warehouseProducts: (warehouseId: number) =>
      api.get<{ data: PwaProductItem[] }>(`/stock/inventory-pwa/warehouses/${warehouseId}/products`),
    submitCounts: (data: PwaCountSubmission) =>
      api.post<PwaCountResponse>('/stock/inventory-pwa/submit-counts', data),
  },
}
