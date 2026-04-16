import { describe, it, expect, beforeEach } from 'vitest'

// ── Notification Store ──

interface NotificationItem {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  message: string
  read: boolean
  createdAt: Date
}

class NotificationStore {
  notifications: NotificationItem[] = []

  add(type: NotificationItem['type'], message: string): string {
    const id = Math.random().toString(36).slice(2)
    this.notifications.push({ id, type, message, read: false, createdAt: new Date() })
    return id
  }

  markRead(id: string): void {
    const n = this.notifications.find(n => n.id === id)
    if (n) n.read = true
  }

  markAllRead(): void {
    this.notifications.forEach(n => n.read = true)
  }

  unreadCount(): number {
    return this.notifications.filter(n => !n.read).length
  }

  remove(id: string): void {
    this.notifications = this.notifications.filter(n => n.id !== id)
  }

  clear(): void {
    this.notifications = []
  }
}

describe('Notification Store', () => {
  let store: NotificationStore

  beforeEach(() => { store = new NotificationStore() })

  it('starts empty', () => { expect(store.notifications).toHaveLength(0) })
  it('adds notification', () => {
    store.add('success', 'Test')
    expect(store.notifications).toHaveLength(1)
  })
  it('tracks unread count', () => {
    store.add('info', 'A')
    store.add('warning', 'B')
    expect(store.unreadCount()).toBe(2)
  })
  it('marks single as read', () => {
    const id = store.add('info', 'A')
    store.markRead(id)
    expect(store.unreadCount()).toBe(0)
  })
  it('marks all as read', () => {
    store.add('info', 'A')
    store.add('info', 'B')
    store.markAllRead()
    expect(store.unreadCount()).toBe(0)
  })
  it('removes notification', () => {
    const id = store.add('error', 'A')
    store.remove(id)
    expect(store.notifications).toHaveLength(0)
  })
  it('clears all notifications', () => {
    store.add('info', 'A')
    store.add('info', 'B')
    store.clear()
    expect(store.notifications).toHaveLength(0)
  })
})

// ── Form Validation Helpers ──

function isValidCpf(cpf: string): boolean {
  const clean = cpf.replace(/\D/g, '')
  if (clean.length !== 11 || /^(\d)\1{10}$/.test(clean)) return false
  let sum = 0
  for (let i = 0; i < 9; i++) sum += parseInt(clean[i]) * (10 - i)
  let rest = (sum * 10) % 11
  if (rest === 10) rest = 0
  if (rest !== parseInt(clean[9])) return false
  sum = 0
  for (let i = 0; i < 10; i++) sum += parseInt(clean[i]) * (11 - i)
  rest = (sum * 10) % 11
  if (rest === 10) rest = 0
  return rest === parseInt(clean[10])
}

function isValidCnpj(cnpj: string): boolean {
  const clean = cnpj.replace(/\D/g, '')
  if (clean.length !== 14 || /^(\d)\1{13}$/.test(clean)) return false
  const weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  let sum = 0
  for (let i = 0; i < 12; i++) sum += parseInt(clean[i]) * weights1[i]
  let rest = sum % 11
  const d1 = rest < 2 ? 0 : 11 - rest
  if (d1 !== parseInt(clean[12])) return false
  sum = 0
  for (let i = 0; i < 13; i++) sum += parseInt(clean[i]) * weights2[i]
  rest = sum % 11
  const d2 = rest < 2 ? 0 : 11 - rest
  return d2 === parseInt(clean[13])
}

function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

function isValidPhone(phone: string): boolean {
  return /^\d{10,11}$/.test(phone.replace(/\D/g, ''))
}

function isValidCep(cep: string): boolean {
  return /^\d{8}$/.test(cep.replace(/\D/g, ''))
}

describe('CPF Validation', () => {
  it('valid CPF passes', () => { expect(isValidCpf('529.982.247-25')).toBe(true) })
  it('invalid CPF fails', () => { expect(isValidCpf('111.111.111-11')).toBe(false) })
  it('short CPF fails', () => { expect(isValidCpf('123')).toBe(false) })
  it('unmasked valid CPF passes', () => { expect(isValidCpf('52998224725')).toBe(true) })
})

describe('CNPJ Validation', () => {
  it('valid CNPJ passes', () => { expect(isValidCnpj('11.222.333/0001-81')).toBe(true) })
  it('invalid CNPJ fails', () => { expect(isValidCnpj('11.111.111/1111-11')).toBe(false) })
  it('short CNPJ fails', () => { expect(isValidCnpj('123')).toBe(false) })
})

describe('Email Validation', () => {
  it('valid email passes', () => { expect(isValidEmail('user@domain.com')).toBe(true) })
  it('invalid email fails', () => { expect(isValidEmail('invalid')).toBe(false) })
  it('email without @ fails', () => { expect(isValidEmail('user.domain.com')).toBe(false) })
  it('email without domain fails', () => { expect(isValidEmail('user@')).toBe(false) })
})

describe('Phone Validation', () => {
  it('11-digit phone passes', () => { expect(isValidPhone('11999887766')).toBe(true) })
  it('10-digit phone passes', () => { expect(isValidPhone('1133224455')).toBe(true) })
  it('short phone fails', () => { expect(isValidPhone('123')).toBe(false) })
  it('masked phone passes', () => { expect(isValidPhone('(11) 99988-7766')).toBe(true) })
})

describe('CEP Validation', () => {
  it('valid CEP passes', () => { expect(isValidCep('01310100')).toBe(true) })
  it('masked CEP passes', () => { expect(isValidCep('01310-100')).toBe(true) })
  it('short CEP fails', () => { expect(isValidCep('123')).toBe(false) })
})

// ── Pagination Helper ──

interface PaginationInfo {
  currentPage: number
  totalPages: number
  perPage: number
  total: number
}

function getPageNumbers(info: PaginationInfo, maxVisible: number = 5): number[] {
  const { currentPage, totalPages } = info
  if (totalPages <= maxVisible) return Array.from({ length: totalPages }, (_, i) => i + 1)
  const half = Math.floor(maxVisible / 2)
  let start = Math.max(1, currentPage - half)
  const end = Math.min(totalPages, start + maxVisible - 1)
  if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
}

describe('Pagination Helper', () => {
  it('shows all pages when few', () => {
    const pages = getPageNumbers({ currentPage: 1, totalPages: 3, perPage: 10, total: 30 })
    expect(pages).toEqual([1, 2, 3])
  })

  it('centers around current page', () => {
    const pages = getPageNumbers({ currentPage: 5, totalPages: 10, perPage: 10, total: 100 })
    expect(pages).toContain(5)
    expect(pages).toHaveLength(5)
  })

  it('handles first page', () => {
    const pages = getPageNumbers({ currentPage: 1, totalPages: 20, perPage: 10, total: 200 })
    expect(pages[0]).toBe(1)
  })

  it('handles last page', () => {
    const pages = getPageNumbers({ currentPage: 20, totalPages: 20, perPage: 10, total: 200 })
    expect(pages[pages.length - 1]).toBe(20)
  })
})
