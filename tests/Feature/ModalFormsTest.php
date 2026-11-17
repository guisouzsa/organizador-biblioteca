<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModalFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_exposes_shared_form_dialog(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="form-dialog"', false)
            ->assertSee('data-form-dialog-close', false);
    }

    public function test_required_book_fields_are_marked_and_validated_on_server(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('books.create'))
            ->assertOk()
            ->assertSee('indica campo obrigatório')
            ->assertSee('name="title"', false)
            ->assertSee('required', false);

        $this->from(route('books.create'))->actingAs($user)->post(route('books.store'), [])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors(['title']);
    }

    public function test_confirmation_dialog_uses_shared_header_body_and_footer(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="ui-dialog"', false)
            ->assertSee('class="modal__head"', false)
            ->assertSee('class="modal__body"', false)
            ->assertSee('class="modal__foot"', false);
    }

    public function test_catalog_search_remains_a_get_form(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('books.index'))
            ->assertOk()
            ->assertSee('method="GET"', false)
            ->assertSee('role="search"', false);
    }
}
