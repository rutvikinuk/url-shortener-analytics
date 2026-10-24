import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import BarList from '../BarList.jsx'
import LineChart from '../LineChart.jsx'
import LinkForm from '../LinkForm.jsx'
import LinkTable from '../LinkTable.jsx'

vi.mock('../../api.js', () => ({ createLink: vi.fn() }))
import { createLink } from '../../api.js'

describe('BarList', () => {
  it('shows the empty text when there are no rows', () => {
    render(<BarList rows={[]} labelKey="referrer" emptyText="Nothing here" />)
    expect(screen.getByText('Nothing here')).toBeInTheDocument()
  })
  it('sizes bars relative to the biggest row', () => {
    const rows = [{ referrer: 'google.com', clicks: 8 }, { referrer: 'Direct', clicks: 2 }]
    const { container } = render(<BarList rows={rows} labelKey="referrer" />)
    const fills = [...container.querySelectorAll('.bar-fill')].map((el) => el.style.width)
    expect(fills).toEqual(['100%', '25%'])
    expect(screen.getByText('google.com')).toBeInTheDocument()
    expect(screen.getByText('8')).toBeInTheDocument()
  })
})

describe('LineChart', () => {
  const series = [
    { date: '2026-10-19', clicks: 1 },
    { date: '2026-10-20', clicks: 0 },
    { date: '2026-10-21', clicks: 4 },
  ]
  it('renders one dot per day with an accessible label', () => {
    const { container } = render(<LineChart series={series} />)
    expect(screen.getByRole('img')).toHaveAccessibleName(/Clicks over time, peak axis 5/)
    expect(container.querySelectorAll('circle')).toHaveLength(3)
  })
  it('shows a tooltip while hovering a dot', () => {
    const { container } = render(<LineChart series={series} />)
    fireEvent.mouseEnter(container.querySelectorAll('circle')[2])
    expect(screen.getByText('2026-10-21: 4')).toBeInTheDocument()
    fireEvent.mouseLeave(container.querySelectorAll('circle')[2])
    expect(screen.queryByText('2026-10-21: 4')).not.toBeInTheDocument()
  })
  it('shows a message instead of an empty chart', () => {
    render(<LineChart series={[]} />)
    expect(screen.getByText('No data yet.')).toBeInTheDocument()
  })
})

describe('LinkTable', () => {
  const links = [
    { id: 1, slug: 'live', destination_url: 'https://a.com', expires_at: null },
    { id: 2, slug: 'old', destination_url: 'https://b.com', expires_at: '2000-01-01T00:00:00Z' },
  ]
  it('shows a hint when empty', () => {
    render(<LinkTable links={[]} selected={null} onSelect={() => {}} />)
    expect(screen.getByText(/No links yet/)).toBeInTheDocument()
  })
  it('flags expired links only', () => {
    render(<LinkTable links={links} selected={null} onSelect={() => {}} />)
    expect(screen.getAllByText('expired')).toHaveLength(1)
  })
  it('selects a link, and clicking the selected one deselects it', async () => {
    const onSelect = vi.fn()
    const { rerender } = render(<LinkTable links={links} selected={null} onSelect={onSelect} />)
    await userEvent.click(screen.getByRole('button', { name: /\/live/ }))
    expect(onSelect).toHaveBeenLastCalledWith('live')
    rerender(<LinkTable links={links} selected="live" onSelect={onSelect} />)
    expect(screen.getByRole('button', { name: /\/live/ })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(screen.getByRole('button', { name: /\/live/ }))
    expect(onSelect).toHaveBeenLastCalledWith(null)
  })
})

describe('LinkForm', () => {
  beforeEach(() => { createLink.mockReset() })

  it('submits only the filled fields, then clears the form', async () => {
    createLink.mockResolvedValue({ slug: 'abc' })
    const onCreated = vi.fn()
    render(<LinkForm onCreated={onCreated} />)
    await userEvent.type(screen.getByLabelText(/Destination URL/), 'https://example.com/x')
    await userEvent.click(screen.getByRole('button', { name: /Create short link/ }))
    await waitFor(() => expect(onCreated).toHaveBeenCalledWith({ slug: 'abc' }))
    expect(createLink).toHaveBeenCalledWith({ destination_url: 'https://example.com/x' })
    expect(screen.getByLabelText(/Destination URL/)).toHaveValue('')
  })

  it('sends a trimmed custom slug', async () => {
    createLink.mockResolvedValue({ slug: 'mine' })
    render(<LinkForm onCreated={() => {}} />)
    await userEvent.type(screen.getByLabelText(/Destination URL/), 'https://example.com')
    await userEvent.type(screen.getByLabelText(/Custom slug/), '  mine  ')
    await userEvent.click(screen.getByRole('button', { name: /Create/ }))
    await waitFor(() => expect(createLink).toHaveBeenCalled())
    expect(createLink.mock.calls[0][0]).toEqual({ destination_url: 'https://example.com', slug: 'mine' })
  })

  it('shows the server error and keeps what was typed', async () => {
    createLink.mockRejectedValue(new Error('The slug is reserved.'))
    const onCreated = vi.fn()
    render(<LinkForm onCreated={onCreated} />)
    await userEvent.type(screen.getByLabelText(/Destination URL/), 'https://example.com')
    await userEvent.click(screen.getByRole('button', { name: /Create/ }))
    expect(await screen.findByRole('alert')).toHaveTextContent('The slug is reserved.')
    expect(onCreated).not.toHaveBeenCalled()
    expect(screen.getByLabelText(/Destination URL/)).toHaveValue('https://example.com')
    expect(screen.getByRole('button', { name: /Create/ })).toBeEnabled()
  })
})
