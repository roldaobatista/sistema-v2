/**
 * CEP Lookup Utility — ViaCEP
 * Centralizes ZIP code lookups across the application.
 */

export interface CepResult {
  address: string
  neighborhood: string
  city: string
  state: string
}

export async function fetchAddressByCep(cep: string): Promise<CepResult | null> {
  const cleaned = cep.replace(/\D/g, '')
  if (cleaned.length !== 8) return null

  try {
    const response = await fetch(`https://viacep.com.br/ws/${cleaned}/json/`)
    if (!response.ok) return null

    const data = await response.json()
    if (data.erro) return null

    return {
      address: data.logradouro || '',
      neighborhood: data.bairro || '',
      city: data.localidade || '',
      state: data.uf || '',
    }
  } catch {
    return null
  }
}
