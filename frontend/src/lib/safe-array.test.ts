import { describe, expect, it } from 'vitest'
import { safeArray, safePaginated } from './safe-array'

describe('safe-array', () => {
  it('safeArray extrai array direto e wrapper simples', () => {
    expect(safeArray<number>([1, 2, 3])).toEqual([1, 2, 3])
    expect(safeArray<number>({ data: [4, 5] })).toEqual([4, 5])
    expect(safeArray<number>({ data: { data: [6] } })).toEqual([])
  })

  it('safePaginated suporta payload paginado simples', () => {
    expect(
      safePaginated<number>({
        data: [1, 2],
        current_page: 2,
        last_page: 5,
        total: 10,
      }),
    ).toEqual({
      items: [1, 2],
      currentPage: 2,
      lastPage: 5,
      total: 10,
    })
  })

  it('safePaginated suporta payload duplamente envelopado', () => {
    expect(
      safePaginated<number>({
        data: {
          data: [7, 8],
          current_page: 3,
          last_page: 4,
          total: 22,
        },
      }),
    ).toEqual({
      items: [7, 8],
      currentPage: 3,
      lastPage: 4,
      total: 22,
    })
  })

  it('safePaginated retorna fallback seguro em shape invalido', () => {
    expect(safePaginated<number>({ data: null })).toEqual({
      items: [],
      currentPage: 1,
      lastPage: 1,
      total: 0,
    })
  })
})
