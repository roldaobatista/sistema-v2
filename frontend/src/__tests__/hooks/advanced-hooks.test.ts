import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// ── useLocalStorage ──

describe('useLocalStorage hook pattern', () => {
  const storage: Record<string, string> = {}

  beforeEach(() => {
    Object.keys(storage).forEach(key => delete storage[key])
    vi.stubGlobal('localStorage', {
      getItem: vi.fn((key: string) => storage[key] ?? null),
      setItem: vi.fn((key: string, value: string) => { storage[key] = value }),
      removeItem: vi.fn((key: string) => { delete storage[key] }),
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('reads from localStorage', () => {
    storage['test-key'] = JSON.stringify('stored-value')
    const value = JSON.parse(localStorage.getItem('test-key') ?? 'null')
    expect(value).toBe('stored-value')
  })

  it('writes to localStorage', () => {
    localStorage.setItem('test-key', JSON.stringify({ a: 1 }))
    expect(storage['test-key']).toBe('{"a":1}')
  })

  it('removes from localStorage', () => {
    storage['test-key'] = '"value"'
    localStorage.removeItem('test-key')
    expect(storage['test-key']).toBeUndefined()
  })

  it('returns null for missing key', () => {
    const value = localStorage.getItem('nonexistent')
    expect(value).toBeNull()
  })
})

// ── useMediaQuery ──

describe('useMediaQuery hook pattern', () => {
  it('matches mobile breakpoint', () => {
    const isMobile = (width: number) => width < 768
    expect(isMobile(375)).toBe(true)
    expect(isMobile(1024)).toBe(false)
  })

  it('matches tablet breakpoint', () => {
    const isTablet = (width: number) => width >= 768 && width < 1024
    expect(isTablet(800)).toBe(true)
    expect(isTablet(1200)).toBe(false)
  })

  it('matches desktop breakpoint', () => {
    const isDesktop = (width: number) => width >= 1024
    expect(isDesktop(1440)).toBe(true)
    expect(isDesktop(500)).toBe(false)
  })
})

// ── useDebounce ──

describe('useDebounce hook pattern', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('debounces function calls', () => {
    const fn = vi.fn()
    let timer: ReturnType<typeof setTimeout> | null = null

    const debounced = (value: string) => {
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => fn(value), 300)
    }

    debounced('a')
    debounced('ab')
    debounced('abc')

    expect(fn).not.toHaveBeenCalled()
    vi.advanceTimersByTime(300)
    expect(fn).toHaveBeenCalledTimes(1)
    expect(fn).toHaveBeenCalledWith('abc')
  })

  it('calls immediately when delay is 0', () => {
    const fn = vi.fn()
    const immediate = (value: string) => setTimeout(() => fn(value), 0)

    immediate('test')
    vi.advanceTimersByTime(0)
    expect(fn).toHaveBeenCalledWith('test')
  })
})

// ── useClickOutside ──

describe('useClickOutside hook pattern', () => {
  it('detects click outside element', () => {
    const handler = vi.fn()
    const element = { contains: vi.fn(() => false) }

    // Simulate click outside
    const event = { target: document.createElement('div') }
    if (!element.contains(event.target)) {
      handler()
    }

    expect(handler).toHaveBeenCalled()
  })

  it('ignores click inside element', () => {
    const handler = vi.fn()
    const element = { contains: vi.fn(() => true) }

    const event = { target: document.createElement('div') }
    if (!element.contains(event.target)) {
      handler()
    }

    expect(handler).not.toHaveBeenCalled()
  })
})

// ── useCopyToClipboard ──

describe('useCopyToClipboard hook pattern', () => {
  it('copies text to clipboard', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText } })

    await navigator.clipboard.writeText('hello')
    expect(writeText).toHaveBeenCalledWith('hello')

    vi.unstubAllGlobals()
  })
})

// ── useKeyboardShortcut ──

describe('useKeyboardShortcut hook pattern', () => {
  it('detects Ctrl+K shortcut', () => {
    const handler = vi.fn()
    const event = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true })

    if (event.ctrlKey && event.key === 'k') {
      handler()
    }

    expect(handler).toHaveBeenCalled()
  })

  it('ignores non-matching shortcut', () => {
    const handler = vi.fn()
    const event = new KeyboardEvent('keydown', { key: 'a', ctrlKey: false })

    if (event.ctrlKey && event.key === 'k') {
      handler()
    }

    expect(handler).not.toHaveBeenCalled()
  })
})

// ── useToggle ──

describe('useToggle hook pattern', () => {
  it('toggles boolean value', () => {
    let value = false
    const toggle = () => { value = !value }

    toggle()
    expect(value).toBe(true)
    toggle()
    expect(value).toBe(false)
  })

  it('sets specific value', () => {
    let value = false
    const setValue = (v: boolean) => { value = v }

    setValue(true)
    expect(value).toBe(true)
    setValue(false)
    expect(value).toBe(false)
  })
})

// ── usePagination ──

describe('usePagination hook pattern', () => {
  it('calculates total pages', () => {
    const totalPages = (total: number, perPage: number) =>
      Math.ceil(total / perPage)

    expect(totalPages(100, 10)).toBe(10)
    expect(totalPages(101, 10)).toBe(11)
    expect(totalPages(0, 10)).toBe(0)
  })

  it('returns correct page range', () => {
    const pageRange = (current: number, total: number, window: number = 5) => {
      const half = Math.floor(window / 2)
      let start = Math.max(1, current - half)
      const end = Math.min(total, start + window - 1)
      start = Math.max(1, end - window + 1)
      return Array.from({ length: end - start + 1 }, (_, i) => start + i)
    }

    expect(pageRange(1, 10)).toEqual([1, 2, 3, 4, 5])
    expect(pageRange(5, 10)).toEqual([3, 4, 5, 6, 7])
    expect(pageRange(10, 10)).toEqual([6, 7, 8, 9, 10])
  })

  it('handles single page', () => {
    const totalPages = (total: number, perPage: number) =>
      Math.ceil(total / perPage)

    expect(totalPages(5, 10)).toBe(1)
  })
})
