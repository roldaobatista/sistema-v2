import { describe, expect, it } from 'vitest'
import { maskPhone, normalizeBrazilPhone } from '@/lib/form-masks'

describe('form-masks phone helpers', () => {
  it('mascara numero brasileiro com DDD', () => {
    expect(maskPhone('66992356105')).toBe('(66) 99235-6105')
  })

  it('mascara numero com codigo do pais sem tratar 55 como DDI do campo', () => {
    expect(maskPhone('5566992356105')).toBe('(66) 99235-6105')
  })

  it('normaliza numero mascarado para whatsapp com codigo do pais', () => {
    expect(normalizeBrazilPhone('(66) 99235-6105')).toBe('5566992356105')
  })

  it('mantem apenas digitos nacionais quando solicitado', () => {
    expect(normalizeBrazilPhone('+55 (66) 99235-6105', false)).toBe('66992356105')
  })
})
