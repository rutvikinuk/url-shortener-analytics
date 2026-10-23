// Pure helpers for the SVG line chart (kept separate so they are easy to test).

/** Round the max up to a "nice" number so the y-axis ends on a tidy value. */
export function niceMax(value) {
  if (value <= 5) return 5
  const magnitude = 10 ** Math.floor(Math.log10(value))
  return Math.ceil(value / magnitude) * magnitude
}

/** Turn [{date, clicks}] into x/y pixel points inside a width x height box. */
export function toPoints(series, width, height) {
  const max = niceMax(Math.max(0, ...series.map((p) => p.clicks)))
  const stepX = series.length > 1 ? width / (series.length - 1) : 0
  return {
    max,
    points: series.map((p, i) => ({
      ...p,
      x: series.length > 1 ? i * stepX : width / 2,
      // SVG's y axis points DOWN, so a bigger value means a smaller y.
      y: height - (p.clicks / max) * height,
    })),
  }
}
