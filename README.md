# ISP Billing & Management System

A web-based billing and management system for an Internet Service Provider, built
with Laravel 12, Blade, Bootstrap 5 and vanilla JavaScript, targeting a local
XAMPP (Apache + MariaDB/MySQL) stack.

> **Project status: in development.** Authentication, role-based access control,
> staff user management, customers, internet plans, subscriptions, the billing
> engine, invoice management and payment processing are working on the full
> schema. The remaining modules (receipts, expenses, reports, analytics, audit
> logs and settings) are not implemented yet.

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

## Billing engine

Billing runs per month. Opening a cycle for a month creates a `billing_cycles`
row (find-or-create, so reopening is harmless); generating it issues one
invoice per billable subscription.

**Invoice arithmetic**, all in bcmath on strings — never floats:

```
subtotal       = Σ (item.quantity × item.unit_price)
discount_total = Σ item.discount_amount + invoice-level discount
charges_total  = invoice-level additional charges
taxable base   = subtotal − discount_total + charges_total   (floored at 0)
tax_total      = taxable base × rate, when tax is enabled
total_amount   = taxable base + tax_total
balance_due    = total_amount − allocated payments           (floored at 0)
```

Each item's `line_total` is `(quantity × unit_price) − its discount`, so the
lines and the header always agree. `InvoiceService::recalculate()` is the only
place this arithmetic lives and is safe to run repeatedly.

**Generation is safe to run twice.** A subscription already invoiced for the
period is skipped, and the unique index on
`(subscription_id, billing_period_start)` is the backstop if two runs race —
a duplicate is counted as skipped rather than crashing the batch. Only *active*
subscriptions that started on or before the period ends are billed.

**Dates.** The invoice date is the subscription's billing day clamped into the
month, so a line billed on the 31st still bills in February. The due date is
the invoice date plus `billing.grace_period_days`.

**The installation fee rides on the first invoice only**; later invoices are
the monthly service charge alone.

Only completed payments count toward `amount_paid` — reversing a payment
restores the balance. Cancelling an invoice is refused while payments are
applied to it; reverse those first. Financial rows are never deleted.

Configurable values (invoice prefix, grace period, tax on/off and rate) are
read through `SettingsService` from `system_settings`, never hard-coded. The
screen for editing them arrives with the settings phase.

## Invoice management

Invoices reach the system two ways: generated in bulk by a billing cycle, or
created by hand from **Billing → Create Invoice** (or from a customer's
profile, which preselects them). The manual form takes any number of lines,
each with its own type, quantity, unit price and discount, plus an
invoice-level discount and additional charges. It shows a running total as you
type, but that is a preview only — the server recalculates every figure on
save, tax included.

**An invoice becomes immutable the moment money is applied to it.** Before any
payment it is a document still being prepared and can be freely amended;
afterwards a payment and a receipt already refer to its figures, so rewriting
them would falsify both. The same rule blocks cancellation: reverse the
payments first. Cancelled invoices keep their row with a zero balance and a
recorded reason — financial records are never deleted.

The customer on an invoice is fixed after issue, since moving one would falsify
two customers' histories.

Listing supports search by invoice number, account number or customer name, and
filters on status, date range and amount. Two saved views span several statuses:
**Unpaid** (unpaid, partially paid and overdue) and **Overdue**. The totals
above the table are summed over the whole filtered set, not the visible page.

Every invoice has a printer-friendly version at `/invoices/{id}/print`, headed
with the company details from system settings and driven by the browser's own
print dialog.

## Payments

A **payment** is money received. An **allocation** is that money being applied
to a particular invoice. Keeping the two apart is what makes the awkward cases
ordinary:

- One payment can settle several invoices.
- Several payments can settle one invoice.
- An overpayment sits as **unapplied credit** on the payment rather than being
  forced onto an invoice that does not owe it. The credit can be applied later
  from the payment's own page, and shows on the customer profile.

Recording a payment opens the customer's outstanding invoices in an allocation
grid, with an "apply oldest first" button that does what a cashier taking a
lump sum would do by hand. Applying more than was received is refused, as is
applying more than an invoice owes, applying to another customer's invoice, and
applying to a cancelled invoice. If any line of an allocation fails the whole
payment rolls back — a half-applied payment is never left behind.

Invoices are locked (`SELECT … FOR UPDATE`) while their balance is read and
changed, so two cashiers taking money for the same invoice at once cannot both
allocate against the same balance.

**Reversal, not deletion.** A bounced cheque or a mis-keyed entry is reversed:
the payment row and its allocations stay exactly where they are, the status is
what stops the money counting, and the invoices it touched are recalculated so
their balances come back. Reversing needs `payments.reverse`, a separate
ability from `payments.create` — a cashier records money; undoing it is an
accounting correction.

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
| `APP_TIMEZONE` | The ISP's own clock. Defaults to `Asia/Manila`. Invoice dates, billing days and "not in the future" checks all read it, so a server running in UTC would reject same-day payments for part of the day. |
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
