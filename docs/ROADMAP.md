# Roadmap

Target completion: **October 20, 2026**

Three two-week sprints, starting September 9, 2026.

## Sprint 1 — Backend Foundation (Sep 9 – Sep 21)

Goal: a working, tested API that can create short links and log clicks.

- [x] Laravel project scaffold
- [x] `Link` model + migration (slug, destination_url, clicks_count)
- [x] `LinkClick` model + migration (link_id, ip, user_agent, referrer)
- [x] `LinkController` REST endpoints (`index`, `store`, `show`)
- [x] Redirect endpoint logs a click on every visit
- [ ] Slug validation (uniqueness, reserved words, allowed characters)
- [ ] Rate limiting on link creation
- [ ] Basic feature tests for the API endpoints
- [ ] Deploy backend to a staging environment

## Sprint 2 — Frontend & QR Codes (Sep 22 – Oct 5)

Goal: a usable UI for creating and managing links.

- [x] React (Vite) frontend scaffold
- [ ] Link creation form (destination URL, optional custom slug)
- [ ] Link list view with copy-to-clipboard short URL
- [ ] QR code generation per link (client-side, downloadable PNG/SVG)
- [ ] Connect frontend to the Laravel API
- [ ] Basic responsive layout and styling

## Sprint 3 — Analytics Dashboard & Polish (Oct 6 – Oct 20)

Goal: a working analytics dashboard and a deployed, demo-ready app.

- [ ] Aggregate click data by day/week for time-series charts
- [ ] Geography breakdown (IP-based lookup)
- [ ] Device/browser breakdown (user-agent parsing)
- [ ] Referrer breakdown
- [ ] Dashboard charts (time-series, pie/bar breakdowns)
- [ ] Per-link analytics detail view
- [ ] Final polish pass (empty states, loading states, error handling)
- [ ] Deploy full app (backend + frontend) to production
- [ ] Write up project README with screenshots
