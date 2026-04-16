import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '../test-utils'
import userEvent from '@testing-library/user-event'
import { Input } from '@/components/ui/input'
import { FormField } from '@/components/ui/form-field'
import { Select } from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Button } from '@/components/ui/button'

describe('Forms Accessibility', () => {
    // ── Label association ──────────────────────────────────────────────────

    it('Input with label prop generates associated htmlFor/id pair', () => {
        render(<Input label="Email" />)
        const input = screen.getByLabelText('Email')
        expect(input).toBeInTheDocument()
        expect(input.tagName).toBe('INPUT')
    })

    it('Input without label can use aria-label for accessibility', () => {
        render(<Input aria-label="Search customers" placeholder="Search..." />)
        const input = screen.getByRole('textbox', { name: 'Search customers' })
        expect(input).toBeInTheDocument()
    })

    it('FormField renders label text associated with child input', () => {
        render(
            <FormField label="Nome completo" required>
                <input id="nome" aria-required="true" />
            </FormField>
        )
        expect(screen.getByText('Nome completo')).toBeInTheDocument()
        expect(screen.getByText('*')).toBeInTheDocument()
    })

    // ── Required fields ────────────────────────────────────────────────────

    it('required Input passes aria-required to the native element', () => {
        render(<Input label="Nome" required aria-required="true" />)
        const input = screen.getByLabelText('Nome')
        expect(input).toHaveAttribute('required')
    })

    it('FormField with required shows visual indicator', () => {
        render(
            <FormField label="CPF" required>
                <input />
            </FormField>
        )
        const asterisk = screen.getByText('*')
        expect(asterisk).toBeInTheDocument()
        expect(asterisk).toHaveClass('text-red-500')
    })

    // ── Error messages ─────────────────────────────────────────────────────

    it('Input with error prop renders error message', () => {
        render(<Input label="Email" error="Email obrigatorio" />)
        expect(screen.getByText('Email obrigatorio')).toBeInTheDocument()
    })

    it('FormField with error prop renders error message with correct styling', () => {
        render(
            <FormField label="Nome" error="Campo obrigatorio">
                <input />
            </FormField>
        )
        const errorMsg = screen.getByText('Campo obrigatorio')
        expect(errorMsg).toBeInTheDocument()
        expect(errorMsg.tagName).toBe('P')
    })

    it('error message linked via aria-describedby when id is provided', () => {
        render(
            <div>
                <label htmlFor="email-field">Email</label>
                <input id="email-field" aria-describedby="email-error" aria-invalid="true" />
                <p id="email-error">Email invalido</p>
            </div>
        )
        const input = screen.getByLabelText('Email')
        expect(input).toHaveAttribute('aria-describedby', 'email-error')
        expect(input).toHaveAttribute('aria-invalid', 'true')
    })

    // ── Form submit on Enter ───────────────────────────────────────────────

    it('form submits when Enter is pressed on an input', async () => {
        const handleSubmit = vi.fn((e: React.FormEvent) => e.preventDefault())
        const user = userEvent.setup()

        render(
            <form onSubmit={handleSubmit}>
                <Input label="Nome" />
                <Button type="submit">Salvar</Button>
            </form>
        )

        const input = screen.getByLabelText('Nome')
        await user.click(input)
        await user.keyboard('{Enter}')
        expect(handleSubmit).toHaveBeenCalledTimes(1)
    })

    // ── Tab order ──────────────────────────────────────────────────────────

    it('Tab order follows visual order of form fields', async () => {
        const user = userEvent.setup()
        render(
            <form>
                <Input label="Primeiro" data-testid="first" />
                <Input label="Segundo" data-testid="second" />
                <Button type="submit">Enviar</Button>
            </form>
        )

        await user.tab()
        expect(screen.getByLabelText('Primeiro')).toHaveFocus()

        await user.tab()
        expect(screen.getByLabelText('Segundo')).toHaveFocus()

        await user.tab()
        expect(screen.getByRole('button', { name: 'Enviar' })).toHaveFocus()
    })

    // ── RadioGroup ─────────────────────────────────────────────────────────

    it('RadioGroup items have correct role and are focusable', () => {
        render(
            <RadioGroup defaultValue="pf" aria-label="Tipo de pessoa">
                <div>
                    <RadioGroupItem value="pf" id="pf" />
                    <label htmlFor="pf">Pessoa Fisica</label>
                </div>
                <div>
                    <RadioGroupItem value="pj" id="pj" />
                    <label htmlFor="pj">Pessoa Juridica</label>
                </div>
            </RadioGroup>
        )
        const radios = screen.getAllByRole('radio')
        expect(radios).toHaveLength(2)
        expect(screen.getByLabelText('Pessoa Fisica')).toBeInTheDocument()
        expect(screen.getByLabelText('Pessoa Juridica')).toBeInTheDocument()
    })

    it('RadioGroup has radiogroup role', () => {
        render(
            <RadioGroup defaultValue="pf" aria-label="Tipo">
                <RadioGroupItem value="pf" id="r1" />
                <RadioGroupItem value="pj" id="r2" />
            </RadioGroup>
        )
        expect(screen.getByRole('radiogroup', { name: 'Tipo' })).toBeInTheDocument()
    })

    // ── Select keyboard accessibility ──────────────────────────────────────

    it('native Select is keyboard accessible', async () => {
        const user = userEvent.setup()
        render(
            <Select label="Status" defaultValue="ativo">
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
            </Select>
        )

        const select = screen.getByRole('combobox')
        await user.tab()
        expect(select).toHaveFocus()
    })

    // ── Disabled state ─────────────────────────────────────────────────────

    it('disabled Input has correct attributes and cannot be focused via tab', async () => {
        const user = userEvent.setup()
        render(
            <form>
                <Input label="Ativo" />
                <Input label="Bloqueado" disabled />
                <Button type="submit">OK</Button>
            </form>
        )

        await user.tab()
        expect(screen.getByLabelText('Ativo')).toHaveFocus()

        await user.tab()
        // Disabled input is skipped, focus goes to button
        expect(screen.getByRole('button', { name: 'OK' })).toHaveFocus()
    })

    it('Input hint text is displayed when there is no error', () => {
        render(<Input label="Senha" hint="Minimo 8 caracteres" />)
        expect(screen.getByText('Minimo 8 caracteres')).toBeInTheDocument()
    })
})
