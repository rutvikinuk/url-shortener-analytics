import { useState } from 'react'
import { createLink } from '../api.js'

export default function LinkForm({ onCreated }) {
  const [url, setUrl] = useState('')
  const [slug, setSlug] = useState('')
  const [expires, setExpires] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const payload = { destination_url: url }
      if (slug.trim()) payload.slug = slug.trim()
      if (expires) payload.expires_at = new Date(expires).toISOString()
      const link = await createLink(payload)
      setUrl(''); setSlug(''); setExpires('')
      onCreated(link)
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={submit} className="card form">
      <h2>Shorten a link</h2>
      <label>Destination URL
        <input type="url" required placeholder="https://example.com/long/path"
          value={url} onChange={(e) => setUrl(e.target.value)} />
      </label>
      <div className="row">
        <label>Custom slug (optional)
          <input placeholder="my-link" value={slug} onChange={(e) => setSlug(e.target.value)} />
        </label>
        <label>Expires (optional)
          <input type="datetime-local" value={expires} onChange={(e) => setExpires(e.target.value)} />
        </label>
      </div>
      {error && <p className="error" role="alert">{error}</p>}
      <button disabled={busy}>{busy ? 'Creating…' : 'Create short link'}</button>
    </form>
  )
}
