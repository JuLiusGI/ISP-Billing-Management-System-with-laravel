<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_signing_in_records_when_and_from_where(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->assertNull($user->last_login_at);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_suspended_account_cannot_sign_in_even_with_the_right_password(): void
    {
        $user = User::factory()->suspended()->create(['password' => 'password']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_suspending_a_signed_in_user_ends_their_session_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => UserStatus::Suspended]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sign_in_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        // The sixth attempt is refused by the throttle even though the
        // credentials are now correct.
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_reset_link_is_sent_for_a_known_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_response_and_no_mail(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');

        // Saying "no such account" here would let anyone enumerate staff.
        Notification::assertNothingSent();
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-1234',
            'password_confirmation' => 'new-password-1234',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-1234', $user->refresh()->password));
    }

    public function test_a_password_reset_is_refused_with_a_bad_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('password.store'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password-1234',
            'password_confirmation' => 'new-password-1234',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
