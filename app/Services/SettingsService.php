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
}
