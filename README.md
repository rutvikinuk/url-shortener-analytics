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
php artisan serve
```

Make sure `.env` has:

```
DB_CONNECTION=sqlite
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

## API

| Method | Endpoint            | Description                                  |
|--------|----------------------|-----------------------------------------------|
| GET    | `/api/links`         | List all links                                |
| POST   | `/api/links`         | Create a new short link                       |
| GET    | `/api/links/{slug}`  | Redirect to the destination and log the click |

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for the sprint-by-sprint build plan.
