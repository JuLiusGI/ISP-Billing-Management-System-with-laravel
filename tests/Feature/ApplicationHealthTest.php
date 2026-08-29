<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationHealthTest extends TestCase
{
    public function test_the_landing_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'), false);
    }

    public function test_the_landing_page_loads_the_compiled_bootstrap_assets(): void
    {
        $response = $this->get('/');

        // Guards against the Vite entry points drifting out of sync with the
        // build manifest, which would leave the UI unstyled.
        $response->assertSee('build/assets/app-', false);
    }

    public function test_the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
