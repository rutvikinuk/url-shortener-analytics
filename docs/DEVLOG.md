# Dev Log

## 2026-10-19 — Day 1: Audit and setup fixes

**Built:** Ran the whole project from a clean clone: `composer install`, migrations, the API with curl, `php artisan test`, and the frontend install/build/lint. Everything works. Fixes made: Vite dev server now proxies `/api` to Laravel (so the React app can call the API without CORS trouble), `.env.example` has a proper app name, and the README has verified setup/test/smoke-test steps plus a "Known issues" list that becomes the to-do list for this block.

**Concept — an audit before building:** Before adding features you run the project like a stranger would and write down what is true. That tells you which bugs are yours and which are old, and it makes README instructions trustworthy. Also learned: a dev-server *proxy*. The browser only talks to Vite (port 5173); Vite quietly forwards `/api/...` to Laravel (port 8000). To the browser it is one origin, so no CORS setup is needed in development.

**Self-check:** Why can a slug like `api` or `up` be dangerous in a URL shortener, and where in the request lifecycle would you block it?

**Next (Day 2):** Core feature 1 — slug generation and validation (reserved words, allowed characters), plus a short `/{slug}` redirect with click logging.

## 2026-10-21 — Day 3: Analytics queries (plus the missed Day 2)

**Catch-up note:** Day 2 (20 Oct) was not pushed, so I finished it first. Slug validation now enforces 3-32 characters of `A-Za-z0-9_-`, lowercases slugs (so `Demo` and `demo` are the same link), and rejects reserved words like `api` and `up`. The short URL `GET /{slug}` now returns a 302 and logs the click (IP, user agent, referrer) in a transaction. The old `/api/links/{slug}` still works. The destination must now be `http` or `https`, so `javascript:` URLs are rejected.

**Built today (Day 3):** `GET /api/links/{slug}/analytics` and `GET /api/analytics` return total clicks, unique visitors (distinct IP), a zero-filled time series by day or ISO week, top referrer hosts ("Direct" when there is no Referer), and for the overview the top links. Query params: `days` (1-365) and `interval` (`day`|`week`). The queries live in `app/Services/ClickAnalytics.php`. 18 tests pass, and I smoke-tested it with curl against the running server.

**Concept — aggregation in SQL, shaping in PHP:** Asking the database to `GROUP BY date(created_at)` and `COUNT(*)` returns a few dozen small rows, even if the table has millions of clicks. Loading every click into PHP and counting there would be slow and memory-hungry. So the database counts, and PHP only does the final tidying. That tidying includes *zero-filling*: days with no clicks have no rows in the table, so a chart would skip them and draw a misleading line. We build every day in the range first, then add the counts into it. Weekly grouping is done by folding the daily counts into the Monday of each week, which works the same on SQLite, MySQL and Postgres. "Unique visitors" is `COUNT(DISTINCT ip)`, an approximation, because many people share one IP and one person can change IPs.

**Self-check:** A link got clicks on Monday and Thursday only. Why would a chart built straight from `GROUP BY date` mislead, and what does `timeSeries()` do to fix it? Bonus: why is `COUNT(DISTINCT ip)` only an approximation of real visitors?

**Next (Day 4):** Core feature 3 — custom slugs/expiry/QR. Slugs already work, so add link expiry (`expires_at`, 410 Gone after expiry) and a QR code endpoint for each link.

## 2026-10-22 — Day 4: Link expiry and QR codes

**Built:** Links can now expire. `POST /api/links` accepts an optional `expires_at` (must be a valid future date); a new migration adds the nullable `expires_at` column. After that moment `GET /{slug}` (and `/api/links/{slug}`) return `410 Gone` and the visit is *not* logged as a click. New endpoint `GET /api/links/{slug}/qr` returns an SVG QR code that points at the short URL. Custom slugs were already done on Day 2, so they are untouched. Because Packagist was not reachable from the build machine, I wrote a small QR encoder myself (`app/Services/QrCode.php`, byte mode, error correction M, up to 213 characters) and checked its output with OpenCV's QR decoder on several strings. 27 tests pass (9 new), and I smoke-tested with curl. Tip: once network access to Packagist works, `bacon/bacon-qr-code` is the usual library choice.

**Concept — 410 vs 404, and how a QR code is made:** `404 Not Found` says "no idea what this is"; `410 Gone` says "this existed on purpose and was removed", which tells browsers and search engines to stop asking. Expiry is also checked at read time (`expires_at` is in the past?), not by a background job deleting rows, so it is exact to the second and the click history stays intact for analytics. A QR code is just your text turned into bytes, plus Reed-Solomon error-correction bytes (extra maths bytes so a scratched code still scans), laid out as black/white squares around three corner "finder" squares. Then 8 possible "masks" (checkerboard-like flips) are tried and the one with the fewest ugly patterns wins, so scanners don't get confused by big blank areas.

**Self-check:** Why do we compute "is it expired?" when the link is visited instead of deleting expired rows with a scheduled job? And what is the one thing that must NOT happen when an expired link is visited (hint: analytics)?

**Next (Day 5):** Core feature 4 — the React dashboard: call the API from the frontend, create links, and draw the click-analytics charts.

## 2026-10-23 — Day 5: React dashboard with click charts

**Built:** The Vite starter page is replaced by a real dashboard. You can create a short link (with optional custom slug and expiry) and see server validation errors inline. Click a link to see its QR code and its own analytics; click it again to go back to "all links". A range picker (7/30/90 days) and a day/week toggle drive the data. It shows total clicks, unique visitors, an SVG line chart of clicks over time with hover tooltips, and bar lists for top referrers and top links. The charts are hand-written SVG, with no chart library. Files: `frontend/src/api.js`, `chartMath.js`, `components/*`. I tested it against the running Laravel API in a headless browser (create link, QR, filters, no console errors). The backend's 27 tests still pass. Screenshot: `docs/dashboard.png`.

**Concept — mapping data to pixels, and "stale" responses:** A chart is just a function from numbers to coordinates. In SVG the y axis points down, so a bigger value means a smaller y: `y = height - (value / max) * height`. We round the max up to a tidy number (`niceMax`) so the axis ends on 5, 30 or 200 and not on 23. Because the API already zero-fills missing days, the line has no misleading gaps. On the React side, each time you change a filter we fetch again inside `useEffect`. If you click quickly, a slow old response could arrive after a newer one and overwrite it. The `stale` flag in the effect's cleanup function stops that: the old effect marks itself stale, and its result is ignored.

**Self-check:** Why does `toPoints` compute `height - (clicks / max) * height` instead of just `(clicks / max) * height`? And what bug would appear without the `stale` flag if you switch from "7 days" to "90 days" on a slow network?

**Next (Day 6):** Tests for the code added this block — more backend feature tests (expiry, QR, analytics edge cases) and frontend tests for `chartMath` and the components.
