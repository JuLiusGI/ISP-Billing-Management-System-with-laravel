<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The interface itself: error pages, keyboard affordances and the chrome that
 * every screen shares.
 */
class InterfaceTest extends TestCase
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

    // -----------------------------------------------------------------
    // Error pages
    // -----------------------------------------------------------------

    public function test_every_error_page_has_a_view(): void
    {
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $this->assertTrue(
                view()->exists("errors.{$code}"),
                "MASTER_SPEC §35 asks for a friendly {$code} page."
            );
        }
    }

    public function test_a_forbidden_request_renders_the_custom_page(): void
    {
        // A technician cannot reach user administration.
        $response = $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('users.index'));

        $response->assertForbidden();
        $response->assertSee('You do not have access to this');
        $response->assertSee('ask an administrator');
    }

    public function test_a_missing_page_renders_the_custom_page(): void
    {
        $response = $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('That page does not exist');
    }

    public function test_a_missing_record_renders_the_custom_page(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('customers.show', 999999))
            ->assertNotFound()
            ->assertSee('That page does not exist');
    }

    public function test_the_error_pages_do_not_depend_on_the_database(): void
    {
        // They must render when the thing they are reporting has broken, so
        // they read config rather than the ISP name in system settings.
        foreach (['403', '404', '500'] as $code) {
            $rendered = view("errors.{$code}")->render();

            $this->assertStringContainsString((string) config('app.name'), $rendered);
        }
    }

    public function test_an_error_page_offers_a_way_back(): void
    {
        $rendered = view('errors.404')->render();

        $this->assertStringContainsString('Back to the dashboard', $rendered);
    }

    public function test_the_session_expiry_page_offers_a_way_to_sign_in_again(): void
    {
        $rendered = view('errors.419')->render();

        $this->assertStringContainsString('Sign in again', $rendered);
        $this->assertStringContainsString(route('login'), $rendered);
    }

    // -----------------------------------------------------------------
    // Keyboard and screen-reader affordances
    // -----------------------------------------------------------------

    public function test_the_application_shell_offers_a_skip_link(): void
    {
        $response = $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('dashboard'));

        $response->assertSee('Skip to main content');
        // The link needs a target to skip to.
        $response->assertSee('id="main-content"', false);
    }

    public function test_the_main_region_is_a_landmark(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('dashboard'))
            ->assertSee('<main class="app-content" id="main-content"', false);
    }

    public function test_a_success_message_is_announced_politely_and_an_error_assertively(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $success = $this->actingAs($admin)
            ->withSession(['success' => 'Saved.'])
            ->get(route('dashboard'));

        $success->assertSee('aria-live="polite"', false);
        $success->assertSee('role="status"', false);

        $error = $this->actingAs($admin)
            ->withSession(['error' => 'That did not work.'])
            ->get(route('dashboard'));

        $error->assertSee('aria-live="assertive"', false);
    }

    public function test_an_error_message_does_not_auto_dismiss(): void
    {
        // A failure that vanishes after a few seconds is a failure someone
        // misses.
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->withSession(['error' => 'Something failed.'])
            ->get(route('dashboard'))
            ->assertSee('data-persist="true"', false);
    }

    public function test_a_success_message_does_auto_dismiss(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->withSession(['success' => 'Saved.'])
            ->get(route('dashboard'))
            ->assertSee('data-persist="false"', false);
    }

    // -----------------------------------------------------------------
    // Double-submit protection
    // -----------------------------------------------------------------

    public function test_named_submit_buttons_are_not_disabled_on_submit(): void
    {
        /*
         * The busy state must not set `disabled`: a disabled submit button is
         * dropped from the request, and the service status buttons post which
         * status was chosen through their own name and value.
         */
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("form.dataset.submitting = 'true'", $layout);
        $this->assertStringNotContainsString('button.disabled = true', $layout);
    }

    public function test_the_status_buttons_still_carry_their_value(): void
    {
        $view = file_get_contents(resource_path('views/subscriptions/show.blade.php'));

        $this->assertStringContainsString('name="status"', $view);
    }

    // -----------------------------------------------------------------
    // Shared chrome
    // -----------------------------------------------------------------

    public function test_every_main_screen_renders_for_an_administrator(): void
    {
        $admin = $this->userWithRole(Role::SUPER_ADMIN);

        foreach ([
            'dashboard', 'customers.index', 'plans.index', 'subscriptions.index',
            'services.index', 'services.history', 'billing.index', 'invoices.index',
            'payments.index', 'receipts.index', 'expenses.index',
            'expense-categories.index', 'reports.index', 'users.index',
            'roles.index', 'audit-logs.index', 'settings.index', 'profile.edit',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_the_guest_screens_render(): void
    {
        foreach (['login', 'password.request'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
