# Dev Log

## 2026-10-19 — Day 1: Audit and setup fixes

**Built:** Ran the whole project from a clean clone: `composer install`, migrations, the API with curl, `php artisan test`, and the frontend install/build/lint. Everything works. Fixes made: Vite dev server now proxies `/api` to Laravel (so the React app can call the API without CORS trouble), `.env.example` has a proper app name, and the README has verified setup/test/smoke-test steps plus a "Known issues" list that becomes the to-do list for this block.

**Concept — an audit before building:** Before adding features you run the project like a stranger would and write down what is true. That tells you which bugs are yours and which are old, and it makes README instructions trustworthy. Also learned: a dev-server *proxy*. The browser only talks to Vite (port 5173); Vite quietly forwards `/api/...` to Laravel (port 8000). To the browser it is one origin, so no CORS setup is needed in development.

**Self-check:** Why can a slug like `api` or `up` be dangerous in a URL shortener, and where in the request lifecycle would you block it?

**Next (Day 2):** Core feature 1 — slug generation and validation (reserved words, allowed characters), plus a short `/{slug}` redirect with click logging.
