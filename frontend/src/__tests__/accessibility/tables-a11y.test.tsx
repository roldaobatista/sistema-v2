import { describe, it, expect, beforeAll } from 'vitest'
import { render, screen } from '../test-utils'

// Mock ResizeObserver for Table component
beforeAll(() => {
    if (typeof globalThis.ResizeObserver === 'undefined') {
        globalThis.ResizeObserver = class {
            observe() {}
            unobserve() {}
            disconnect() {}
        } as any
    }
})
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
    TableCaption,
} from '@/components/ui/table'
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'

describe('Tables Accessibility', () => {
    // ── Table structure ────────────────────────────────────────────────────

    it('Table renders with proper HTML table structure', () => {
        render(
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nome</TableHead>
                        <TableHead>Email</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>Joao</TableCell>
                        <TableCell>joao@test.com</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        expect(screen.getByRole('table')).toBeInTheDocument()
        const headers = screen.getAllByRole('columnheader')
        expect(headers).toHaveLength(2)
        expect(headers[0]).toHaveTextContent('Nome')
        expect(headers[1]).toHaveTextContent('Email')
    })

    it('Table header cells use th elements', () => {
        render(
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>ID</TableHead>
                        <TableHead>Cliente</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>1</TableCell>
                        <TableCell>Empresa X</TableCell>
                        <TableCell>Ativo</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        const headerCells = screen.getAllByRole('columnheader')
        expect(headerCells).toHaveLength(3)
        headerCells.forEach(cell => {
            expect(cell.tagName).toBe('TH')
        })
    })

    // ── Sortable columns ───────────────────────────────────────────────────

    it('sortable column has aria-sort attribute', () => {
        render(
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead aria-sort="ascending">Nome</TableHead>
                        <TableHead aria-sort="none">Email</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>Ana</TableCell>
                        <TableCell>ana@test.com</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        const headers = screen.getAllByRole('columnheader')
        expect(headers[0]).toHaveAttribute('aria-sort', 'ascending')
        expect(headers[1]).toHaveAttribute('aria-sort', 'none')
    })

    // ── Row selection ──────────────────────────────────────────────────────

    it('selected row has data-state="selected"', () => {
        render(
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nome</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow data-state="selected" aria-selected="true">
                        <TableCell>Item selecionado</TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>Item normal</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        const rows = screen.getAllByRole('row')
        // First row is header, second is selected, third is normal
        expect(rows[1]).toHaveAttribute('aria-selected', 'true')
        expect(rows[2]).not.toHaveAttribute('aria-selected')
    })

    // ── Table caption ──────────────────────────────────────────────────────

    it('Table with caption provides accessible name', () => {
        render(
            <Table>
                <TableCaption>Lista de clientes ativos</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nome</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>Cliente 1</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        expect(screen.getByText('Lista de clientes ativos')).toBeInTheDocument()
        const table = screen.getByRole('table')
        const caption = table.querySelector('caption')
        expect(caption).toBeInTheDocument()
    })

    it('Table with aria-label provides accessible name', () => {
        render(
            <Table aria-label="Ordens de servico">
                <TableHeader>
                    <TableRow>
                        <TableHead>Numero</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>OS-001</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        )

        expect(screen.getByRole('table', { name: 'Ordens de servico' })).toBeInTheDocument()
    })

    // ── Pagination ─────────────────────────────────────────────────────────

    it('Pagination has navigation role with aria-label', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious href="#" />
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationLink href="#" isActive>1</PaginationLink>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationLink href="#">2</PaginationLink>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationNext href="#" />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        )

        const nav = screen.getByRole('navigation', { name: 'pagination' })
        expect(nav).toBeInTheDocument()
    })

    it('active pagination page has aria-current="page"', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem>
                        <PaginationLink href="#" isActive>1</PaginationLink>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationLink href="#">2</PaginationLink>
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        )

        const activeLink = screen.getByText('1').closest('a')
        expect(activeLink).toHaveAttribute('aria-current', 'page')

        const inactiveLink = screen.getByText('2').closest('a')
        expect(inactiveLink).not.toHaveAttribute('aria-current')
    })
})
