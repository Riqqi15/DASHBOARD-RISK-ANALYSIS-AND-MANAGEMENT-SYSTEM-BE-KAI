<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaShellTest extends TestCase
{
    public function test_login_page_receives_the_application_identity_from_inertia_middleware(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Login')
                ->where('app.name', 'KAI RAMS'));
    }
}
