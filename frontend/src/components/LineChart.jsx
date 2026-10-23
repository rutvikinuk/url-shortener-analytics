import { useState } from 'react'
import { toPoints } from '../chartMath.js'

const W = 640
const H = 220
const PAD = { top: 12, right: 12, bottom: 28, left: 36 }

export default function LineChart({ series }) {
  const [hover, setHover] = useState(null)
  const innerW = W - PAD.left - PAD.right
  const innerH = H - PAD.top - PAD.bottom
  const { max, points } = toPoints(series, innerW, innerH)

  if (series.length === 0) return <p className="muted">No data yet.</p>

  const line = points.map((p) => `${p.x},${p.y}`).join(' ')
  const area = `0,${innerH} ${line} ${points.at(-1).x},${innerH}`
  const ticks = [0, 0.5, 1].map((f) => Math.round(max * f))
  const labelEvery = Math.max(1, Math.ceil(points.length / 6))
  const active = hover === null ? null : points[hover]

  return (
    <svg
      viewBox={`0 0 ${W} ${H}`}
      role="img"
      aria-label={`Clicks over time, peak axis ${max}`}
      className="chart"
    >
      <g transform={`translate(${PAD.left},${PAD.top})`}>
        {ticks.map((t) => {
          const y = innerH - (t / max) * innerH
          return (
            <g key={t}>
              <line x1="0" x2={innerW} y1={y} y2={y} className="grid" />
              <text x="-8" y={y + 4} textAnchor="end" className="axis">{t}</text>
            </g>
          )
        })}
        <polygon points={area} className="area" />
        <polyline points={line} className="line" />
        {points.map((p, i) => (
          <g key={p.date}>
            {i % labelEvery === 0 && (
              <text x={p.x} y={innerH + 18} textAnchor="middle" className="axis">
                {p.date.slice(5)}
              </text>
            )}
            <circle
              cx={p.x} cy={p.y} r={hover === i ? 5 : 3} className="dot"
              onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}
            />
          </g>
        ))}
        {active && (
          <text x={Math.min(Math.max(active.x, 50), innerW - 50)} y={Math.max(active.y - 10, 10)}
            textAnchor="middle" className="tip">
            {active.date}: {active.clicks}
          </text>
        )}
      </g>
    </svg>
  )
}
