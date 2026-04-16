import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import WhatsAppConfigPage from '@/pages/configuracoes/WhatsAppConfigPage'

const {
  mockApiGet,
  mockApiPost,
  toastSuccess,
  toastError,
} = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockApiPost: vi.fn(),
  toastSuccess: vi.fn(),
  toastError: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

  return {
    ...actual,
    default: {
      get: mockApiGet,
      post: mockApiPost,
    },
    getApiErrorMessage: (err: unknown, fallback: string) =>
      (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback,
  }
})

vi.mock('sonner', () => ({
  toast: {
    success: toastSuccess,
    error: toastError,
  },
}))

describe('WhatsAppConfigPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockApiGet.mockResolvedValue({
      data: {
        data: {
          provider: 'meta',
          api_url: 'https://graph.facebook.com/v18.0/PHONE_ID',
          instance_name: 'kalibrium',
          phone_number: '5566999999999',
          is_active: true,
        },
      },
    })

    mockApiPost.mockImplementation((url: string) => {
      if (url === '/whatsapp/test') {
        return Promise.resolve({ data: { data: { success: true } } })
      }

      return Promise.resolve({ data: { message: 'Configuração salva' } })
    })
  })

  it('carrega configuração envelopada e usa o contrato real do teste de envio', async () => {
    const user = userEvent.setup()

    render(<WhatsAppConfigPage />)

    await waitFor(() => {
      expect(screen.getByRole('combobox')).toHaveValue('meta')
    })
    expect(screen.getByText(/Provedor: meta/i)).toBeInTheDocument()

    const testPhoneInput = screen.getAllByPlaceholderText('(66) 99235-6105')[1]

    await user.clear(testPhoneInput)
    await user.type(testPhoneInput, '66998887777')
    await user.click(screen.getByRole('button', { name: /Enviar Mensagem de Teste/i }))

    await waitFor(() => {
      expect(screen.getByDisplayValue('(66) 99999-9999')).toBeInTheDocument()
      expect(testPhoneInput).toHaveValue('(66) 99888-7777')
      expect(mockApiPost).toHaveBeenCalledWith('/whatsapp/test', { phone: '5566998887777' })
      expect(toastSuccess).toHaveBeenCalledWith('Mensagem de teste enviada!')
    })
  })
})
