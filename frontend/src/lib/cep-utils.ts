interface ViaCepResponse {
  cep: string
  logradouro: string
  complemento: string
  bairro: string
  localidade: string
  uf: string
  erro?: boolean
}

export interface AddressResult {
  address: string
  city: string
  state: string
}

export async function fetchAddressByCep(cep: string): Promise<AddressResult | null> {
  const cleanCep = cep.replace(/\D/g, '')
  if (cleanCep.length !== 8) return null

  try {
    const response = await fetch(`https://viacep.com.br/ws/${cleanCep}/json/`)
    if (!response.ok) return null

    const data: ViaCepResponse = await response.json()
    if (data.erro) return null

    const parts = [data.logradouro, data.bairro].filter(Boolean)

    return {
      address: parts.join(', '),
      city: data.localidade,
      state: data.uf,
    }
  } catch {
    return null
  }
}

export function formatCep(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 5) return digits
  return `${digits.slice(0, 5)}-${digits.slice(5)}`
}

export function formatPhone(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 2) return digits
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}
