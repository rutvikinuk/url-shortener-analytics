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
