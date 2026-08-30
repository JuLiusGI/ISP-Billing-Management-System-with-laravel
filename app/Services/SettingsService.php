<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

/**
 * Reads configurable values out of system_settings.
 *
 * Registered as a singleton so a request that asks for a dozen settings runs
 * one query rather than a dozen. MASTER_SPEC §28 requires these values not be
 * hard-coded, so everything that varies by installation is read through here.
 * The administration screen for editing them arrives with the settings phase.
 */
class SettingsService
{
    /** @var Collection<string, SystemSetting>|null */
    private ?Collection $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->get($key);

        if (! $setting) {
            return $default;
        }

        return $setting->typed_value ?? $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /** Money-adjacent settings are returned as strings so bcmath can use them. */
    public function decimal(string $key, string $default = '0'): string
    {
        $value = $this->get($key, $default);

        return $value === null ? $default : (string) $value;
    }

    public function set(string $key, mixed $value): void
    {
        $setting = $this->all()->get($key);

        if (! $setting) {
            return;
        }

        $setting->update(['value' => $setting->type->serialize($value)]);

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    /** @return Collection<string, SystemSetting> */
    private function all(): Collection
    {
        return $this->cache ??= SystemSetting::all()->keyBy('key');
    }

    // -----------------------------------------------------------------
    // Named accessors for the billing values, so callers do not repeat
    // string keys and a rename happens in one place.
    // -----------------------------------------------------------------

    public function invoicePrefix(): string
    {
        return $this->string('billing.invoice_prefix', 'INV');
    }

    public function receiptPrefix(): string
    {
        return $this->string('billing.receipt_prefix', 'OR');
    }

    /** Days a customer has to pay after an invoice is issued. */
    public function gracePeriodDays(): int
    {
        return max(0, $this->integer('billing.grace_period_days', 5));
    }

    public function taxEnabled(): bool
    {
        return $this->boolean('billing.tax_enabled', false);
    }

    /** Percentage, as a decimal string. */
    public function taxRate(): string
    {
        return $this->decimal('billing.tax_rate', '0');
    }

    public function defaultBillingCycle(): string
    {
        return $this->string('billing.default_cycle', 'monthly');
    }

    public function currency(): string
    {
        return $this->string('billing.currency', 'PHP');
    }

    public function currencySymbol(): string
    {
        return $this->string('billing.currency_symbol', '₱');
    }

    // -----------------------------------------------------------------
    // Company identity
    // -----------------------------------------------------------------

    /**
     * The ISP's own details, as printed on invoices and receipts and shown in
     * the interface.
     *
     * One method rather than five call sites reading five keys: the header of a
     * receipt and the header of an invoice must not be able to disagree.
     *
     * @return array{name: string, address: string, phone: string, email: string, website: string, logo: ?string}
     */
    public function company(): array
    {
        return [
            'name' => $this->companyName(),
            'address' => $this->string('company.address'),
            'phone' => $this->string('company.phone'),
            'email' => $this->string('company.email'),
            'website' => $this->string('company.website'),
            'logo' => $this->companyLogoPath(),
        ];
    }

    /** Falls back to the framework's app name so a fresh install is not blank. */
    public function companyName(): string
    {
        return $this->string('company.name') ?: (string) config('app.name');
    }

    public function companyLogoPath(): ?string
    {
        return $this->string('company.logo_path') ?: null;
    }

    // -----------------------------------------------------------------
    // Service
    // -----------------------------------------------------------------

    /**
     * Whether the scheduler may suspend a line for non-payment on its own.
     * Off by default: cutting a customer off without a human deciding to is
     * not something an installation should start doing unasked.
     */
    public function autoSuspendEnabled(): bool
    {
        return $this->boolean('service.auto_suspend_enabled', false);
    }

    /** How far past due an invoice must be before automatic suspension. */
    public function suspendAfterDaysOverdue(): int
    {
        return max(1, $this->integer('service.suspend_after_days_overdue', 15));
    }

    public function defaultServiceStatus(): string
    {
        return $this->string('service.default_status', 'pending');
    }

    // -----------------------------------------------------------------
    // Notifications
    // -----------------------------------------------------------------

    /** The master switch. With this off, no notification email is sent at all. */
    public function emailNotificationsEnabled(): bool
    {
        return $this->boolean('notifications.email_enabled', false);
    }

    public function notifiesOn(string $event): bool
    {
        return $this->boolean("notifications.on_{$event}", false);
    }
}
