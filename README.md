# ISP Billing & Management System

A web-based billing and management system for an Internet Service Provider, built
with Laravel 12, Blade, Bootstrap 5 and vanilla JavaScript, targeting a local
XAMPP (Apache + MariaDB/MySQL) stack.

> **Project status: in development.** Authentication, role-based access control,
> staff user management, customers, internet plans, subscriptions, service
> management, the billing engine, invoice management, payment processing,
> receipts, expenses, reports, the analytics dashboard and audit logging are
> working on the full schema, along with system settings, notifications,
> automated billing and the interface refinement pass. The final security
> review and documentation passes remain.

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

# The company logo on receipts is served from the public disk.
# Customer photos are not - see Security below.
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

## Service management

**Internet Services → Active / Suspended / Expired Services** is the operational
view of the same subscriptions. Where *Customer Subscriptions* answers "who is
on what plan, at what price", the service board answers "which lines are up,
which are cut, and why". Counts across the top make each state one click away,
and the row actions offer only the transitions the state machine allows.

**Internet Services → Service History** is the status-change audit trail across
every customer, filterable by target status, date range, customer, and — the
question asked most often when a disconnection is disputed — whether a person or
the scheduler made the change.

### The provisioning seam

Enabling or cutting a line is a *side effect* of a status change, not part of
it, so it sits behind the `App\Contracts\ServiceProvisioner` interface:

```php
activate(Subscription $s)    // bring the line up
suspend(Subscription $s)     // take it down, keep the account provisioned
terminate(Subscription $s)   // remove it from the network
isEnabled(): bool
```

`NullServiceProvisioner` is bound by default. It is a working implementation
rather than a stub: service state is tracked in the database, and every action
that *would* have gone to the network is written to the log, so the intended
sequence can be checked against a real device before a driver is trusted with
it. The service board says plainly that nothing is being pushed to a router.

Two deliberate properties, both covered by tests:

- Provisioning runs **after** the transaction commits. Holding row locks open
  across a device call is how a slow router becomes a database problem.
- A provisioning failure is logged, never rethrown. The status change is already
  durable and drives billing; an unreachable router must not silently undo it.

Adding MikroTik or RADIUS support means writing one class and changing one
binding in `AppServiceProvider`. No other code moves.

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

## Receipts

A receipt acknowledges money received, so exactly one belongs to each payment —
the unique index on `payment_id` enforces it. Receipts are issued explicitly
from the payment page rather than automatically, and only for a **completed**
payment: a reversed one is money the ISP no longer holds, so there is nothing
to acknowledge.

Issuing is idempotent at the service level. The button disappears once a
receipt exists and the route refuses a second attempt, but if two requests slip
past that check together the second returns the receipt the first created
instead of failing.

Receipt numbers follow `{billing.receipt_prefix}-YYYY-NNNNNN`, defaulting to
`OR`.

The receipt carries everything MASTER_SPEC §14 asks for: ISP name and address,
customer name and account number, receipt number, payment reference and date,
the invoices it was applied to with the balance left on each, amount paid,
payment method, and both who received and who issued it. Anything not applied
to an invoice is shown as credit held on the account.

A payment reversed *after* its receipt was issued keeps the receipt — it was
issued, and the record stands — but the document is stamped **VOID** on screen
and in print.

The on-screen and printed versions render the same partial, so the two can
never show different figures.

## Expenses

**Finance → Expenses** records what the ISP spends. Every entry carries a
category, an amount, a date, how it was paid, and optionally a vendor. References
run `EXP-YYYY-NNNNNN`, are generated on save and never accepted from input.

The listing filters by category, payment method, free text (reference,
description, vendor) and a date range. The summary above it — a total plus a
per-category breakdown with percentage shares — is computed from the **whole
filtered set**, not the page on screen, so narrowing the dates gives a real
period figure rather than a page subtotal.

Expenses are archived rather than deleted, and archived entries are excluded
from every total.

