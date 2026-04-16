const EQUIPMENT_STATUS_MAP: Record<string, string> = {
    active: 'active',
    ativo: 'active',
    in_calibration: 'in_calibration',
    em_calibracao: 'in_calibration',
    'em calibração': 'in_calibration',
    'em calibracao': 'in_calibration',
    in_maintenance: 'in_maintenance',
    em_manutencao: 'in_maintenance',
    'em manutenção': 'in_maintenance',
    'em manutencao': 'in_maintenance',
    out_of_service: 'out_of_service',
    fora_de_uso: 'out_of_service',
    'fora de uso': 'out_of_service',
    discarded: 'discarded',
    descartado: 'discarded',
}

function normalizeKey(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, '_')
}

export function normalizeEquipmentStatus(status: string | null | undefined): string | null {
    if (!status) {
        return null
    }

    const normalized = normalizeKey(status)
    return EQUIPMENT_STATUS_MAP[normalized] ?? null
}
