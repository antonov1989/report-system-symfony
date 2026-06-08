# 💰 Finance Tracker

A personal finance / expense tracker built with **Symfony 7.4 (LTS)** and **PHP 8.2**.
It exposes a JWT-secured REST API and ships a lightweight server-rendered dashboard
with charts.

> Portfolio project demonstrating clean API design, JWT auth, asynchronous processing
> with Messenger, scheduled jobs, validation, and a tested codebase.

---

## Features

- **JWT authentication** (LexikJWTAuthenticationBundle) — stateless registration & login.
- **REST API** for accounts, categories, transactions, budgets and recurring transactions,
  with request DTOs, validation (`#[MapRequestPayload]`), pagination and filtering.
- **Per-user data isolation** — every resource is scoped to the authenticated owner.
- **Budgets with email alerts** — when a monthly category budget is exceeded, an email
  alert is dispatched **asynchronously** via Messenger (caught by Mailpit in dev).
- **Asynchronous CSV import** — upload a CSV of transactions; it's parsed off-request by
  a Messenger worker, auto-creating categories as needed.
- **Recurring transactions** — templates materialised into real transactions by a daily
  console command (`app:recurring:run`), suitable for cron.
- **Reports** — net worth, monthly income vs expense, and spending-by-category aggregations.
- **Dashboard** (Twig + Chart.js) — overview cards, charts and budget progress bars,
  protected by session-based form login with stateless CSRF.
- **OpenAPI docs** at `/api/doc` (NelmioApiDocBundle + Swagger UI).
- **Tested** — functional API tests + a dashboard test (PHPUnit), with transactional
  isolation via DAMADoctrineTestBundle.

## Tech stack

| Area        | Choice                                             |
|-------------|----------------------------------------------------|
| Framework   | Symfony 7.4 LTS, PHP 8.2                            |
| Database    | PostgreSQL 16 (Doctrine ORM 3, UUID v7 keys)       |
| Auth        | JWT (API) + session form login (dashboard)         |
| Async       | Symfony Messenger (Doctrine transport)             |
| Mail        | Symfony Mailer → Mailpit (dev)                      |
| Frontend    | Twig + AssetMapper/importmap + Chart.js            |
| Infra (dev) | Docker Compose (Postgres + Mailpit), Symfony CLI   |
| Tests       | PHPUnit, DAMADoctrineTestBundle                    |

## Architecture

```
HTTP ─┬─ /api/**          JWT firewall (stateless)
      │   └─ Controller/Api/*  → DTO (#[MapRequestPayload] + validation)
      │                         → Doctrine repositories (owner-scoped queries)
      │                         → Service\BudgetMonitor ─dispatch─▶ Messenger
      └─ /dashboard       session firewall → DashboardController → Twig + Chart.js

Messenger (async transport)
  ├─ ImportTransactionsCsv      → parse CSV, persist transactions
  └─ BudgetExceededNotification → send email via Mailer

Console
  └─ app:recurring:run          → materialise due recurring transactions (cron)
```

Money is stored as `DECIMAL(14,2)`; transaction sign is derived from its type
(`income` / `expense` / `transfer`). Account balance = opening balance + Σ signed
transactions, computed in SQL.

---

## Getting started

### Prerequisites
- PHP 8.2 with `pdo_pgsql`, `mbstring`, `intl`
- Composer, Docker + Docker Compose, [Symfony CLI](https://symfony.com/download)

### Setup

```bash
composer install

# Start Postgres + Mailpit (Symfony CLI auto-injects DATABASE_URL / MAILER_DSN)
docker compose up -d

# Generate JWT keypair
php bin/console lexik:jwt:generate-keypair

# Create the schema
symfony console doctrine:migrations:migrate -n
symfony console messenger:setup-transports

# (optional) seed a demo account with 6 months of data
symfony console app:demo:seed   # → demo@example.com / secret123

# Run the app
symfony serve -d
symfony console messenger:consume async   # process async jobs
```

Open <http://localhost:8000> for the dashboard and <http://localhost:8000/api/doc>
for the API docs. Sent emails appear in Mailpit (`docker compose port mailer 8025`).

> Use `symfony console …` (not `php bin/console …`) so the Docker `DATABASE_URL`
> and `MAILER_DSN` are injected automatically.

### Tests

```bash
symfony console --env=test doctrine:database:create
symfony console --env=test doctrine:schema:create
symfony php bin/phpunit
```

---

## API overview

All `/api/**` routes except `register`, `login` and `doc` require
`Authorization: Bearer <token>`.

| Method | Path                          | Description                          |
|--------|-------------------------------|--------------------------------------|
| POST   | `/api/register`               | Create an account                    |
| POST   | `/api/login`                  | Obtain a JWT                         |
| GET    | `/api/me`                     | Current user                         |
| GET/POST | `/api/accounts`             | List / create accounts (with balance)|
| GET/PUT/DELETE | `/api/accounts/{id}`  | Read / update / delete account       |
| GET/POST | `/api/categories`           | List / create categories             |
| GET/POST | `/api/transactions`         | List (filter+paginate) / create      |
| GET/PUT/DELETE | `/api/transactions/{id}` | Read / update / delete           |
| GET/POST | `/api/budgets`              | List (with spent/progress) / create  |
| GET/POST | `/api/recurring`            | List / create recurring templates    |
| POST   | `/api/import/transactions`    | Upload a CSV (async import)          |
| GET    | `/api/reports/summary`        | Net worth + month totals             |
| GET    | `/api/reports/by-category`    | Spending grouped by category         |
| GET    | `/api/reports/monthly`        | Income vs expense per month          |

Transaction list filters: `accountId`, `categoryId`, `type`, `from`, `to`, `page`, `perPage`.

### Example

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"secret123"}' | jq -r .token)

curl localhost:8000/api/reports/summary -H "Authorization: Bearer $TOKEN"
```

### CSV import format

```csv
date,type,amount,category,description
2026-06-01,expense,42.50,Groceries,Supermarket
2026-06-02,income,2500.00,Salary,Monthly pay
```
