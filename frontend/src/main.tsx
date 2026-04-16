import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import * as Sentry from '@sentry/react'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { initSentry } from '@/lib/sentry'
import { toast } from 'sonner'
import './index.css'
import App from './App'
import { initSyncEngine } from '@/lib/offline/syncEngine'

initSentry()
initSyncEngine()

// Aplica tema de forma síncrona ao carregar (evita mistura claro+escuro quando index.html está em cache)
function applyThemeSync() {
  try {
    const raw = localStorage.getItem('ui-store')
    let theme: 'light' | 'dark' | 'system' = 'light'
    if (raw) {
      try {
        const o = JSON.parse(raw) as { theme?: string }
        if (o?.theme === 'dark' || o?.theme === 'light' || o?.theme === 'system') theme = o.theme
      } catch { /* ignore */ }
    }
    const isDark = theme === 'dark' || (theme === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches)
    const root = document.documentElement
    root.classList.remove('dark', 'light')
    root.classList.add(isDark ? 'dark' : 'light')
    const meta = document.querySelector('meta[name="theme-color"]')
    if (meta) meta.setAttribute('content', isDark ? '#09090B' : '#2563EB')
  } catch { /* ignore */ }
}
applyThemeSync()

// go2rtc URL for camera streaming (injected at build time or fallback to /go2rtc)
;(window as Window & { __GO2RTC_URL?: string }).__GO2RTC_URL =
  import.meta.env.VITE_GO2RTC_URL || (window.location.origin + '/go2rtc')

// Escuta eventos de 403 (permissão negada) disparados pelo interceptor da API
window.addEventListener('api:forbidden', ((e: CustomEvent<{ message: string }>) => {
  toast.error(e.detail.message || 'Você não tem permissão para realizar esta ação.')
}) as EventListener)

// ─── Service Worker (PWA Offline) ─────────────────────────────────
// Registro e gerenciamento de updates ficam no hook usePWA.ts (que escuta controllerchange corretamente).
// Aqui apenas escutamos eventos de sync do SW para mostrar toasts globais.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data?.type === 'SYNC_COMPLETE') {
      if (event.data.remaining === 0) {
        toast.success('Dados offline sincronizados com sucesso!')
      }
    }
    // Provide auth token to SW for offline queue replay
    if (event.data?.type === 'GET_AUTH_TOKEN' && event.ports?.[0]) {
      const token = localStorage.getItem('auth_token')
      event.ports[0].postMessage({ token })
    }
  })
}

function ErrorFallback() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <div className="mx-auto max-w-md rounded-2xl border border-red-200 bg-red-50/80 p-8 text-center shadow-lg">
        <h2 className="text-xl font-semibold text-red-800">Algo deu errado</h2>
        <p className="mt-2 text-sm text-red-700">Ocorreu um erro inesperado. Tente recarregar a página.</p>
        <button
          type="button"
          onClick={() => window.location.reload()}
          className="mt-6 rounded-lg bg-red-600 px-6 py-2.5 text-sm font-medium text-white"
        >
          Recarregar página
        </button>
      </div>
    </div>
  )
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <Sentry.ErrorBoundary fallback={<ErrorFallback />}>
      <ErrorBoundary>
        <App />
      </ErrorBoundary>
    </Sentry.ErrorBoundary>
  </StrictMode>,
)
