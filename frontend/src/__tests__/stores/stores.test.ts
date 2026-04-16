import { describe, it, expect, beforeEach } from 'vitest'

// ── Mock Store Implementation ──

interface AuthState {
  user: { id: number; name: string; email: string; tenant_id: number } | null
  token: string | null
  isAuthenticated: boolean
  login: (user: AuthState['user'], token: string) => void
  logout: () => void
}

const createAuthStore = () => {
  const state: AuthState = {
    user: null,
    token: null,
    isAuthenticated: false,
    login: (user, token) => {
      state.user = user
      state.token = token
      state.isAuthenticated = true
    },
    logout: () => {
      state.user = null
      state.token = null
      state.isAuthenticated = false
    },
  }
  return state
}

describe('Auth Store', () => {
  let store: AuthState

  beforeEach(() => {
    store = createAuthStore()
  })

  it('starts with no user', () => {
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })

  it('logs in user', () => {
    store.login({ id: 1, name: 'Admin', email: 'admin@test.com', tenant_id: 1 }, 'abc123')
    expect(store.user?.name).toBe('Admin')
    expect(store.token).toBe('abc123')
    expect(store.isAuthenticated).toBe(true)
  })

  it('logs out user', () => {
    store.login({ id: 1, name: 'Admin', email: 'admin@test.com', tenant_id: 1 }, 'abc123')
    store.logout()
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })
})

// ── Sidebar Store ──

interface SidebarState {
  isOpen: boolean
  toggle: () => void
  open: () => void
  close: () => void
}

const createSidebarStore = () => {
  const state: SidebarState = {
    isOpen: true,
    toggle: () => { state.isOpen = !state.isOpen },
    open: () => { state.isOpen = true },
    close: () => { state.isOpen = false },
  }
  return state
}

describe('Sidebar Store', () => {
  let store: SidebarState

  beforeEach(() => {
    store = createSidebarStore()
  })

  it('starts open', () => {
    expect(store.isOpen).toBe(true)
  })

  it('toggles state', () => {
    store.toggle()
    expect(store.isOpen).toBe(false)
    store.toggle()
    expect(store.isOpen).toBe(true)
  })

  it('closes explicitly', () => {
    store.close()
    expect(store.isOpen).toBe(false)
  })

  it('opens explicitly', () => {
    store.close()
    store.open()
    expect(store.isOpen).toBe(true)
  })
})

// ── Filter Store ──

interface FilterState {
  search: string
  status: string[]
  page: number
  perPage: number
  setSearch: (val: string) => void
  setStatus: (val: string[]) => void
  setPage: (val: number) => void
  reset: () => void
}

const createFilterStore = () => {
  const defaults = { search: '', status: [], page: 1, perPage: 20 }
  const state: FilterState = {
    ...defaults,
    setSearch: (val) => { state.search = val; state.page = 1 },
    setStatus: (val) => { state.status = val; state.page = 1 },
    setPage: (val) => { state.page = val },
    reset: () => { state.search = defaults.search; state.status = [...defaults.status]; state.page = defaults.page },
  }
  return state
}

describe('Filter Store', () => {
  let store: FilterState

  beforeEach(() => {
    store = createFilterStore()
  })

  it('starts with default values', () => {
    expect(store.search).toBe('')
    expect(store.status).toEqual([])
    expect(store.page).toBe(1)
  })

  it('sets search and resets page', () => {
    store.setPage(3)
    store.setSearch('calibração')
    expect(store.search).toBe('calibração')
    expect(store.page).toBe(1)
  })

  it('sets status filters', () => {
    store.setStatus(['open', 'in_progress'])
    expect(store.status).toEqual(['open', 'in_progress'])
  })

  it('resets to defaults', () => {
    store.setSearch('test')
    store.setStatus(['closed'])
    store.setPage(5)
    store.reset()
    expect(store.search).toBe('')
    expect(store.status).toEqual([])
    expect(store.page).toBe(1)
  })
})

// ── Notification Store ──

interface NotificationState {
  notifications: { id: number; title: string; read: boolean }[]
  unreadCount: number
  addNotification: (n: { id: number; title: string }) => void
  markRead: (id: number) => void
  markAllRead: () => void
  clear: () => void
}

const createNotificationStore = () => {
  const state: NotificationState = {
    notifications: [],
    unreadCount: 0,
    addNotification: (n) => {
      state.notifications.push({ ...n, read: false })
      state.unreadCount++
    },
    markRead: (id) => {
      const n = state.notifications.find(x => x.id === id)
      if (n && !n.read) {
        n.read = true
        state.unreadCount--
      }
    },
    markAllRead: () => {
      state.notifications.forEach(n => { n.read = true })
      state.unreadCount = 0
    },
    clear: () => {
      state.notifications = []
      state.unreadCount = 0
    },
  }
  return state
}

describe('Notification Store', () => {
  let store: NotificationState

  beforeEach(() => {
    store = createNotificationStore()
  })

  it('starts empty', () => {
    expect(store.notifications).toHaveLength(0)
    expect(store.unreadCount).toBe(0)
  })

  it('adds notification', () => {
    store.addNotification({ id: 1, title: 'Nova OS' })
    expect(store.notifications).toHaveLength(1)
    expect(store.unreadCount).toBe(1)
  })

  it('marks notification as read', () => {
    store.addNotification({ id: 1, title: 'Test' })
    store.markRead(1)
    expect(store.unreadCount).toBe(0)
    expect(store.notifications[0].read).toBe(true)
  })

  it('marks all as read', () => {
    store.addNotification({ id: 1, title: 'A' })
    store.addNotification({ id: 2, title: 'B' })
    store.markAllRead()
    expect(store.unreadCount).toBe(0)
  })

  it('clears all notifications', () => {
    store.addNotification({ id: 1, title: 'A' })
    store.clear()
    expect(store.notifications).toHaveLength(0)
  })
})

// ── Theme Store ──

interface ThemeState {
  theme: 'light' | 'dark' | 'system'
  setTheme: (t: ThemeState['theme']) => void
}

const createThemeStore = () => {
  const state: ThemeState = {
    theme: 'system',
    setTheme: (t) => { state.theme = t },
  }
  return state
}

describe('Theme Store', () => {
  let store: ThemeState

  beforeEach(() => {
    store = createThemeStore()
  })

  it('defaults to system theme', () => {
    expect(store.theme).toBe('system')
  })

  it('sets dark theme', () => {
    store.setTheme('dark')
    expect(store.theme).toBe('dark')
  })

  it('sets light theme', () => {
    store.setTheme('light')
    expect(store.theme).toBe('light')
  })
})
