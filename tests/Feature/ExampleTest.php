<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertOk()->assertSee('Entrar');
        $this->get('/catalogo')->assertRedirect('/login');
    }

    public function test_authenticated_home_shows_landing_with_panel_link(): void
    {
        $this->signIn();

        $this->get('/')->assertOk()->assertSee('Abrir painel');
    }
}