### Categories

**Finance → Expense Categories** maintains the list. Codes are derived from the
name (`Tower Rental` → `TOWER_RENTAL`) rather than typed.

A category that has expenses filed under it **cannot be deleted** — it is
retired instead, which removes it from new expenses while leaving historical
records with a meaningful label. Only an unused category can be deleted
outright. An expense already filed under a retired category stays editable,
because being unable to correct a typo on an old record would be worse than the
tidiness the restriction buys.

Both the expense module and its categories run on the `expenses.*` abilities, so
accountants maintain their own chart of accounts without needing administrator
rights. Billing staff and technicians have no access.

Module-level totals live here; the formal Expense Report belongs to the reports
module.

## Dashboard

The dashboard is assembled **per role**, and not just visually: the controller
asks for a panel's data only when the signed-in user holds the ability behind
it, so a technician's dashboard issues no revenue queries at all rather than
fetching figures and hiding them in the view.

| Panel | Ability |
|---|---|
| Customer statistics, sign-up trend, recent customers | `customers.view` |
| Service statistics, services-by-state chart | `subscriptions.view` |
| Billing statistics, invoice-status chart, recent invoices, overdue alert | `invoices.view` |
| Recent payments | `payments.view` |
| Revenue / expenses / net, revenue trend chart | `reports.financial` |

A user holding none of these gets an explicit empty state rather than a blank
page.

Charts are Chart.js, imported through Vite with only the controllers actually
used registered, so the bundle carries the bar, line and doughnut pieces rather
than the whole library. Data reaches the canvas as a `data-chart` attribute
rendered by Blade from real query results.

The twelve-month trends **fill empty months with zero** rather than skipping
them. A gap in a time series should read as "nothing happened" instead of
compressing the axis and implying the months were adjacent.

Money figures come back from `SUM()` as `0` when there are no rows, which reads
differently from every other amount on the page, so both the dashboard and the
report services normalise sums through a `money()` helper using bcmath rather
than a float cast.

## Interface

Bootstrap 5 themed from `resources/css/app.scss`, dark navy with a red accent.
Brand colours are Sass variables merged into Bootstrap's `$theme-colors`, so
`bg-navy` and `text-navy` are generated rather than hand-written. No hex values
belong in a Blade view.

### Error pages

Friendly pages for 403, 404, 419, 429, 500 and 503. They are **deliberately
dependency-free** — no sidebar, no JavaScript, and the ISP name read from
config rather than system settings. An error page has to render when the thing
it is reporting has already broken, so a 500 caused by an unreachable database
must not query the database to draw itself.

Each says what happened in plain terms and offers a way out; the 419 page
offers to sign in again, since that is the only useful next step.

### Accessibility

- **Skip link** to `#main-content`. The sidebar puts dozens of links before the
  content on every page, so without it reaching the page by keyboard means
  tabbing past all of them, every time.
- **`:focus-visible` ring** that survives the Bootstrap reset, switching to
  white inside the navy sidebar where the blue ring has too little contrast.
- **`prefers-reduced-motion`** honoured; the sidebar slide is decoration.
- **Toasts are announced by severity** — an error is `role="alert"` /
  `aria-live="assertive"` and stays until dismissed, a success is
  `role="status"` / `aria-live="polite"` and auto-dismisses. A failure that
  vanishes after five seconds is a failure someone misses.
- Stat tiles avoid `text-warning`, which is about 1.6:1 against white. Amber
  stays on badges, where it sits behind dark text.

### Loading and double-submit

Submitting a non-GET form marks its submit buttons `aria-busy` and blocks a
second submit. It sets a flag on the *form* rather than disabling the button,
because a disabled submit button is dropped from the request — and some of ours
carry the value the action depends on, such as the service status buttons that
post which status was chosen. A test asserts the busy state never sets
`disabled`.

GET forms are exempt: filters return fast, and a button left spinning after a
back-navigation would look broken.

