function getBrazilNationalPhoneDigits(value: string): string {
  let digits = value.replace(/\D/g, '')

  if (digits.startsWith('00')) {
    digits = digits.slice(2)
  }

  if (digits.startsWith('55') && digits.length >= 12) {
    digits = digits.slice(2)
  }

  while (digits.length > 11 && digits.startsWith('0')) {
    digits = digits.slice(1)
  }

  return digits.slice(0, 11)
}

export function maskCpfCnpj(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (digits.length <= 11) {
    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  }
  return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
}

export function maskPhone(value: string): string {
  const digits = getBrazilNationalPhoneDigits(value)

  if (digits.length === 0) {
    return ''
  }

  if (digits.length <= 2) {
    return `(${digits}`
  }

  if (digits.length <= 6) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  }

  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

export function normalizeBrazilPhone(value: string, includeCountryCode = true): string {
  const digits = getBrazilNationalPhoneDigits(value)

  if (digits.length < 10) {
    return digits
  }

  return includeCountryCode ? `55${digits}` : digits
}

export function maskCep(value: string): string {
  return value.replace(/\D/g, '').replace(/(\d{5})(\d{3})/, '$1-$2')
}
