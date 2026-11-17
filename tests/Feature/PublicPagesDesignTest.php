<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_is_short_and_links_to_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Seu acervo organizado.')
            ->assertSee('Entrar no sistema')
            ->assertSee(route('login'), false)
            ->assertSee('decor/logo.png', false)
            ->assertSee('paw-trail', false)
            ->assertDontSee('Perguntas frequentes')
            ->assertDontSee('gato-espiando');
    }

    public function test_login_keeps_authentication_actions_and_password_visibility_control(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Acesse sua conta')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('Manter conectado')
            ->assertSee('Esqueci minha senha');
    }

    public function test_reset_form_has_visibility_controls_for_both_password_fields(): void
    {
        $response = $this->get('/redefinir-senha/token-de-teste?email=gui%40example.com');

        $response->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-password-toggle'));
    }
}
