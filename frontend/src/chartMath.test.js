import { describe, expect, it } from 'vitest'
import { niceMax, toPoints } from './chartMath.js'

describe('niceMax', () => {
  it('never goes below 5 so an empty chart still has an axis', () => {
    expect(niceMax(0)).toBe(5)
    expect(niceMax(5)).toBe(5)
  })
  it('rounds up to one significant digit', () => {
    expect(niceMax(6)).toBe(6)
    expect(niceMax(23)).toBe(30)
    expect(niceMax(101)).toBe(200)
    expect(niceMax(999)).toBe(1000)
  })
  it('keeps exact round numbers unchanged', () => {
    expect(niceMax(10)).toBe(10)
    expect(niceMax(200)).toBe(200)
  })
})

describe('toPoints', () => {
  const series = [
    { date: '2026-10-01', clicks: 0 },
    { date: '2026-10-02', clicks: 10 },
    { date: '2026-10-03', clicks: 5 },
  ]

  it('spreads points evenly across the width', () => {
    const { points } = toPoints(series, 200, 100)
    expect(points.map((p) => p.x)).toEqual([0, 100, 200])
  })
  it('flips y: zero clicks sit at the bottom, the max at the top', () => {
    const { max, points } = toPoints(series, 200, 100)
    expect(max).toBe(10)
    expect(points[0].y).toBe(100)
    expect(points[1].y).toBe(0)
    expect(points[2].y).toBe(50)
  })
  it('keeps the original fields on each point', () => {
    const { points } = toPoints(series, 200, 100)
    expect(points[1]).toMatchObject({ date: '2026-10-02', clicks: 10 })
  })
  it('centres a single point instead of dividing by zero', () => {
    const { points } = toPoints([{ date: 'd', clicks: 2 }], 200, 100)
    expect(points[0].x).toBe(100)
    expect(Number.isFinite(points[0].y)).toBe(true)
  })
  it('handles an empty series', () => {
    expect(toPoints([], 200, 100)).toEqual({ max: 5, points: [] })
  })
  it('an all-zero series stays on the baseline', () => {
    const { points } = toPoints([{ date: 'a', clicks: 0 }, { date: 'b', clicks: 0 }], 100, 50)
    expect(points.every((p) => p.y === 50)).toBe(true)
  })
})
