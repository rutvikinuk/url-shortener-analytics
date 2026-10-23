import { useCallback, useEffect, useState } from 'react'
import { getLinkAnalytics, getOverview, listLinks, qrUrl } from './api.js'
import BarList from './components/BarList.jsx'
import LineChart from './components/LineChart.jsx'
import LinkForm from './components/LinkForm.jsx'
import LinkTable from './components/LinkTable.jsx'
import './App.css'

export default function App() {
  const [links, setLinks] = useState([])
  const [slug, setSlug] = useState(null) // null = all links
  const [days, setDays] = useState(30)
  const [interval, setInterval] = useState('day')
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)

  const refreshLinks = useCallback(
    () => listLinks().then((page) => setLinks(page.data)).catch((e) => setError(e.message)),
    [],
  )
  useEffect(() => { refreshLinks() }, [refreshLinks])

  // Reload analytics whenever the selected link or the filters change.
  useEffect(() => {
    let stale = false // ignore a slow response if the user already changed filters
    const opts = { days, interval }
    const load = slug ? getLinkAnalytics(slug, opts) : getOverview(opts)
    load
      .then((d) => { if (!stale) { setData(d); setError(null) } })
      .catch((e) => { if (!stale) setError(e.message) })
    return () => { stale = true }
  }, [slug, days, interval])

  return (
    <main>
      <header>
        <h1>Link analytics</h1>
        <p className="muted">
          {slug ? <>Showing <strong>/{slug}</strong></> : 'Showing all links'}
        </p>
      </header>

      {error && <p className="error" role="alert">{error}</p>}

      <LinkForm onCreated={(l) => { refreshLinks(); setSlug(l.slug) }} />

      <section className="card">
        <h2>Your links</h2>
        <LinkTable links={links} selected={slug} onSelect={setSlug} />
        {slug && (
          <figure className="qr">
            <img src={qrUrl(slug)} alt={`QR code for /${slug}`} width="140" height="140" />
            <figcaption className="muted">Scan to open /{slug}</figcaption>
          </figure>
        )}
      </section>

      <section className="card">
        <div className="toolbar">
          <h2>Clicks</h2>
          <select aria-label="Date range" value={days} onChange={(e) => setDays(+e.target.value)}>
            <option value={7}>Last 7 days</option>
            <option value={30}>Last 30 days</option>
            <option value={90}>Last 90 days</option>
          </select>
          <select aria-label="Group by" value={interval} onChange={(e) => setInterval(e.target.value)}>
            <option value="day">By day</option>
            <option value="week">By week</option>
          </select>
        </div>
        {data ? (
          <>
            <div className="stats">
              <div><span className="stat">{data.total_clicks}</span><span className="muted">total clicks</span></div>
              <div><span className="stat">{data.unique_visitors}</span><span className="muted">unique visitors</span></div>
            </div>
            <LineChart series={data.series} />
            <div className="two-col">
              <div><h3>Top referrers</h3><BarList rows={data.top_referrers} labelKey="referrer" /></div>
              {!slug && (
                <div><h3>Top links</h3>
                  <BarList rows={(data.top_links ?? []).map((l) => ({ ...l, label: `/${l.slug}` }))} labelKey="label" />
                </div>
              )}
            </div>
          </>
        ) : <p className="muted">Loading…</p>}
      </section>
    </main>
  )
}
