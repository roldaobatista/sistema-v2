import { describe, it, expect } from 'vitest'

// ── Final comprehensive batch — mixed helpers ──

// ── Date range validation ──

function isValidDateRange(from: Date | null, to: Date | null): { valid: boolean; error?: string } {
  if (!from) return { valid: false, error: 'Data inicial obrigatória' }
  if (!to) return { valid: false, error: 'Data final obrigatória' }
  if (from > to) return { valid: false, error: 'Data inicial maior que a final' }
  const diffDays = Math.round((to.getTime() - from.getTime()) / (1000 * 60 * 60 * 24))
  if (diffDays > 365) return { valid: false, error: 'Intervalo máximo de 1 ano' }
  return { valid: true }
}

describe('Date Range Validation', () => {
  it('valid range', () => expect(isValidDateRange(new Date(2026, 0, 1), new Date(2026, 6, 1)).valid).toBe(true))
  it('no from', () => expect(isValidDateRange(null, new Date()).error).toBe('Data inicial obrigatória'))
  it('no to', () => expect(isValidDateRange(new Date(), null).error).toBe('Data final obrigatória'))
  it('from > to', () => expect(isValidDateRange(new Date(2027, 0, 1), new Date(2026, 0, 1)).error).toBe('Data inicial maior que a final'))
  it('too long', () => expect(isValidDateRange(new Date(2024, 0, 1), new Date(2026, 0, 1)).error).toBe('Intervalo máximo de 1 ano'))
})

// ── Search debounce logic ──

function shouldTriggerSearch(query: string, minLength: number = 2): boolean {
  const trimmed = query.trim()
  return trimmed.length >= minLength
}

describe('Search Trigger', () => {
  it('empty → false', () => expect(shouldTriggerSearch('')).toBe(false))
  it('1 char → false', () => expect(shouldTriggerSearch('a')).toBe(false))
  it('2 chars → true', () => expect(shouldTriggerSearch('ab')).toBe(true))
  it('spaces trimmed', () => expect(shouldTriggerSearch('  a  ')).toBe(false))
  it('custom min', () => expect(shouldTriggerSearch('abc', 4)).toBe(false))
})

// ── File Size Formatter ──

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const k = 1024
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${units[i]}`
}

describe('File Size Formatter', () => {
  it('0 bytes', () => expect(formatFileSize(0)).toBe('0 B'))
  it('500 bytes', () => expect(formatFileSize(500)).toBe('500 B'))
  it('1024 → 1 KB', () => expect(formatFileSize(1024)).toBe('1 KB'))
  it('1.5 MB', () => expect(formatFileSize(1572864)).toBe('1.5 MB'))
  it('1 GB', () => expect(formatFileSize(1073741824)).toBe('1 GB'))
})

// ── Receipt/Attachment Validators ──

const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp']
const MAX_FILE_SIZE = 10 * 1024 * 1024 // 10MB

function validateFile(fileName: string, fileSize: number): string | null {
  const ext = fileName.split('.').pop()?.toLowerCase() ?? ''
  if (!ALLOWED_EXTENSIONS.includes(ext)) return `Extensão .${ext} não permitida`
  if (fileSize > MAX_FILE_SIZE) return 'Arquivo excede 10MB'
  if (fileSize === 0) return 'Arquivo vazio'
  return null
}

describe('File Validation', () => {
  it('valid PDF', () => expect(validateFile('nota.pdf', 500000)).toBeNull())
  it('valid JPG', () => expect(validateFile('foto.jpg', 2000000)).toBeNull())
  it('invalid ext', () => expect(validateFile('virus.exe', 1000)).toContain('não permitida'))
  it('too large', () => expect(validateFile('big.pdf', 20 * 1024 * 1024)).toContain('10MB'))
  it('empty file', () => expect(validateFile('empty.pdf', 0)).toBe('Arquivo vazio'))
})

// ── Pagination Meta ──

interface PageMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

function getPageRange(meta: PageMeta): string {
  const from = (meta.currentPage - 1) * meta.perPage + 1
  const to = Math.min(meta.currentPage * meta.perPage, meta.total)
  return `${from}-${to} de ${meta.total}`
}

function getPageNumbers(current: number, last: number, windowSize: number = 5): number[] {
  const half = Math.floor(windowSize / 2)
  let start = Math.max(1, current - half)
  const end = Math.min(last, start + windowSize - 1)
  if (end - start + 1 < windowSize) start = Math.max(1, end - windowSize + 1)
  const pages: number[] = []
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
}

describe('Page Range', () => {
  it('page 1', () => expect(getPageRange({ currentPage: 1, lastPage: 5, perPage: 10, total: 47 })).toBe('1-10 de 47'))
  it('last page', () => expect(getPageRange({ currentPage: 5, lastPage: 5, perPage: 10, total: 47 })).toBe('41-47 de 47'))
  it('single page', () => expect(getPageRange({ currentPage: 1, lastPage: 1, perPage: 10, total: 3 })).toBe('1-3 de 3'))
})

describe('Page Numbers', () => {
  it('first pages', () => expect(getPageNumbers(1, 10)).toEqual([1, 2, 3, 4, 5]))
  it('middle pages', () => expect(getPageNumbers(5, 10)).toEqual([3, 4, 5, 6, 7]))
  it('last pages', () => expect(getPageNumbers(10, 10)).toEqual([6, 7, 8, 9, 10]))
  it('less than window', () => expect(getPageNumbers(1, 3)).toEqual([1, 2, 3]))
})

// ── Color Conversion ──

function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  return result
    ? { r: parseInt(result[1], 16), g: parseInt(result[2], 16), b: parseInt(result[3], 16) }
    : null
}

function getContrastColor(hex: string): 'black' | 'white' {
  const rgb = hexToRgb(hex)
  if (!rgb) return 'black'
  const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255
  return luminance > 0.5 ? 'black' : 'white'
}

describe('Hex to RGB', () => {
  it('#ff0000 → red', () => expect(hexToRgb('#ff0000')).toEqual({ r: 255, g: 0, b: 0 }))
  it('#10b981 → green', () => {
    const result = hexToRgb('#10b981')!
    expect(result.r).toBe(16)
    expect(result.g).toBe(185)
  })
  it('invalid → null', () => expect(hexToRgb('invalid')).toBeNull())
})

describe('Contrast Color', () => {
  it('white bg → black text', () => expect(getContrastColor('#ffffff')).toBe('black'))
  it('black bg → white text', () => expect(getContrastColor('#000000')).toBe('white'))
  it('dark blue → white', () => expect(getContrastColor('#1e3a5f')).toBe('white'))
  it('light yellow → black', () => expect(getContrastColor('#fef3c7')).toBe('black'))
})
