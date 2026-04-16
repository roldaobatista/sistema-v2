import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
    PaginationEllipsis,
} from '@/components/ui/pagination'

vi.mock('@/components/ui/button', () => ({
    buttonVariants: ({ variant, size }: { variant: string; size: string }) =>
        `btn-${variant}-${size}`,
    Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) =>
        <button {...props}>{children}</button>,
}))

describe('Pagination', () => {
    it('renders as a nav element with pagination role', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationLink href="#">1</PaginationLink></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByRole('navigation')).toBeInTheDocument()
        expect(screen.getByRole('navigation')).toHaveAttribute('aria-label', 'pagination')
    })

    it('renders page links', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationLink href="#">1</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#">2</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#">3</PaginationLink></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByText('1')).toBeInTheDocument()
        expect(screen.getByText('2')).toBeInTheDocument()
        expect(screen.getByText('3')).toBeInTheDocument()
    })

    it('marks active page with aria-current', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationLink href="#">1</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#" isActive>2</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#">3</PaginationLink></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByText('2').closest('a')).toHaveAttribute('aria-current', 'page')
        expect(screen.getByText('1').closest('a')).not.toHaveAttribute('aria-current')
    })

    it('renders previous page button with correct aria-label', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationPrevious href="#" /></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByLabelText('Go to previous page')).toBeInTheDocument()
        expect(screen.getByText('Previous')).toBeInTheDocument()
    })

    it('renders next page button with correct aria-label', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationNext href="#" /></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByLabelText('Go to next page')).toBeInTheDocument()
        expect(screen.getByText('Next')).toBeInTheDocument()
    })

    it('renders ellipsis for truncated pages', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationLink href="#">1</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationEllipsis /></PaginationItem>
                    <PaginationItem><PaginationLink href="#">10</PaginationLink></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByText('More pages')).toBeInTheDocument()
    })

    it('ellipsis is hidden from screen readers', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationEllipsis /></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        const ellipsis = screen.getByText('More pages').closest('span[aria-hidden]')
        expect(ellipsis).toHaveAttribute('aria-hidden', 'true')
    })

    it('calls onClick when page link is clicked', async () => {
        const user = userEvent.setup()
        const onClick = vi.fn()

        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationLink href="#" onClick={onClick}>1</PaginationLink></PaginationItem>
                </PaginationContent>
            </Pagination>
        )

        await user.click(screen.getByText('1'))
        expect(onClick).toHaveBeenCalled()
    })

    it('renders a complete pagination bar', () => {
        render(
            <Pagination>
                <PaginationContent>
                    <PaginationItem><PaginationPrevious href="#" /></PaginationItem>
                    <PaginationItem><PaginationLink href="#" isActive>1</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#">2</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationLink href="#">3</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationEllipsis /></PaginationItem>
                    <PaginationItem><PaginationLink href="#">10</PaginationLink></PaginationItem>
                    <PaginationItem><PaginationNext href="#" /></PaginationItem>
                </PaginationContent>
            </Pagination>
        )
        expect(screen.getByRole('navigation')).toBeInTheDocument()
        expect(screen.getByText('Previous')).toBeInTheDocument()
        expect(screen.getByText('Next')).toBeInTheDocument()
        expect(screen.getByText('More pages')).toBeInTheDocument()
    })
})
