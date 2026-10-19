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
curl -i localhost:8000/api/links/demo     # 302 to the destination, click logged
```

Always send `Accept: application/json` so validation errors come back as JSON.

## API

| Method | Endpoint            | Description                                  |
|--------|----------------------|-----------------------------------------------|
| GET    | `/api/links`         | List all links                                |
| POST   | `/api/links`         | Create a new short link                       |
| GET    | `/api/links/{slug}`  | Redirect to the destination and log the click |

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for the sprint-by-sprint build plan.

## Known issues (Day 1 audit, 19 Oct 2026)

Verified by running the app locally: link creation, custom slugs, duplicate
slug rejection (422), non-http URL rejection, 404 for unknown slugs, 302
redirect with click logging, and the frontend build/lint all work. Still open:

- Reserved slugs (e.g. `api`, `up`) are accepted. Planned for the slug-validation day.
- No rate limiting on `POST /api/links`.
- The redirect lives at `/api/links/{slug}`, not a short `/{slug}` URL.
- The frontend is still the Vite starter page and does not call the API yet.
- Only the two default example tests exist; real tests come later in the plan.