### Tables and mobile

Long listings use a sticky header inside their scroll container. Below the
`sm` breakpoint, table padding tightens to reclaim horizontal room, and the
sidebar becomes an off-canvas panel that closes on Escape or a backdrop tap.

## Automated billing

Three commands, all safe to run repeatedly:

| Command | Does |
|---|---|
| `billing:generate-invoices` | Issues the month's invoices. `--month=YYYY-MM`, `--dry-run` |
| `billing:update-overdue` | Marks outstanding invoices past their due date |
| `billing:process-service-status` | Expires lapsed services; suspends overdue ones. `--dry-run` |

They register in `routes/console.php` and need one cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, point Task Scheduler at `php artisan schedule:run` every minute.

**The order within the day is deliberate.** Invoices at 01:00, the overdue
sweep at 02:00, service status at 03:00 — so a line is never suspended over an
invoice that was about to be marked overdue in the same run. A test asserts
those times rather than trusting the comment.

Each job carries `withoutOverlapping()`, so a slow run cannot be started on top
of itself. That is belt and braces: repeat-safety is a property of the commands
themselves.

- Generating twice issues nothing the second time — the subscription is already
  invoiced for the period, backed by a unique index on
  `(subscription_id, billing_period_start)`, so two runs racing cannot both win.
- The overdue sweep no longer matches an invoice it has already marked.
- Suspension only considers active services, so a second run finds nothing.

**Automatic suspension is off by default and the command says so** rather than
doing nothing silently. Cutting customers off is not something an installation
should start doing because a scheduler was switched on. Both the switch and the
days-overdue threshold live in **Administration → System Settings → Service**.
Expiry is not configurable: an expiry date that has passed is a fact, not a
policy decision.

Every automated change goes through `SubscriptionService::changeStatus`, so the
state machine, service status log, audit trail, customer connection status and
provisioning hook all still apply. Scheduler-driven changes are flagged
`is_automatic` and carry no `user_id`, which is what makes the Service History
filter for "the scheduler" versus "a person" meaningful.

Runs write a summary to the application log as well as the console, since a
scheduled run has nobody watching its output. A partial failure returns a
non-zero exit code so it surfaces rather than passing quietly.

## System settings

**Administration → System Settings** holds the values that vary by
installation. Nothing here is compiled in: the ISP's own name, address and logo
drive the interface chrome, the sign-in page and the headers of printed invoices
and receipts, all from `SettingsService::company()` so the two documents cannot
disagree about who issued them.

| Group | Covers |
|---|---|
| Company | ISP name, address, phone, email, website, logo |
| Billing | Default cycle, grace period, invoice and receipt prefixes, currency and symbol, tax on/off and rate |
| Service | Automatic suspension on/off, days overdue before suspension, default service status |
| Notifications | Master switch plus one switch per event |

Each group saves on its own form. A single page saving company details, billing
rules, suspension policy and notification switches together would let one
careless save change all four.

Reading is `settings.view`, saving is `settings.update`, so an auditor can be
shown the configuration without being able to change it. Every change goes
through the audit trail like any other model write.

`billing.tax_rate` is stored and read as a **string**, not a float. A decimal
setting exists because the value is money-adjacent, and a float round-trip both
loses precision and turns a stored `12.50` back into `12.5`.

## Notifications

Five customer notifications: invoice issued, payment received, invoice overdue,
service suspended, service reactivated. Each sends by mail and is also stored in
the `notifications` table.

Whether anything goes out is decided in one place, `CustomerNotifier`, by three
questions: is the master switch on, is this event's switch on, and does the
customer have an email address at all. Putting that at each dispatch site would
eventually mean one of the three being forgotten.

- **Sending never blocks the work that triggered it.** Notifications go out
  after the transaction commits, and a failure is logged rather than thrown — a
  mail server being down must not cost the ISP the record of money it has taken.
