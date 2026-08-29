# ISP Billing & Management System

A web-based billing and management system for an Internet Service Provider, built
with Laravel 12, Blade, Bootstrap 5 and vanilla JavaScript, targeting a local
XAMPP (Apache + MariaDB/MySQL) stack.

> **Project status: foundation only.** The Laravel application, database
> connection and Bootstrap 5 asset pipeline are configured and verified. The ISP
> business modules (customers, plans, subscriptions, billing, invoices, payments,
> receipts, expenses, reports, dashboard, audit logs, settings) are not
> implemented yet.

## Requirements

| Component | Version used |
|---|---|
| PHP | 8.2+ (8.2.12 via XAMPP) |
| Composer | 2.x |
| Node.js / npm | 20+ (24.13.0 / 11.6.2) |
| MySQL or MariaDB | MariaDB 10.4+ / MySQL 8.0+ |
| Apache | XAMPP bundled (optional — see below) |

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `gd`, `zip`. Running the test
suite also needs `pdo_sqlite`.

## Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

### Database

Start MySQL from the XAMPP Control Panel, then create the database:

```sql
CREATE DATABASE isp_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Confirm the connection settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=isp_billing
DB_USERNAME=root
DB_PASSWORD=
```

Then run the migrations:

```bash
php artisan migrate
php artisan migrate:status
```

> If a standalone MySQL Server is also installed alongside XAMPP, its client may
> come first on `PATH` while XAMPP's MariaDB owns port 3306. Use
> `C:\xampp\mysql\bin\mysql.exe` explicitly to avoid connecting with a mismatched
> client.

### Frontend assets

```bash
npm run build   # production build
npm run dev     # Vite dev server with hot reload
```

Bootstrap 5 is themed from `resources/css/app.scss`. Brand colours are declared
there as Sass variables and merged into Bootstrap's `$theme-colors` **before**
`_maps.scss` is imported, which is what generates the `bg-navy`, `text-navy` and
related brand utilities. Do not hard-code hex values in Blade views.

## Running the application

### Option A — Laravel development server (preferred)

```bash
php artisan serve
```

Available at <http://127.0.0.1:8000>. If port 8000 is taken, pass
`--port=<free port>`.

### Option B — XAMPP Apache

With the project in `C:\xampp\htdocs\isp-billing-system`, start Apache and open
<http://localhost/isp-billing-system/public/>. This requires `mod_rewrite`, which
XAMPP enables by default. For a cleaner URL, point an Apache virtual host's
`DocumentRoot` at the project's `public/` directory.

Both options are verified working.

## Testing

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`), so
they never touch the development MySQL database.

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application name shown in the UI. Defaults to `ISP Billing`. |
| `APP_URL` | Base URL used for generated links and assets. |
| `APP_DEBUG` | Must be `false` in production. |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Default to `database`. |
| `MAIL_*` | Mail transport. Defaults to the `log` driver in development. |

`.env` is never committed. Copy `.env.example` and fill it in per environment.

## Production considerations

- Set `APP_DEBUG=false` and `APP_ENV=production`.
- Generate a fresh `APP_KEY`; never reuse the development key.
- Use a dedicated MySQL user with least privilege instead of `root`.
- Run `php artisan config:cache route:cache view:cache` and `npm run build`.
- Serve `public/` as the document root; never expose the project root.

## Planned integrations

The schema and architecture are being designed so the following can be added
later without restructuring: MikroTik and RADIUS integration, PPPoE and hotspot
management, SMS and email notifications, online payment gateways, and automatic
suspension/reconnection driven by the billing state.
