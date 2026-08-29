# ISP Billing & Management System

A web-based billing and management system for an Internet Service Provider, built
with Laravel 12, Blade, Bootstrap 5 and vanilla JavaScript, targeting a local
XAMPP (Apache + MariaDB/MySQL) stack.

> **Project status: in development.** Authentication, role-based access control,
> staff user management, customers, internet plans and subscriptions are working
> on the full billing schema. The remaining modules (billing,
> invoices, payments, receipts, expenses, reports, analytics, audit logs and
> settings) are not implemented yet.

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

# Customer profile photos are served from the public disk.
php artisan storage:link
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

Then run the migrations and seed the reference data:

```bash
php artisan migrate --seed
php artisan migrate:status
```

Seeding installs the role/permission catalogue, the default expense categories
and the system settings defaults. Every seeder is idempotent, so `db:seed` can
be re-run safely; settings an administrator has changed are preserved.

`database/schema.sql` holds the same structure as a standalone MySQL script for
environments that do not run Laravel's migrations:

```bash
mysql -u root -p < database/schema.sql
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

## Development sign-in

`php artisan migrate --seed` creates one account per role. **These are
development credentials and must be changed before any real deployment.**

| Email | Role |
|---|---|
| `admin@example.com` | Super Admin |
| `billing@example.com` | Billing Staff |
| `technician@example.com` | Technician |
| `accountant@example.com` | Accountant |

All four use the password `password`. Override the administrator account with
`SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` in `.env`.

Only accounts with status `active` can sign in, and the status is re-checked on
every request, so suspending someone ends their session immediately rather than
when it expires.

## Roles and permissions

Access is granted by ability (`invoices.create`, `users.delete`, …), abilities
are grouped into roles, and roles are assigned to users. Five roles ship with
the application and are seeded as system roles.

| Role | Scope |
|---|---|
| Super Admin | Unrestricted. Bypasses every ability check. |
| Administrator | Everything except redefining roles (`roles.manage`). |
| Billing Staff | Customers, subscriptions, invoicing, payments, receipts. |
| Technician | Customers and service status. Nothing financial. |
| Accountant | Payments, expenses, financial reporting. Cannot invoice. |

Enforcement happens in three places, and the UI is never one of them:

1. **Route middleware** — `permission:users.view` on the route itself.
2. **Form requests** — `authorize()` re-checks before validation runs.
3. **Policies** — `UserPolicy` / `RolePolicy` for decisions that depend on the
   record, such as refusing to delete the last super admin.

Blade's `@can` only decides what to *draw*. Hiding a link never protects a
route.

Abilities are resolved by a single `Gate::before` hook in `AppServiceProvider`,
so new permission rows work immediately without registering a gate for each.
Abilities named with a dot go through that hook; policy abilities (`view`,
`update`, `delete`) deliberately do not, because a blanket super admin grant
there would skip guards that must hold for everyone.

Custom roles can be added from **Administration → Roles & permissions**. System
roles cannot be deleted, a role still assigned to someone must be emptied
first, and the Super Admin role is not editable because it bypasses the checks
its permission list would describe.

## Customers

Customer records carry three independent status axes, because in practice they
move separately:

| Field | Meaning |
|---|---|
| `status` | Lifecycle — pending installation, active, inactive, suspended, terminated. |
| `account_status` | Billing standing — good standing, overdue, delinquent. |
| `connection_status` | Whether the physical line is up. |

Account numbers (`ACC-YYYY-NNNNN`) are generated on save and never accepted
from input. Generation derives from the current maximum id, so two concurrent
requests can pick the same number; the unique index is the real guarantee and
`CustomerService` retries on collision.

Customers are **archived**, never deleted — soft delete keeps their invoices
and payments attributable. A customer with an outstanding balance cannot be
archived. Archived records are hidden from the default list and reachable via
the Archived toggle, and archiving frees their email address for reuse.

Profile photos are stored on the `public` disk, so `php artisan storage:link`
must have been run (see Installation).

## Internet plans

Plans define what is on sale: speeds, monthly price, installation and
activation fees, and billing cycle. Five example plans are seeded on a fresh
install; nothing in the application refers to them, and they are meant to be
edited or replaced.

**Repricing a plan never rewrites history.** A subscription copies the plan's
rate into `subscriptions.monthly_rate` when it is created, and an invoice
stores its own totals. Changing `internet_plans.monthly_price` therefore
affects new signups only — existing subscribers keep the rate they signed up
on, and issued invoices are untouched. The plan form says so wherever a plan
already has subscribers.

Retiring a plan means **deactivating** it, which hides it from new signups and
leaves every existing subscription and invoice alone. A plan that has ever been
subscribed to cannot be deleted at all, because its subscriptions and invoices
name it in their history.

## Subscriptions

A subscription is a customer on a plan: it carries the agreed rate, any standing
discount, the billing day, and the connection details (type, PPPoE username,
static IP).

**The rate is copied, not referenced.** Creating a subscription copies the
plan's price into `subscriptions.monthly_rate`, where it stays editable — a
negotiated rate is stored as agreed and survives any later repricing of the
plan.

Service status moves through a state machine, not a free-form field:

```
pending   → active, cancelled
active    → suspended, expired, cancelled
suspended → active, expired, cancelled
expired   → active, cancelled
cancelled → (terminal)
```

The allowed moves live on the `SubscriptionStatus` enum, so the buttons the UI
offers and the transitions the server accepts come from one definition, and
posting an illegal move directly is refused. Status is not editable on the
subscription form for the same reason: it only moves through the status action,
so **every change writes a `service_status_logs` entry** with its reason, who
made it, and whether it was automatic.

Each change also reconciles the customer's `connection_status`: connected while
any line is active, disconnected once none are but one has been activated
before, pending otherwise.

Two abilities separate the concerns: `subscriptions.update` edits the record
(pricing, dates, connection details) and `subscriptions.manage_status` changes
service state. Billing staff hold the first, technicians the second.

## Testing

Create the test database once:

```sql
CREATE DATABASE isp_billing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
php artisan test
```

Tests run against MySQL (`isp_billing_test`, configured in `phpunit.xml`), not
SQLite. This is deliberate: SQLite has no true `DECIMAL` type, so monetary
assertions could pass under SQLite and still be wrong against the real billing
database. The development database `isp_billing` is never touched by the suite.

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application name shown in the UI. Defaults to `ISP Billing`. |
| `APP_URL` | Base URL used for generated links and assets. |
| `APP_DEBUG` | Must be `false` in production. |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Default to `database`. |
| `MAIL_*` | Mail transport. Defaults to the `log` driver in development. Password reset needs a working mailer. |
| `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` | Credentials for the seeded administrator. Change before deployment. |

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
