<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/catalogo')->assertRedirect('/login');
    }

    public function test_authenticated_home_goes_to_dashboard(): void
    {
        $this->signIn();

        $this->get('/')->assertRedirect('/painel');
    }
}
