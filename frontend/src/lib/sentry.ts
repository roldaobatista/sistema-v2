import * as Sentry from '@sentry/react'

const sentryDsn = import.meta.env.VITE_SENTRY_DSN?.trim()

function parseBooleanEnv(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value === '') {
    return fallback
  }

  return value === 'true'
}

function parseNumberEnv(value: string | undefined, fallback: number): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : fallback
}

function shouldIgnoreHandledHttpError(message: string | undefined): boolean {
  if (!message) {
    return false
  }

  return /(401|403|404|422)/.test(message)
}

export function initSentry() {
  const sentryEnabled = parseBooleanEnv(import.meta.env.VITE_SENTRY_ENABLED, Boolean(sentryDsn))

  if (!sentryEnabled || !sentryDsn) {
    return
  }

  Sentry.init({
    enabled: sentryEnabled,
    dsn: sentryDsn,
    environment: import.meta.env.VITE_APP_ENV || (import.meta.env.PROD ? 'production' : 'development'),
    release: import.meta.env.VITE_SENTRY_RELEASE || '1.0.0',
    tracesSampleRate: parseNumberEnv(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE, 0.2),
    tracePropagationTargets: ['localhost', /^\//],
    replaysSessionSampleRate: parseNumberEnv(import.meta.env.VITE_SENTRY_REPLAYS_SESSION_SAMPLE_RATE, 0),
    replaysOnErrorSampleRate: parseNumberEnv(import.meta.env.VITE_SENTRY_REPLAYS_ON_ERROR_SAMPLE_RATE, 1),
    integrations: [
      Sentry.browserTracingIntegration(),
      Sentry.replayIntegration({ maskAllText: true, blockAllMedia: true }),
    ],
    ignoreErrors: [
      'ResizeObserver loop',
      'Non-Error promise rejection',
      /Loading chunk .* failed/,
      /Network Error/,
    ],
    beforeSend(event) {
      const exception = event.exception?.values?.[0]

      if (shouldIgnoreHandledHttpError(exception?.value)) {
        return null
      }

      return event
    },
    beforeSendTransaction(event) {
      if (['/_boost', '/telescope', '/up', '/health'].some((fragment) => event.transaction?.includes(fragment))) {
        return null
      }

      return event
    },
  })
}

export function setSentryUser(user: { id: number; email: string; name: string } | null) {
  if (user) {
    Sentry.setUser({ id: String(user.id), email: user.email, username: user.name })
  } else {
    Sentry.setUser(null)
  }
}

export function captureError(error: unknown, context?: Record<string, unknown>) {
  if (import.meta.env.DEV) {
    console.error('[captureError]', error, context)
  }

  if (!sentryDsn) {
    return
  }

  if (error instanceof Error) {
    Sentry.captureException(error, { extra: context })
  } else {
    Sentry.captureMessage(String(error), { level: 'error', extra: context })
  }
}
