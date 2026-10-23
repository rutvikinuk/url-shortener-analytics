// Thin wrapper around fetch for the Laravel JSON API (proxied at /api by Vite).
async function request(path, options = {}) {
  const res = await fetch(`/api${path}`, {
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    ...options,
  })
  const body = await res.json().catch(() => null)
  if (!res.ok) {
    // Laravel validation errors look like { message, errors: { field: [msg] } }
    const first = body?.errors && Object.values(body.errors)[0]?.[0]
    throw new Error(first || body?.message || `Request failed (${res.status})`)
  }
  return body
}

export const listLinks = () => request('/links')
export const createLink = (payload) =>
  request('/links', { method: 'POST', body: JSON.stringify(payload) })
export const getOverview = ({ days, interval }) =>
  request(`/analytics?days=${days}&interval=${interval}`)
export const getLinkAnalytics = (slug, { days, interval }) =>
  request(`/links/${encodeURIComponent(slug)}/analytics?days=${days}&interval=${interval}`)
export const qrUrl = (slug) => `/api/links/${encodeURIComponent(slug)}/qr`