- **Only meaningful transitions are announced.** Suspension and reactivation
  are; a move between pending, expired and cancelled is administrative, and
  mailing about it trains people to ignore the ones that matter.
- **Reactivation means coming back from suspension**, so a first activation is
  not announced as "your service is back on".

Mail transport is configured with the `MAIL_*` environment variables, never in
the database. The default is Laravel's `log` driver, so in development the
messages land in `storage/logs/laravel.log`.

> Notifications implement `ShouldQueue`. With `QUEUE_CONNECTION=database`, a
> worker must be running for them to leave the application:
>
> ```bash
> php artisan queue:work
> ```

Notifications are **off by default** on a fresh install. An installation should
not start emailing a customer base the moment it is seeded.

## Audit logs

**Administration → Audit Logs** records who did what, from where. Filterable by
module, action, user, date range and free text; each entry opens to a
field-by-field before/after.

Model changes are captured by the `Auditable` trait rather than by remembering
to log at each call site. It is applied to the nine models whose history
matters — customers, subscriptions, plans, invoices, payments, expenses, users,
roles and settings — and deliberately not to everything, since a log of cache
rows and pivot writes would bury the entries someone actually needs.

Authentication has no model write behind it, so it hangs off the framework's
own events: sign-in, sign-out, **failed attempts** and throttling. Failures
matter more than successes here — a run of them against one address is the
shape of an attack, and is invisible if only successes are recorded. The
address tried is stored; the password never is.

Four properties worth knowing:

- **Update entries carry only what changed.** Storing the whole row twice would
  make a one-field correction indistinguishable from a rewrite of the record.
- **The trail rolls back with what it describes.** Writes run inside the
  caller's transaction, so an entry cannot survive a change that was undone.
- **A failure to write the trail is logged, never thrown.** Losing an audit row
  is bad; refusing a customer's payment because the audit table is unavailable
  is worse.
- **Secrets are redacted centrally** — passwords and tokens are stripped in
  `AuditLogger` whether or not the model remembered to exclude them.

The trail is read-only. There is no create, update or delete route, and a test
asserts none exists: an audit log editable from the interface it audits is not
evidence of anything.

Service status changes appear in two places on purpose — `service_status_logs`
is the domain record of what happened to the line, while the audit trail is
where someone auditing an account looks first.

## Reports

Nine reports, each filterable by date range and exportable to CSV.

| Report | Answers | Ability |
|---|---|---|
| Financial Summary | Gross revenue less expenses, month by month | `reports.financial` |
| Revenue | Money received over time and by method | `reports.financial` |
| Expense | Operating costs by category and period | `expenses.view` |
| Payment | Every payment taken in the period | `payments.view` |
| Billing | Invoices issued and where they ended up | `invoices.view` |
| Outstanding | Receivables aged by how long they are owed | `invoices.view` |
| Overdue | Invoices past due, by age | `invoices.view` |
| Customer | Base by status, type and growth | `reports.operational` |
| Service | Services by state and plan, plus recurring revenue | `reports.operational` |

Each report is gated on the ability covering the **data it exposes**, not on one
blanket reports ability, so a role only sees reports over records it could
already read. Billing staff get receivables and payments; accountants get
revenue, spend and the summary; technicians get customers and services. The hub
lists only what the signed-in user may open, so it never offers a link that
would 403.

Two conventions hold throughout:

- **Revenue means completed payments.** Reversed and cancelled payments stay in
  the table for the audit trail and are never counted as money received.
- **Cancelled and void invoices carry no balance** and are excluded from
  receivables and ageing, though the billing report still counts them as
  documents issued.

Outstanding and Overdue are deliberately **not** date-filtered. Ageing is a
statement about today, and letting someone pick a range would produce a number
that looks like a receivables figure but is not one.

Every total is a `SUM()` and every grouping a `GROUP BY`. Reporting is the one
place where adding rows up in PHP looks harmless and then falls over once a real
customer base exists.

