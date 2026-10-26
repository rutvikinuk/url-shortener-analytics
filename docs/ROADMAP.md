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
- [x] Slug validation (uniqueness, reserved words, allowed characters)
- [x] Rate limiting on link creation
- [x] Basic feature tests for the API endpoints (links + analytics done; more on test day)
- [ ] Deploy backend to a staging environment — Docker image ready, not deployed

## Sprint 2 — Frontend & QR Codes (Sep 22 – Oct 5)

Goal: a usable UI for creating and managing links.

- [x] React (Vite) frontend scaffold
- [x] Link creation form (destination URL, optional custom slug)
- [x] Link list view with copy-to-clipboard short URL
- [x] QR code generation per link (server-side SVG)
- [x] Connect frontend to the Laravel API
- [x] Basic responsive layout and styling

## Sprint 3 — Analytics Dashboard & Polish (Oct 6 – Oct 20)

Goal: a working analytics dashboard and a deployed, demo-ready app.

- [x] Aggregate click data by day/week for time-series charts
- [ ] Geography breakdown (IP-based lookup) — not built, future work
- [ ] Device/browser breakdown (user-agent parsing) — not built, future work
- [x] Referrer breakdown
- [x] Dashboard charts (time-series, pie/bar breakdowns)
- [x] Per-link analytics detail view
- [x] Final polish pass (loading states, error handling)
- [ ] Deploy full app (backend + frontend) to production — Docker image ready, not deployed
- [x] Write up project README with screenshots
