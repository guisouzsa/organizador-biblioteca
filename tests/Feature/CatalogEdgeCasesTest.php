<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_with_only_a_title_renders_in_list_and_details(): void
    {
        $book = Book::create(['title' => 'Somente título']);

        $this->get('/catalogo')->assertOk()->assertSee('Somente título')->assertSee('Sem capa', false);
        $this->get("/catalogo/{$book->id}")->assertOk()->assertSee('Somente título')->assertSee('Nenhuma fonte registrada');
        $this->get("/catalogo/{$book->id}/editar")->assertOk();
    }

    public function test_unreachable_cover_url_still_shows_accessible_alt_text(): void
    {
        $book = Book::factory()->create(['title' => 'Com capa quebrada', 'cover_url' => 'https://exemplo.invalid/capa.jpg']);

        $this->get('/catalogo')->assertSee('alt="Capa de Com capa quebrada"', false);
    }

    public function test_data_is_persisted_and_recovered_intact(): void
    {
        $category = Category::findOrCreateByName('Sociologia');

        $this->post('/catalogo', [
            'title' => 'Sociologia em Movimento', 'subtitle' => 'Ensino Médio', 'authors' => 'Ana Lima; Bruno Souza',
            'publisher' => 'Moderna', 'isbn' => '978-0-306-40615-7', 'edition' => '3ª', 'publication_year' => 2023,
            'pages' => 416, 'description' => 'Descrição longa.', 'cover_url' => 'https://exemplo.com/c.jpg',
            'language' => 'pt-BR', 'format' => 'Brochura', 'categories' => [$category->id], 'collection_name' => 'Coleção Z',
        ])->assertRedirect();

        $book = Book::with(['categories', 'collection'])->where('isbn13', '9780306406157')->firstOrFail();

        $this->assertSame('Sociologia em Movimento', $book->title);
        $this->assertSame('Ensino Médio', $book->subtitle);
        $this->assertSame(['Ana Lima', 'Bruno Souza'], $book->authorsList());
        $this->assertSame('Moderna', $book->publisher);
        $this->assertSame('0306406152', $book->isbn10);
        $this->assertSame('3ª', $book->edition);
        $this->assertSame(2023, $book->publication_year);
        $this->assertSame(416, $book->pages);
        $this->assertSame('pt-BR', $book->language);
        $this->assertSame('Brochura', $book->format);
        $this->assertSame(['Sociologia'], $book->categories->pluck('name')->all());
        $this->assertSame('Coleção Z', $book->collection->name);
    }

    public function test_search_finds_book_by_isbn10_isbn13_title_and_author_fragments(): void
    {
        Book::factory()->create(['title' => 'Geografia Humana', 'authors' => 'Marta Oliveira', 'isbn13' => '9780306406157', 'isbn10' => '0306406152']);
        Book::factory()->create(['title' => 'Outro Livro', 'authors' => 'Fulano']);

        foreach (['0306406152', '0-306-40615-2', '9780306406157', 'humana', 'oliveira', 'GEOGRAFIA'] as $term) {
            $this->get('/catalogo?q='.urlencode($term))->assertSee('Geografia Humana')->assertDontSee('Outro Livro');
        }
    }

    public function test_search_with_no_results_shows_empty_state(): void
    {
        Book::factory()->create(['title' => 'Existente']);

        $this->get('/catalogo?q=inexistente')->assertOk()->assertSee('Nenhum livro corresponde')->assertSee('Limpar filtros');
    }

    public function test_unknown_publisher_and_missing_authors_do_not_break_filters(): void
    {
        Book::create(['title' => 'Sem editora']);

        $this->get('/catalogo?publisher=Desconhecida')->assertOk()->assertDontSee('Sem editora');
        $this->get('/catalogo?year=1999')->assertOk();
    }

    public function test_update_without_categories_detaches_previous_ones(): void
    {
        $category = Category::findOrCreateByName('Química');
        $book = Book::factory()->create();
        $book->categories()->attach($category);

        $this->put("/catalogo/{$book->id}", ['title' => $book->title])->assertRedirect();

        $this->assertSame(0, $book->fresh()->categories()->count());
    }

    public function test_invalid_filter_values_are_rejected_not_executed(): void
    {
        $this->get('/catalogo?year=abc')->assertSessionHasErrors('year');
        $this->get('/catalogo?category=1;DROP')->assertSessionHasErrors('category');
    }

    public function test_search_term_with_sql_like_wildcards_does_not_error(): void
    {
        Book::factory()->create(['title' => '100% Matemática']);

        $this->get('/catalogo?q='.urlencode("%' OR 1=1 --"))->assertOk();
        $this->get('/catalogo?q='.urlencode('100%'))->assertOk()->assertSee('100% Matemática');
    }

    public function test_scanner_form_does_not_save_anything_until_confirmation(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [['id' => 'g', 'volumeInfo' => ['title' => 'Encontrado']]]]),
            'openlibrary.org/*' => Http::response('', 404),
        ]);

        $this->postJson('/catalogo/isbn', ['isbn' => '9780306406157'])->assertJsonPath('status', 'found');

        $this->assertSame(0, Book::count());
    }

    public function test_lookup_not_found_offers_manual_registration_with_isbn(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['totalItems' => 0]),
            'openlibrary.org/*' => Http::response('', 404),
        ]);

        $json = $this->postJson('/catalogo/isbn', ['isbn' => '9780306406157'])->assertJsonPath('status', 'not_found')->json();

        $this->assertStringContainsString('isbn=9780306406157', $json['html']);
        $this->get('/catalogo/novo?isbn=9780306406157')->assertOk()->assertSee('9780306406157');
    }

    public function test_lookup_shows_divergences_between_sources(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [['id' => 'g', 'volumeInfo' => ['title' => 'Título A', 'publisher' => 'Editora A']]]]),
            'openlibrary.org/*' => Http::response(['key' => '/books/OL1M', 'title' => 'Título B', 'publishers' => ['Editora B']]),
        ]);

        $html = $this->postJson('/catalogo/isbn', ['isbn' => '9780306406157'])->assertJsonPath('status', 'found')->json('html');

        $this->assertStringContainsString('Divergência entre fontes', $html);
        $this->assertStringContainsString('Título A', $html);
        $this->assertStringContainsString('Título B', $html);
    }
}
