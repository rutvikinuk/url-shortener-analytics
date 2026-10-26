# Demo script (about 5 minutes)

Start it: `docker compose up --build`, then open http://localhost:8000/dashboard/
(or run the backend with `php artisan serve` and the frontend with `npm run dev`).

1. **The idea (30s).** "A short link is a database row: slug -> destination. Visiting the slug looks the row up, logs a click and redirects."
2. **Create a link (60s).** In the dashboard, paste `https://example.com`, use the slug `demo`, click create. Show the QR code that appears. Try creating `demo` again to show the 422 "already taken" message, and `http://localhost/x` to show the safe-destination rule.
3. **Generate traffic (60s).** In a terminal:
   ```bash
   for i in $(seq 1 20); do curl -s -o /dev/null -e https://news.example/post http://localhost:8000/demo; done
   curl -i http://localhost:8000/demo        # show the 302 and Location header
   ```
4. **Analytics (60s).** Reload the dashboard: total clicks, unique visitors, the line chart, top referrers. Switch 7/30/90 days and day/week. Hover the chart for tooltips.
5. **Expiry (30s).** Create a link with an `expires_at` a minute away, visit it before and after: `302`, then `410 Gone` (and it is not counted).
6. **Safety (45s).** Hit `POST /api/links` 11 times quickly to show the `429`. Start the server with `API_TOKEN=secret` and show `401` without the header, `200` with `-H 'Authorization: Bearer secret'`, and that `/demo` still redirects.
7. **Engineering (45s).** Show the GitHub Actions run: backend tests, frontend tests, audits, Docker build + smoke test.

Tear down: `docker compose down -v`.
