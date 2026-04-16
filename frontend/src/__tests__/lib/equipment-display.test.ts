import { describe, expect, it } from 'vitest'
import {
    buildEquipmentDisplayName,
    formatMeasurementValue,
    formatMeasurementWithUnit,
    getMeasurementPrecision,
    normalizeMeasurementInput,
} from '@/lib/equipment-display'

describe('equipment-display utils', () => {
    it('detecta a precisao pela divisao informada', () => {
        expect(getMeasurementPrecision('0.2')).toBe(1)
        expect(getMeasurementPrecision('0,0050')).toBe(3)
        expect(getMeasurementPrecision('10')).toBe(0)
    })

    it('normaliza entradas metrologicas removendo zeros desnecessarios', () => {
        expect(normalizeMeasurementInput('300.0000')).toBe('300')
        expect(normalizeMeasurementInput('0,200')).toBe('0.2')
    })

    it('formata capacidade seguindo as casas da divisao', () => {
        expect(formatMeasurementValue('300', '0.2')).toBe('300,0')
        expect(formatMeasurementValue('300', '1')).toBe('300')
        expect(formatMeasurementWithUnit('300', 'kg', '0.005')).toBe('300,000 kg')
    })

    it('monta a identificacao completa do equipamento sem null', () => {
        expect(buildEquipmentDisplayName({
            manufacturer: 'Toledo',
            model: '9094',
            serial_number: 'SN-123',
            capacity: '300',
            capacity_unit: 'kg',
            resolution: '0.2',
        })).toBe('Toledo - 9094 - SN-123 - 300,0 kg')
    })
})
