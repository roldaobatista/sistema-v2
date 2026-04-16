/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_URL?: string
  readonly VITE_APP_ENV?: string
  readonly VITE_ENABLE_QUERY_DEVTOOLS?: string
  readonly VITE_ERROR_REPORTING_URL?: string
  readonly VITE_INMETRO_AUTO_SYNC?: string
  readonly VITE_SENTRY_DSN?: string
  readonly VITE_SENTRY_ENABLED?: string
  readonly VITE_SENTRY_RELEASE?: string
  readonly VITE_SENTRY_REPLAYS_ON_ERROR_SAMPLE_RATE?: string
  readonly VITE_SENTRY_REPLAYS_SESSION_SAMPLE_RATE?: string
  readonly VITE_SENTRY_TRACES_SAMPLE_RATE?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
