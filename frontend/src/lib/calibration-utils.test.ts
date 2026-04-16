import { describe, expect, it } from 'vitest'
import { getCalibrationReadingsPath } from './calibration-utils'

describe('getCalibrationReadingsPath', () => {
    it('monta a rota canonica de leituras de calibracao', () => {
        expect(getCalibrationReadingsPath(15)).toBe('/calibracao/15/leituras')
        expect(getCalibrationReadingsPath('abc')).toBe('/calibracao/abc/leituras')
    })
})
