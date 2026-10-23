export default function LinkTable({ links, selected, onSelect }) {
  if (links.length === 0) return <p className="muted">No links yet — create one above.</p>
  return (
    <ul className="links">
      {links.map((l) => {
        const expired = l.expires_at && new Date(l.expires_at) < new Date()
        return (
          <li key={l.id}>
            <button
              className={`link-row ${selected === l.slug ? 'active' : ''}`}
              onClick={() => onSelect(selected === l.slug ? null : l.slug)}
              aria-pressed={selected === l.slug}
            >
              <strong>/{l.slug}</strong>
              <span className="dest" title={l.destination_url}>{l.destination_url}</span>
              {expired && <span className="badge">expired</span>}
            </button>
          </li>
        )
      })}
    </ul>
  )
}
