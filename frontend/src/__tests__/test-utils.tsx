import type { ReactElement } from 'react'
import { render, type RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'

/**
 * QueryClient para testes: sem retry e gcTime zero para evitar vazamento entre testes.
 */
function createTestQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false, gcTime: 0 },
            mutations: { retry: false },
        },
    })
}

interface CustomRenderOptions extends Omit<RenderOptions, 'wrapper'> {
    queryClient?: QueryClient
    route?: string
}

function AllProviders({
    children,
    queryClient,
}: {
    children: React.ReactNode
    queryClient: QueryClient
}) {
    return (
        <QueryClientProvider client={queryClient}>
            <TooltipProvider delayDuration={0}>
                <BrowserRouter>{children}</BrowserRouter>
            </TooltipProvider>
        </QueryClientProvider>
    )
}

/**
 * Render com providers (QueryClient + BrowserRouter).
 * Use em testes de componentes/páginas que dependem de rotas ou react-query.
 */
function customRender(ui: ReactElement, options: CustomRenderOptions = {}) {
    const { queryClient = createTestQueryClient(), route, ...renderOptions } = options

    if (route) {
        window.history.pushState({}, 'Test page', route)
    }

    return {
        ...render(ui, {
            wrapper: ({ children }) => (
                <AllProviders queryClient={queryClient}>{children}</AllProviders>
            ),
            ...renderOptions,
        }),
        queryClient,
    }
}

export * from '@testing-library/react'
export { customRender as render, createTestQueryClient }
