<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_has_floating_navigation_without_mobile_brand_header(): void
    {
        $this->signIn();

        $this->get('/painel')
            ->assertOk()
            ->assertSee('bottom-nav__inner', false)
            ->assertSee('bottom-nav__item is-active', false)
            ->assertSee('data-app-header class="hidden"', false);
    }

    public function test_shared_form_dialog_has_separate_scrollable_body_and_footer(): void
    {
        $this->signIn();

        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('id="form-dialog-content"', false)
            ->assertSee('id="form-dialog-actions"', false);
    }

    public function test_book_form_is_marked_as_a_multistep_form(): void
    {
        $this->signIn();

        $this->get('/catalogo/novo')
            ->assertOk()
            ->assertSee('data-multistep-form', false)
            ->assertSee('data-step="Identificação"', false)
            ->assertSee('data-step="Publicação"', false)
            ->assertSee('data-step="Classificação"', false)
            ->assertSee('data-confirm-title="Confirmar cadastro"', false)
            ->assertSee('data-isbn', false)
            ->assertSee('data-form-actions', false);
    }
}