A reversed date range is swapped rather than rejected, and an unparseable date
falls back to the default six-month window: a read-only report should show
something rather than an error page.

### Export

`?export=csv` on any report streams a UTF-8 CSV (with a BOM, so Excel reads the
peso sign correctly). Streamed rather than buffered, since the reason to export
is usually that the range is large. CSV is the only format on purpose — it opens
in every spreadsheet an ISP office already has.

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

### Financial integrity

`FinancialIntegrityTest` checks the invariants that span modules, which no
per-module suite would notice breaking:

- **The books balance.** For every invoice, the stored `amount_paid` equals the
  sum of its completed allocations and `balance_due` is the remainder floored at
  zero. Asserted after each step of a full lifecycle — bill, part-pay, settle,
  spread one payment across two invoices, reverse it, pay again, receipt.
- **The dashboard and the reports agree.** Both compute receivables, overdue and
  revenue by different routes; if they disagree, one of them is wrong.
- **The ageing buckets sum to their own total.** An ageing report whose buckets
  do not add up is worthless.
- **Reversed money disappears from every view of it** — revenue, dashboard,
  customer balance and receivables alike.

### Writing invoice fixtures

Use `Invoice::factory()->ofAmount(1500)` rather than setting `total_amount`
directly. Overriding the total alone leaves `subtotal` at the factory's random
default, and the first recalculation corrects the total back to that subtotal —
which reads as the application losing money that was never there, and makes any
test that later records a payment non-deterministic.

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

## Security

The measures below are the ones that are easy to get wrong quietly, so they are
written down rather than left to be rediscovered.

**Authorisation is checked twice.** Routes carry a `permission:` middleware and
controller actions call the policy. The middleware answers "may this role reach
this screen at all"; the policy answers "may this user act on this particular
record". Neither is redundant: a route list alone would let anyone holding
`customers.view` reach any customer, and a policy alone would leave a route open
if an action ever forgot its check. Three actions are deliberately ungated —
sign-out and the two password-reset steps — because a user who cannot sign in
cannot pass a gate.

**Customer photos are private.** They are stored on the `local` disk and served
by `GET /customers/{customer}/photo`, which applies the same `view` policy as
the rest of the record and sends `Cache-Control: private` so that no shared
cache retains one. They are deliberately not on the public disk: anything there
is served straight off the filesystem with no session behind it, and a random
filename is obscurity rather than access control. Photos written before this
change are still read from the public disk so existing records keep working.
To move them across:

```bash
mkdir -p storage/app/private/customers/photos
mv storage/app/public/customers/photos/* storage/app/private/customers/photos/
```

**Reporting columns are on an allowlist.** `FinancialReportService::overTime()`
interpolates its date and amount column names into `DATE_FORMAT()` and `SUM()`,
which no query binding can parameterise. Both names are checked against the
columns the method actually supports, and an unknown one throws before the
query is built. Every call site passes a literal today; the check is there so
that stays safe if one ever starts passing a report filter through.

**Elsewhere the framework's defaults are relied on and left intact.** CSRF
protection covers every non-`GET` form, with no exclusions in
`bootstrap/app.php`. Blade escaping is untouched — there is no `{!! !!}` in any
view. Every model declares `$fillable`, so no request payload can reach a column
that was not asked for, and the few `forceFill()` calls write computed values
rather than input. Passwords use the `hashed` cast with `Password::defaults()`.

**Secrets stay out of the repository.** `.env` is untracked and `.env.example`
ships an empty `APP_KEY`. Session cookie flags are environment-driven: set
`SESSION_SECURE_COOKIE=true` wherever the app is served over HTTPS.

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

Network integration has its seam already in place — see **Service management**
above. Automatic suspension has the pieces it needs too: `changeStatus()` takes
an `automatic` flag that the history view surfaces separately from human
actions, and the suspension threshold and on/off switch already exist in system
settings. The scheduled command that drives it is not written yet.
