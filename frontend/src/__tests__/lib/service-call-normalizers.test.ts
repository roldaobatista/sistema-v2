import { describe, expect, it } from 'vitest'

import {
  canAcceptServiceCall,
  unwrapServiceCallAssignees,
  unwrapServiceCallAuditLogs,
  unwrapServiceCallPayload,
} from '@/lib/service-call-normalizers'

describe('service-call-normalizers', () => {
  it('desembrulha payload aninhado de chamado', () => {
    const payload = unwrapServiceCallPayload({
      data: {
        data: {
          id: 10,
          call_number: 'CT-00010',
        },
      },
    })

    expect(payload).toEqual({
      id: 10,
      call_number: 'CT-00010',
    })
  })

  it('desembrulha resposta de responsaveis em formato ApiResponse', () => {
    const payload = unwrapServiceCallAssignees({
      data: {
        data: {
          technicians: [{ id: 1, name: 'Tech', email: 'tech@example.com' }],
          drivers: [{ id: 2, name: 'Driver', email: 'driver@example.com' }],
        },
      },
    })

    expect(payload.technicians).toHaveLength(1)
    expect(payload.drivers).toHaveLength(1)
  })

  it('normaliza historico para array vazio quando o shape vier invalido', () => {
    expect(unwrapServiceCallAuditLogs({ data: { data: null } })).toEqual([])
  })

  it('aceita somente chamado pendente de agendamento no fluxo do tecnico', () => {
    expect(canAcceptServiceCall('pending_scheduling')).toBe(true)
    expect(canAcceptServiceCall('scheduled')).toBe(false)
    expect(canAcceptServiceCall('open')).toBe(false)
  })
})
