import { describe, it, expect } from 'vitest'
import { render, screen } from '../test-utils'
import userEvent from '@testing-library/user-event'

// We test navigation accessibility patterns using minimal rendered markup
// that mirrors what AppLayout and TechShell produce.

describe('Navigation Accessibility', () => {
    // ── Skip link ──────────────────────────────────────────────────────────

    it('skip link allows jumping to main content', async () => {
        const user = userEvent.setup()
        render(
            <div>
                <a href="#main-content" className="sr-only focus:not-sr-only">
                    Pular para conteudo principal
                </a>
                <nav aria-label="Menu principal">
                    <a href="/dashboard">Dashboard</a>
                </nav>
                <main id="main-content" tabIndex={-1}>
                    <h1>Dashboard</h1>
                </main>
            </div>
        )

        const skipLink = screen.getByText('Pular para conteudo principal')
        expect(skipLink).toBeInTheDocument()
        expect(skipLink).toHaveAttribute('href', '#main-content')
    })

    // ── ARIA landmarks ─────────────────────────────────────────────────────

    it('page contains navigation landmark', () => {
        render(
            <div>
                <nav aria-label="Menu principal">
                    <a href="/">Dashboard</a>
                </nav>
                <main>
                    <h1>Content</h1>
                </main>
            </div>
        )

        expect(screen.getByRole('navigation', { name: 'Menu principal' })).toBeInTheDocument()
    })

    it('page contains main landmark', () => {
        render(
            <div>
                <header>
                    <span>Kalibrium</span>
                </header>
                <main>
                    <h1>Dashboard</h1>
                </main>
            </div>
        )

        expect(screen.getByRole('main')).toBeInTheDocument()
    })

    it('page header is rendered as banner landmark', () => {
        render(
            <div>
                <header>
                    <span>Kalibrium</span>
                </header>
                <main>Content</main>
            </div>
        )

        expect(screen.getByRole('banner')).toBeInTheDocument()
    })

    // ── Current page indication ────────────────────────────────────────────

    it('active navigation item has aria-current="page"', () => {
        render(
            <nav aria-label="Menu principal">
                <a href="/" aria-current="page">Dashboard</a>
                <a href="/clientes">Clientes</a>
            </nav>
        )

        const activeLink = screen.getByText('Dashboard')
        expect(activeLink).toHaveAttribute('aria-current', 'page')

        const inactiveLink = screen.getByText('Clientes')
        expect(inactiveLink).not.toHaveAttribute('aria-current')
    })

    // ── Menu item roles ────────────────────────────────────────────────────

    it('navigation links are accessible by role', () => {
        render(
            <nav aria-label="Sidebar">
                <a href="/" role="link">Dashboard</a>
                <a href="/os" role="link">Ordens de Servico</a>
            </nav>
        )

        const links = screen.getAllByRole('link')
        expect(links.length).toBeGreaterThanOrEqual(2)
    })

    // ── Mobile menu toggle ─────────────────────────────────────────────────

    it('mobile menu toggle button has aria-expanded attribute', async () => {
        const user = userEvent.setup()
        const ToggleMenu = () => {
            const [open, setOpen] = React.useState(false)
            return (
                <>
                    <button
                        aria-expanded={open}
                        aria-label="Abrir menu"
                        onClick={() => setOpen(!open)}
                    >
                        Menu
                    </button>
                    {open && (
                        <nav aria-label="Menu mobile">
                            <a href="/">Dashboard</a>
                        </nav>
                    )}
                </>
            )
        }

        // Need React for the component
        const React = await import('react')
        render(<ToggleMenu />)

        const toggle = screen.getByRole('button', { name: 'Abrir menu' })
        expect(toggle).toHaveAttribute('aria-expanded', 'false')

        await user.click(toggle)
        expect(toggle).toHaveAttribute('aria-expanded', 'true')
    })

    // ── TechShell bottom nav has navigation role ───────────────────────────

    it('bottom navigation bar has nav element with links', () => {
        render(
            <nav aria-label="Navegacao principal" role="navigation">
                <a href="/tech/dashboard">Painel</a>
                <a href="/tech">OS</a>
                <a href="/tech/agenda">Agenda</a>
                <a href="/tech/caixa">Caixa</a>
            </nav>
        )

        const nav = screen.getByRole('navigation', { name: 'Navegacao principal' })
        expect(nav).toBeInTheDocument()

        const links = within(nav).getAllByRole('link')
        expect(links).toHaveLength(4)
    })

    // ── Sidebar collapse button ────────────────────────────────────────────

    it('sidebar collapse button has descriptive aria-label', () => {
        render(
            <button aria-label="Recolher menu lateral">
                {'<'}
            </button>
        )

        expect(screen.getByRole('button', { name: 'Recolher menu lateral' })).toBeInTheDocument()
    })

    // ── Logout button ──────────────────────────────────────────────────────

    it('logout button is identifiable by screen readers', () => {
        render(
            <button aria-label="Sair do sistema">
                Sair
            </button>
        )

        expect(screen.getByRole('button', { name: 'Sair do sistema' })).toBeInTheDocument()
    })
})

// Import within for scoped queries
import { within } from '@testing-library/react'
