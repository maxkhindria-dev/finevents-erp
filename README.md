# FinEvents ERP

One secure web app for FinEvents (France, UK, USA): CRM, time tracking, projects, finance matching, time off and wiki.

- **Project brief and rules:** [`CLAUDE.md`](CLAUDE.md)
- **Why this tech stack:** [`docs/stack-review.md`](docs/stack-review.md)
- **Current phase plan:** [`docs/phase-1-plan.md`](docs/phase-1-plan.md)

**Stack:** PHP 8.3 + Laravel 12, MySQL 8, Bootstrap 5 + plain JavaScript, hosted on Laravel Cloud (EU).

## Run it on a developer's computer

Needs PHP 8.3, Composer and Node 22.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # demo data is generated and fictional (@example.test)
npm run build
php artisan serve
```

Then open http://localhost:8000.

## Checks (the same ones GitHub runs on every pull request)

```bash
vendor/bin/pest          # automated tests
vendor/bin/pint --test   # code style
vendor/bin/phpstan analyse   # static analysis
composer audit && npm audit  # known vulnerabilities in packages
```

GitHub also runs **gitleaks** (secret scanning) and **Semgrep** (security analysis). See `.github/workflows/`.

## Rules

- Never commit secrets. Real values live in **Dashlane** and in Laravel Cloud's encrypted environment settings. `.env.example` holds placeholder names only.
- Never use production data outside production. Use generated test data.
- One feature per branch and pull request, with a plain-English summary and "how to test" steps.
