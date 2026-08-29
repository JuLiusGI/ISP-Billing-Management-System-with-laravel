<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationHealthTest extends TestCase
{
    public function test_the_root_url_sends_visitors_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_a_guest_reaching_the_dashboard_is_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_the_login_page_renders(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(config('app.name'), false);
    }

    public function test_the_login_page_loads_the_compiled_bootstrap_assets(): void
    {
        // Guards against the Vite entry points drifting out of sync with the
        // build manifest, which would leave the UI unstyled.
        $this->get(route('login'))->assertSee('build/assets/app-', false);
    }

    public function test_the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
