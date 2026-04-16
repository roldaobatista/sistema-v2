import { useState, useMemo } from 'react'

interface ExcentricityPoint {
  corner: number
  label: string
  x: number
  y: number
  valueKg: number | null
}

interface CalibrationExcentricityVisualizerProps {
  readings?: { corner: number; value: number }[]
  nominalLoad?: number
  equipmentClass?: string
  onChange?: (corner: number, value: number) => void
  readOnly?: boolean
}

const CORNERS: Omit<ExcentricityPoint, 'valueKg'>[] = [
  { corner: 1, label: 'Frente-Esq', x: 80, y: 70 },
  { corner: 2, label: 'Frente-Dir', x: 320, y: 70 },
  { corner: 3, label: 'Centro', x: 200, y: 160 },
  { corner: 4, label: 'Trás-Esq', x: 80, y: 250 },
  { corner: 5, label: 'Trás-Dir', x: 320, y: 250 },
]

function getErrorColor(error: number, mpe: number): string {
  const ratio = Math.abs(error) / mpe
  if (ratio <= 0.5) return '#22c55e' // green
  if (ratio <= 0.8) return '#f59e0b' // amber
  return '#ef4444' // red
}

const getMpe = (load: number, equipClass: string = 'III') => {
  const rates: Record<string, number> = { 'I': 0.0001, 'II': 0.00025, 'III': 0.0005, 'IIII': 0.001 }
  return load * (rates[equipClass] ?? rates['III'])
}

export default function CalibrationExcentricityVisualizer({
  readings = [],
  nominalLoad = 100,
  equipmentClass = 'III',
  onChange,
  readOnly = true,
}: CalibrationExcentricityVisualizerProps) {
  const [hoveredCorner, setHoveredCorner] = useState<number | null>(null)

  const mpe = getMpe(nominalLoad, equipmentClass)

  const points = useMemo(() => {
    return (CORNERS || []).map(c => {
      const reading = readings.find(r => r.corner === c.corner)
      return { ...c, valueKg: reading?.value ?? null }
    })
  }, [readings])

  const maxError = useMemo(() => {
    if (readings.length === 0) return 0
    return Math.max(...(readings || []).map(r => Math.abs(r.value - nominalLoad)))
  }, [readings, nominalLoad])

  return (
    <div className="rounded-xl border border-default bg-surface-0 p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-surface-900">
          Teste de Excentricidade
        </h3>
        <div className="flex items-center gap-3 text-[10px]">
          <span className="flex items-center gap-1">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" /> {'≤ 50% MPE'}
          </span>
          <span className="flex items-center gap-1">
            <span className="w-2.5 h-2.5 rounded-full bg-amber-500" /> {'50-80% MPE'}
          </span>
          <span className="flex items-center gap-1">
            <span className="w-2.5 h-2.5 rounded-full bg-red-500" /> {'> 80% MPE'}
          </span>
        </div>
      </div>

      <svg viewBox="0 0 400 320" className="w-full max-w-md mx-auto">
        {/* Platform base */}
        <rect
          x="40" y="40" width="320" height="240"
          rx="12" ry="12"
          fill="none" stroke="currentColor" strokeWidth="1.5"
          className="text-surface-300"
        />
        {/* Platform surface */}
        <rect
          x="50" y="50" width="300" height="220"
          rx="8" ry="8"
          fill="currentColor" fillOpacity="0.03"
          className="text-brand-500"
        />
        {/* Grid lines */}
        <line x1="200" y1="50" x2="200" y2="270" stroke="currentColor" strokeWidth="0.5" strokeDasharray="4 4" className="text-surface-200" />
        <line x1="50" y1="160" x2="350" y2="160" stroke="currentColor" strokeWidth="0.5" strokeDasharray="4 4" className="text-surface-200" />

        {/* Points */}
        {(points || []).map(point => {
          const error = point.valueKg !== null ? point.valueKg - nominalLoad : 0
          const color = point.valueKg !== null ? getErrorColor(error, mpe) : '#94a3b8'
          const isHovered = hoveredCorner === point.corner
          const radius = isHovered ? 24 : 20

          return (
            <g
              key={point.corner}
              onMouseEnter={() => setHoveredCorner(point.corner)}
              onMouseLeave={() => setHoveredCorner(null)}
              className="cursor-pointer"
            >
              {/* Outer ring */}
              <circle
                cx={point.x} cy={point.y} r={radius + 4}
                fill={color} fillOpacity={0.15}
                stroke={color} strokeWidth={isHovered ? 2 : 0}
              />
              {/* Main circle */}
              <circle
                cx={point.x} cy={point.y} r={radius}
                fill={color}
                stroke="white" strokeWidth="2"
              />
              {/* Corner number */}
              <text
                x={point.x} y={point.y - 4}
                textAnchor="middle" dominantBaseline="middle"
                fill="white" fontSize="14" fontWeight="bold"
              >
                {point.corner}
              </text>
              {/* Value */}
              <text
                x={point.x} y={point.y + 10}
                textAnchor="middle" dominantBaseline="middle"
                fill="white" fontSize="9" fontWeight="600"
              >
                {point.valueKg !== null ? `${point.valueKg.toFixed(3)}` : '—'}
              </text>
              {/* Label below */}
              <text
                x={point.x} y={point.y + radius + 16}
                textAnchor="middle" dominantBaseline="middle"
                fill="currentColor" fontSize="10"
                className="text-surface-500"
              >
                {point.label}
              </text>
            </g>
          )
        })}

        {/* Nominal reference */}
        <text x="200" y="306" textAnchor="middle" fill="currentColor" fontSize="10" className="text-surface-400">
          Carga Nominal: {nominalLoad} kg | MPE: ±{mpe.toFixed(3)} kg | Erro Máx: {maxError.toFixed(4)} kg
        </text>
      </svg>

      {/* Detail tooltip if hovered */}
      {hoveredCorner !== null && (() => {
        const p = points.find(pt => pt.corner === hoveredCorner)
        if (!p || p.valueKg === null) return null
        const error = p.valueKg - nominalLoad
        return (
          <div className="mt-2 p-2 rounded-lg bg-surface-50 border border-default text-xs">
            <span className="font-semibold">Ponto {p.corner} ({p.label}): </span>
            <span>{p.valueKg.toFixed(4)} kg</span>
            <span className="mx-2">|</span>
            <span>Erro: {error >= 0 ? '+' : ''}{error.toFixed(4)} kg</span>
            <span className="mx-2">|</span>
            <span>MPE Utilizado: {((Math.abs(error) / mpe) * 100).toFixed(1)}%</span>
          </div>
        )
      })()}
    </div>
  )
}
