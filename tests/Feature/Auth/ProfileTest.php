<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_own_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => 'Renamed',
            'last_name' => 'Person',
            'email' => $user->email,
            'phone' => '09171234567',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Renamed', $user->first_name);
        $this->assertSame('09171234567', $user->phone);
    }

    public function test_changing_the_email_clears_its_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'moved@example.com',
        ]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $other->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('brand-new-password', $user->refresh()->password));
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'guessing',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    public function test_a_guest_cannot_reach_the_profile_page(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }
}
