import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createLink, getLinkAnalytics, getOverview, listLinks, qrUrl, setToken } from './api.js'

function respond(status, body) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => (body === undefined ? Promise.reject(new Error('no body')) : Promise.resolve(body)),
  })
}

beforeEach(() => { vi.stubGlobal('fetch', vi.fn()) })
afterEach(() => { vi.unstubAllGlobals() })

describe('api', () => {
  it('calls the proxied /api path with JSON headers', async () => {
    fetch.mockReturnValue(respond(200, { data: [] }))
    await expect(listLinks()).resolves.toEqual({ data: [] })
    const [url, opts] = fetch.mock.calls[0]
    expect(url).toBe('/api/links')
    expect(opts.headers.Accept).toBe('application/json')
  })

  it('POSTs the payload as JSON when creating a link', async () => {
    fetch.mockReturnValue(respond(201, { slug: 'abc' }))
    await createLink({ destination_url: 'https://e.com', slug: 'abc' })
    const [url, opts] = fetch.mock.calls[0]
    expect(url).toBe('/api/links')
    expect(opts.method).toBe('POST')
    expect(JSON.parse(opts.body)).toEqual({ destination_url: 'https://e.com', slug: 'abc' })
  })

  it('builds analytics URLs with days and interval', async () => {
    fetch.mockReturnValue(respond(200, {}))
    await getOverview({ days: 7, interval: 'week' })
    await getLinkAnalytics('my slug/1', { days: 30, interval: 'day' })
    expect(fetch.mock.calls[0][0]).toBe('/api/analytics?days=7&interval=week')
    expect(fetch.mock.calls[1][0]).toBe('/api/links/my%20slug%2F1/analytics?days=30&interval=day')
  })

  it('surfaces the first Laravel validation message', async () => {
    fetch.mockReturnValue(respond(422, {
      message: 'The given data was invalid.',
      errors: { slug: ['The slug is reserved.', 'second'], url: ['other'] },
    }))
    await expect(createLink({})).rejects.toThrow('The slug is reserved.')
  })

  it('falls back to the message, then to the status code', async () => {
    fetch.mockReturnValueOnce(respond(410, { message: 'This link has expired.' }))
    await expect(listLinks()).rejects.toThrow('This link has expired.')
    fetch.mockReturnValueOnce(respond(500, undefined))
    await expect(listLinks()).rejects.toThrow('Request failed (500)')
  })

  it('encodes the slug in the QR url', () => {
    expect(qrUrl('a b')).toBe('/api/links/a%20b/qr')
  })
})

describe('api token', () => {
  afterEach(() => { sessionStorage.clear() })

  it('sends no Authorization header without a token', async () => {
    fetch.mockReturnValue(respond(200, {}))
    await listLinks()
    expect(fetch.mock.calls[0][1].headers.Authorization).toBeUndefined()
  })

  it('sends the saved token as a Bearer header', async () => {
    setToken('s3cret')
    fetch.mockReturnValue(respond(200, {}))
    await listLinks()
    expect(fetch.mock.calls[0][1].headers.Authorization).toBe('Bearer s3cret')
  })

  it('surfaces the server message on 401', async () => {
    fetch.mockReturnValue(respond(401, { message: 'Invalid or missing API token.' }))
    await expect(listLinks()).rejects.toThrow('Invalid or missing API token.')
  })
})
