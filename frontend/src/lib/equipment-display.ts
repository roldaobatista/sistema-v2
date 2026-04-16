function toCleanString(value: number | string | null | undefined): string {
    if (value === null || value === undefined) return ''
    return String(value).trim()
}

function normalizeDecimalSeparator(value: string): string {
    return value.replace(/\s+/g, '').replace(',', '.')
}

export function getMeasurementPrecision(value: number | string | null | undefined): number {
    const normalized = normalizeDecimalSeparator(toCleanString(value))
    if (!normalized || !normalized.includes('.')) return 0

    const decimals = normalized.split('.')[1]?.replace(/0+$/, '') ?? ''
    return decimals.length
}

export function normalizeMeasurementInput(value: number | string | null | undefined): string {
    const normalized = normalizeDecimalSeparator(toCleanString(value))
    if (!normalized) return ''

    const numeric = Number(normalized)
    if (!Number.isFinite(numeric)) return toCleanString(value)

    return numeric.toString()
}

export function formatMeasurementValue(
    value: number | string | null | undefined,
    referencePrecision?: number | string | null | undefined
): string {
    const raw = normalizeDecimalSeparator(toCleanString(value))
    if (!raw) return ''

    const numeric = Number(raw)
    if (!Number.isFinite(numeric)) return toCleanString(value)

    const precision = getMeasurementPrecision(referencePrecision)
    return numeric.toLocaleString('pt-BR', {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    })
}

export function formatMeasurementWithUnit(
    value: number | string | null | undefined,
    unit?: string | null,
    referencePrecision?: number | string | null | undefined
): string {
    const formattedValue = formatMeasurementValue(value, referencePrecision)
    if (!formattedValue) return ''
    return unit ? `${formattedValue} ${unit}` : formattedValue
}

type EquipmentLabelInput = {
    manufacturer?: string | null
    brand?: string | null
    model?: string | null
    serial_number?: string | null
    capacity?: number | string | null
    capacity_unit?: string | null
    resolution?: number | string | null
}

export function buildEquipmentDisplayName(equipment: EquipmentLabelInput, fallbackId?: number): string {
    const manufacturer = equipment.manufacturer?.trim() || equipment.brand?.trim() || ''
    const model = equipment.model?.trim() || ''
    const serialNumber = equipment.serial_number?.trim() || ''
    const capacity = formatMeasurementWithUnit(equipment.capacity, equipment.capacity_unit, equipment.resolution)

    const parts = [manufacturer, model, serialNumber, capacity].filter(Boolean)
    if (parts.length > 0) {
        return parts.join(' - ')
    }

    return fallbackId ? `Equip #${fallbackId}` : 'Equipamento'
}
