import { describe, it, expect, vi, beforeEach } from 'vitest'
import { formatCep, formatPhone, fetchAddressByCep } from './cep-utils'

describe('cep-utils', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  describe('formatCep', () => {
    it('should format a valid CEP', () => {
      expect(formatCep('12345678')).toBe('12345-678')
    })

    it('should allow partial CEPs', () => {
      expect(formatCep('1234')).toBe('1234')
      expect(formatCep('12345')).toBe('12345')
      expect(formatCep('123456')).toBe('12345-6')
    })

    it('should strip non-digits', () => {
      expect(formatCep('12.345-678')).toBe('12345-678')
      expect(formatCep('abc12345def')).toBe('12345')
    })
  })

  describe('formatPhone', () => {
    it('should format landline phones (10 digits)', () => {
      expect(formatPhone('1144445555')).toBe('(11) 4444-5555')
    })

    it('should format mobile phones (11 digits)', () => {
      expect(formatPhone('11999998888')).toBe('(11) 99999-8888')
    })

    it('should handle partial input and non-digits', () => {
      expect(formatPhone('1')).toBe('1')
      expect(formatPhone('11')).toBe('11')
      expect(formatPhone('119')).toBe('(11) 9')
      expect(formatPhone('abcd119efg')).toBe('(11) 9')
    })
  })

  describe('fetchAddressByCep', () => {
    it('should return null if cep is invalid length', async () => {
      expect(await fetchAddressByCep('123')).toBeNull()
    })

    it('should return address data on success', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
          logradouro: 'Rua Teste',
          bairro: 'Centro',
          localidade: 'São Bernardo',
          uf: 'SP'
        })
      })

      const result = await fetchAddressByCep('09710000')
      expect(result).toEqual({
        address: 'Rua Teste, Centro',
        city: 'São Bernardo',
        state: 'SP'
      })
    })

    it('should return null on API error (erro: true)', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ erro: true })
      })

      const result = await fetchAddressByCep('99999999')
      expect(result).toBeNull()
    })

    it('should return null if fetch fails', async () => {
      global.fetch = vi.fn().mockRejectedValue(new Error('Network error'))
      const result = await fetchAddressByCep('01001000')
      expect(result).toBeNull()
    })
  })
})
