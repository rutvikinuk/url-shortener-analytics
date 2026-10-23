# URL Shortener + Analytics Dashboard

A link-shortening service with custom slugs and QR code generation, backed by a
dashboard that shows click analytics — geography, device, referrer, and
time-series breakdowns for every link.

**Status:** 🚧 In active development

## Tech Stack

- **Backend:** Laravel 12 (PHP 8.3), SQLite for local development
- **Frontend:** React (Vite)
- **API:** REST, JSON

## Planned Features

- Custom short slugs, with random slug generation as a fallback
- QR code generation per link
- Click analytics dashboard: geography, device, and referrer breakdowns
- Time-series charts for click activity over time

## Project Structure

```
.
├── app/                # Laravel application code (models, controllers)
├── database/           # Migrations and the local SQLite database
├── routes/              # web.php and api.php route definitions
├── frontend/            # React (Vite) frontend
└── docs/
    └── ROADMAP.md        # Sprint plan and build timeline
```

## Setup

### Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve        # http://localhost:8000
php artisan test         # run the backend tests
```

Requires PHP 8.3 with the `sqlite3`, `pdo_sqlite`, `mbstring`, `dom` and `xml`
extensions, and Composer.

Make sure `.env` has:

```
DB_CONNECTION=sqlite
```

### Frontend

The dashboard (create links, QR code, click charts) lives in `frontend/`; screenshot: `docs/dashboard.png`. Run the backend on port 8000 first.

```bash
cd frontend
npm install
npm run dev      # http://localhost:5173, proxies /api to Laravel on :8000
npm run build    # production build
npm run lint
```

### Quick smoke test

With the backend running:

```bash
curl -X POST localhost:8000/api/links -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"destination_url":"https://example.com","slug":"demo"}'
curl -i localhost:8000/demo               # 302 to the destination, click logged
curl localhost:8000/api/links/demo/analytics?days=7
```

Always send `Accept: application/json` so validation errors come back as JSON.

## API

| Method | Endpoint            | Description                                  |
|--------|----------------------|-----------------------------------------------|
| GET    | `/api/links`         | List all links                                |
| POST   | `/api/links`         | Create a new short link                       |
| GET    | `/api/links/{slug}`  | Redirect to the destination and log the click |
| GET    | `/{slug}`            | Short URL: 302 to the destination, click logged |
| GET    | `/api/links/{slug}/analytics` | Per-link totals, time series, top referrers |
| GET    | `/api/analytics`     | Same for all links, plus `top_links`          |
| GET    | `/api/links/{slug}/qr` | QR code (SVG) for the short URL             |

Slugs: 3-32 characters of letters, numbers, `-` and `_`; stored lowercase;
reserved words (`api`, `up`, `admin`, ...) are rejected.

Optional `expires_at` (a future date/time) on `POST /api/links`: after that moment
the short URL and QR endpoint return `410 Gone` and visits are not counted.
The QR code is generated in-app (`app/Services/QrCode.php`), no external service.

Analytics query params: `days` (1-365, default 30) and `interval` (`day` or
`week`). The series is zero-filled so charts have no gaps.

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for the sprint-by-sprint build plan.

## Known issues (Day 1 audit, 19 Oct 2026; updated Day 3)

Verified by running the app locally: link creation, custom slugs, duplicate
slug rejection (422), non-http URL rejection, 404 for unknown slugs, 302
redirect with click logging, and the frontend build/lint all work. Still open:

- No rate limiting on `POST /api/links`.
- Frontend has no automated tests yet (planned for Day 6); the dashboard was checked by hand in a browser.
- Test coverage so far: slug validation, redirect and click logging, analytics queries (`php artisan test`).
