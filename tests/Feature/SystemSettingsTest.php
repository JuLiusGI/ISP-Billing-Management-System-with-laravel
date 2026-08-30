<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    private function settings(): SettingsService
    {
        // Resolved fresh so the per-request cache does not mask a write.
        app()->forgetInstance(SettingsService::class);

        return app(SettingsService::class);
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    public function test_every_settings_group_is_seeded(): void
    {
        foreach (['company', 'billing', 'service', 'notifications'] as $group) {
            $this->assertTrue(
                SystemSetting::where('group', $group)->exists(),
                "The {$group} group should ship with defaults."
            );
        }
    }

    public function test_accessors_return_the_stored_values(): void
    {
        $settings = $this->settings();

        $this->assertSame('INV', $settings->invoicePrefix());
        $this->assertSame('OR', $settings->receiptPrefix());
        $this->assertSame(5, $settings->gracePeriodDays());
        $this->assertSame('PHP', $settings->currency());
        $this->assertFalse($settings->autoSuspendEnabled());
        $this->assertSame(15, $settings->suspendAfterDaysOverdue());
    }

    public function test_the_company_name_falls_back_when_unset(): void
    {
        SystemSetting::where('key', 'company.name')->update(['value' => null]);

        $this->assertSame(config('app.name'), $this->settings()->companyName());
    }

    public function test_a_missing_key_returns_its_default_rather_than_null(): void
    {
        SystemSetting::where('key', 'billing.grace_period_days')->delete();

        $this->assertSame(5, $this->settings()->gracePeriodDays());
    }

    // -----------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------

    public function test_company_details_can_be_saved(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'company'), [
                'company_name' => 'Samar Fiber Networks',
                'company_address' => '12 Mabini Street, Catbalogan',
                'company_phone' => '(055) 251-0000',
                'company_email' => 'billing@samarfiber.test',
                'company_website' => 'samarfiber.test',
            ])->assertRedirect();

        $company = $this->settings()->company();

        $this->assertSame('Samar Fiber Networks', $company['name']);
        $this->assertSame('billing@samarfiber.test', $company['email']);
    }

    public function test_billing_settings_can_be_saved_and_prefixes_are_upper_cased(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'billing'), [
                'default_cycle' => 'monthly',
                'grace_period_days' => 10,
                'invoice_prefix' => 'sfn',
                'receipt_prefix' => 'rec',
                'currency' => 'php',
                'currency_symbol' => '₱',
                'tax_enabled' => 1,
                'tax_rate' => '12.5',
            ])->assertRedirect();

        $settings = $this->settings();

        $this->assertSame('SFN', $settings->invoicePrefix());
        $this->assertSame('REC', $settings->receiptPrefix());
        $this->assertSame('PHP', $settings->currency());
        $this->assertSame(10, $settings->gracePeriodDays());
        $this->assertTrue($settings->taxEnabled());
        $this->assertSame('12.50', $settings->taxRate());
    }

    public function test_a_saved_invoice_prefix_is_used_by_the_next_invoice(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'billing'), [
                'default_cycle' => 'monthly', 'grace_period_days' => 5,
                'invoice_prefix' => 'ACME', 'receipt_prefix' => 'OR',
                'currency' => 'PHP', 'currency_symbol' => '₱',
                'tax_enabled' => 0, 'tax_rate' => '0',
            ]);

        // The setting is not decorative: it drives generated numbers.
        $this->assertSame('ACME', $this->settings()->invoicePrefix());
    }

    public function test_a_zero_grace_period_is_allowed(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'billing'), [
                'default_cycle' => 'monthly', 'grace_period_days' => 0,
                'invoice_prefix' => 'INV', 'receipt_prefix' => 'OR',
                'currency' => 'PHP', 'currency_symbol' => '₱',
                'tax_enabled' => 0, 'tax_rate' => '0',
            ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->settings()->gracePeriodDays());
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->put(route('settings.update', 'billing'), [
            'default_cycle' => 'fortnightly',
            'grace_period_days' => -1,
            'invoice_prefix' => 'has spaces',
            'receipt_prefix' => 'OR',
            'currency' => 'PESO',
            'currency_symbol' => '₱',
            'tax_enabled' => 0,
            'tax_rate' => '250',
        ])->assertSessionHasErrors([
            'default_cycle', 'grace_period_days', 'invoice_prefix', 'currency', 'tax_rate',
        ]);

        // Nothing partially applied.
        $this->assertSame('INV', $this->settings()->invoicePrefix());
    }

    public function test_service_settings_can_be_saved(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'service'), [
                'auto_suspend_enabled' => 1,
                'suspend_after_days_overdue' => 30,
                'default_status' => 'active',
            ])->assertRedirect();

        $settings = $this->settings();

        $this->assertTrue($settings->autoSuspendEnabled());
        $this->assertSame(30, $settings->suspendAfterDaysOverdue());
    }

    public function test_a_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->put(route('settings.update', 'company'), [
            'company_name' => 'Samar Fiber',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $path = $this->settings()->companyLogoPath();
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)->put(route('settings.update', 'company'), [
            'company_name' => 'Samar Fiber',
            'remove_logo' => 1,
        ]);

        $this->assertNull($this->settings()->companyLogoPath());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_unknown_settings_group_is_not_found(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'nonsense'), [])
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // The screen
    // -----------------------------------------------------------------

    public function test_each_tab_renders(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        foreach (['company', 'billing', 'service', 'notifications'] as $group) {
            $this->actingAs($admin)
                ->get(route('settings.index', ['group' => $group]))
                ->assertOk();
        }

        // An unrecognised tab falls back rather than erroring.
        $this->actingAs($admin)
            ->get(route('settings.index', ['group' => 'nonsense']))
            ->assertOk()
            ->assertViewHas('group', 'company');
    }

    public function test_the_configured_isp_name_is_shown_in_the_interface(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->put(route('settings.update', 'company'), [
            'company_name' => 'Samar Fiber Networks',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Samar Fiber Networks');
    }

    public function test_the_sign_in_page_also_shows_the_configured_name(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('settings.update', 'company'), ['company_name' => 'Samar Fiber Networks']);

        $this->post(route('logout'));

        $this->get(route('login'))->assertOk()->assertSee('Samar Fiber Networks');
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_only_administrators_reach_settings(): void
    {
        foreach ([Role::SUPER_ADMIN, Role::ADMINISTRATOR] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('settings.index'))->assertOk();
        }

        foreach ([Role::BILLING_STAFF, Role::TECHNICIAN, Role::ACCOUNTANT] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('settings.index'))->assertForbidden();
        }
    }

    public function test_a_role_without_the_update_ability_cannot_save(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('settings.update', 'company'), ['company_name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $this->settings()->companyName());
    }

    public function test_a_settings_change_is_written_to_the_audit_trail(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->put(route('settings.update', 'billing'), [
            'default_cycle' => 'monthly', 'grace_period_days' => 21,
            'invoice_prefix' => 'INV', 'receipt_prefix' => 'OR',
            'currency' => 'PHP', 'currency_symbol' => '₱',
            'tax_enabled' => 0, 'tax_rate' => '0',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Settings',
            'action' => 'updated',
            'user_id' => $admin->id,
        ]);
    }
}
