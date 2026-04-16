import { lazy, Suspense } from 'react'

const ReactQueryDevtools = lazy(() =>
  import('@tanstack/react-query-devtools').then((module) => ({
    default: module.ReactQueryDevtools,
  })),
)

const queryDevtoolsEnabled = import.meta.env.DEV || import.meta.env.VITE_ENABLE_QUERY_DEVTOOLS === 'true'

export function QueryDevtoolsLoader() {
  if (!queryDevtoolsEnabled) {
    return null
  }

  return (
    <Suspense fallback={null}>
      <ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-left" />
    </Suspense>
  )
}
