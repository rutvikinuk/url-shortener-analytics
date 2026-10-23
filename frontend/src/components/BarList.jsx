// Horizontal bars for "top referrers" / "top links". Width is relative to the biggest row.
export default function BarList({ rows, labelKey, emptyText = 'No data yet.' }) {
  if (rows.length === 0) return <p className="muted">{emptyText}</p>
  const max = Math.max(...rows.map((r) => r.clicks))
  return (
    <ul className="bars">
      {rows.map((r) => (
        <li key={r[labelKey]}>
          <span className="bar-label" title={r[labelKey]}>{r[labelKey]}</span>
          <span className="bar-track">
            <span className="bar-fill" style={{ width: `${(r.clicks / max) * 100}%` }} />
          </span>
          <span className="bar-value">{r.clicks}</span>
        </li>
      ))}
    </ul>
  )
}
