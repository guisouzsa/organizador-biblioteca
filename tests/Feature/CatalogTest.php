<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCollection;
use App\Models\BookSource;
use App\Models\Category;
use App\Services\Catalog\BookLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    private const ISBN13 = '9780306406157';

    private const ISBN10 = '0306406152';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Filosofia: volume único',
            'authors' => 'Marilena Chaui',
            'publisher' => 'Ática',
            'publication_year' => 2016,
        ], $overrides);
    }

    // ---------- Listagem, busca e filtros ----------

    public function test_catalog_lists_books_with_key_fields(): void
    {
        Book::factory()->create(['title' => 'Química Geral', 'authors' => 'Ana Lima', 'publisher' => 'Moderna', 'isbn13' => self::ISBN13, 'publication_year' => 2020]);

        $this->get('/catalogo')->assertOk()
            ->assertSee('Química Geral')->assertSee('Ana Lima')->assertSee('Moderna')
            ->assertSee(self::ISBN13)->assertSee('2020');
    }

    public function test_catalog_search_by_title_author_and_isbn(): void
    {
        Book::factory()->create(['title' => 'Química Geral', 'authors' => 'Ana Lima', 'isbn13' => self::ISBN13]);
        Book::factory()->create(['title' => 'Biologia Celular', 'authors' => 'Carlos Souza']);

        $this->get('/catalogo?q=Química')->assertSee('Química Geral')->assertDontSee('Biologia Celular');
        $this->get('/catalogo?q=Souza')->assertSee('Biologia Celular')->assertDontSee('Química Geral');
        $this->get('/catalogo?q=978-0-306')->assertSee('Química Geral')->assertDontSee('Biologia Celular');
    }

    public function test_catalog_filters_by_category_publisher_year_and_collection(): void
    {
        $category = Category::findOrCreateByName('Física');
        $collection = BookCollection::create(['name' => 'Coleção X']);
        $a = Book::factory()->create(['title' => 'Livro A', 'publisher' => 'Ed1', 'publication_year' => 2021, 'collection_id' => $collection->id]);
        $a->categories()->attach($category);
        Book::factory()->create(['title' => 'Livro B', 'publisher' => 'Ed2', 'publication_year' => 2019]);

        $this->get('/catalogo?category='.$category->id)->assertSee('Livro A')->assertDontSee('Livro B');
        $this->get('/catalogo?publisher=Ed2')->assertSee('Livro B')->assertDontSee('Livro A');
        $this->get('/catalogo?year=2021')->assertSee('Livro A')->assertDontSee('Livro B');
        $this->get('/catalogo?collection='.$collection->id)->assertSee('Livro A')->assertDontSee('Livro B');
    }

    public function test_empty_catalog_shows_guidance(): void
    {
        $this->get('/catalogo')->assertOk()->assertSee('ainda não tem livros');
    }

    // ---------- Cadastro ----------

    public function test_manual_registration_without_isbn(): void
    {
        $this->post('/catalogo', $this->payload())->assertRedirect();

        $book = Book::firstOrFail();
        $this->assertSame('Filosofia: volume único', $book->title);
        $this->assertNull($book->isbn13);
        $this->assertNull($book->isbn10);
        $this->assertSame(BookSource::PROVIDER_MANUAL, $book->sources->first()->provider);
    }

    public function test_two_books_without_isbn_can_coexist(): void
    {
        $this->post('/catalogo', $this->payload(['title' => 'Sem ISBN 1']));
        $this->post('/catalogo', $this->payload(['title' => 'Sem ISBN 2']));

        $this->assertSame(2, Book::count());
    }

    public function test_registration_with_isbn10_stores_both_isbns(): void
    {
        $this->post('/catalogo', $this->payload(['isbn' => '0-306-40615-2']))->assertRedirect();

        $book = Book::firstOrFail();
        $this->assertSame(self::ISBN13, $book->isbn13);
        $this->assertSame(self::ISBN10, $book->isbn10);
    }

    public function test_invalid_isbn_is_rejected(): void
    {
        $this->post('/catalogo', $this->payload(['isbn' => '9780306406158']))->assertSessionHasErrors('isbn');
        $this->post('/catalogo', $this->payload(['isbn' => '7891000100103']))->assertSessionHasErrors('isbn');

        $this->assertSame(0, Book::count());
    }

    public function test_title_is_required(): void
    {
        $this->post('/catalogo', $this->payload(['title' => '']))->assertSessionHasErrors('title');
    }

    public function test_authors_are_normalized(): void
    {
        $this->post('/catalogo', $this->payload(['authors' => "Ana Lima;  João Silva\nAna Lima"]));

        $this->assertSame('Ana Lima; João Silva', Book::firstOrFail()->authors);
        $this->assertSame(['Ana Lima', 'João Silva'], Book::first()->authorsList());
    }

    public function test_registration_attaches_existing_and_new_categories_and_collection(): void
    {
        $existing = Category::findOrCreateByName('Matemática');
        $collection = BookCollection::create(['name' => 'PNLD 2026 — Ciências Humanas']);

        $this->post('/catalogo', $this->payload([
            'categories' => [$existing->id],
            'new_categories' => 'Projeto de Vida, matemática',
            'collection_id' => $collection->id,
        ]))->assertRedirect();

        $book = Book::firstOrFail();
        $this->assertEqualsCanonicalizing(['Matemática', 'Projeto de Vida'], $book->categories->pluck('name')->all());
        $this->assertSame('PNLD 2026 — Ciências Humanas', $book->collection->name);
    }

    public function test_registration_with_lookup_token_records_sources(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [['id' => 'g1', 'volumeInfo' => ['title' => 'Do Google', 'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => self::ISBN13]]]]]]),
            'openlibrary.org/*' => Http::response('', 404),
        ]);

        $token = $this->postJson('/catalogo/isbn', ['isbn' => self::ISBN13])->assertOk()->json('status');
        $this->assertSame('found', $token);

        // O token fica no cache; recupera pelo serviço como a tela faria.
        $lookup = app(BookLookupService::class)->lookup(self::ISBN13);
        $this->post('/catalogo', $this->payload(['isbn' => self::ISBN13, 'lookup_token' => $lookup['token']]))->assertRedirect();

        $source = Book::firstOrFail()->sources()->firstOrFail();
        $this->assertSame('google_books', $source->provider);
        $this->assertSame('g1', $source->external_id);
        $this->assertSame('confirmed', $source->verification_status);
        $this->assertNotNull($source->retrieved_at);
        $this->assertSame('g1', $source->raw_metadata['id']);
    }

    // ---------- Duplicação ----------

    public function test_duplicate_isbn_is_not_created_and_offers_existing_record(): void
    {
        $existing = Book::factory()->create(['title' => 'Já existe', 'isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);

        $this->from('/catalogo/novo')
            ->post('/catalogo', $this->payload(['isbn' => self::ISBN13]))
            ->assertRedirect('/catalogo/novo')
            ->assertSessionHas('duplicate_book_id', $existing->id);

        $this->assertSame(1, Book::count());

        $this->withSession(['duplicate_book_id' => $existing->id])->get('/catalogo/novo')
            ->assertSee('Este ISBN já está no catálogo')->assertSee('Abrir registro existente');
    }

    public function test_duplicate_detected_through_isbn10_input(): void
    {
        Book::factory()->create(['isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);

        $this->post('/catalogo', $this->payload(['isbn' => self::ISBN10]))->assertSessionHas('duplicate_book_id');
        $this->assertSame(1, Book::count());
    }

    public function test_existing_record_is_updated_only_with_confirmation(): void
    {
        $existing = Book::factory()->create(['title' => 'Antigo', 'isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);

        $this->post('/catalogo', $this->payload(['title' => 'Novo título', 'isbn' => self::ISBN13, 'confirm_update' => 1]))
            ->assertRedirect(route('books.show', $existing));

        $this->assertSame(1, Book::count());
        $this->assertSame('Novo título', $existing->fresh()->title);
    }

    public function test_similar_titles_are_not_treated_as_duplicates(): void
    {
        Book::factory()->create(['title' => 'Filosofia: volume único', 'isbn13' => self::ISBN13]);

        $this->post('/catalogo', $this->payload(['isbn' => '9788535902778']))->assertRedirect();

        $this->assertSame(2, Book::count());
    }

    // ---------- Detalhes e edição ----------

    public function test_show_displays_book_details_and_sources(): void
    {
        $book = Book::factory()->create(['title' => 'Detalhe', 'isbn13' => self::ISBN13, 'pages' => 321]);
        $book->sources()->create(['provider' => 'google_books', 'source_url' => 'https://books.google.com/x', 'retrieved_at' => now(), 'verification_status' => 'confirmed']);

        $this->get("/catalogo/{$book->id}")->assertOk()
            ->assertSee('Detalhe')->assertSee(self::ISBN13)->assertSee('321')
            ->assertSee('Google Books')->assertSee('Conferido pelo usuário');
    }

    public function test_show_returns_404_for_unknown_book(): void
    {
        $this->get('/catalogo/999')->assertNotFound();
    }

    public function test_edit_form_is_prefilled_and_update_persists(): void
    {
        $category = Category::findOrCreateByName('História');
        $book = Book::factory()->create(['title' => 'Antes', 'isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);

        $this->get("/catalogo/{$book->id}/editar")->assertOk()->assertSee('Antes');

        $this->put("/catalogo/{$book->id}", $this->payload(['title' => 'Depois', 'isbn' => self::ISBN13, 'categories' => [$category->id]]))
            ->assertRedirect(route('books.show', $book));

        $book->refresh();
        $this->assertSame('Depois', $book->title);
        $this->assertSame(['História'], $book->categories->pluck('name')->all());
    }

    public function test_update_rejects_isbn_belonging_to_another_book(): void
    {
        Book::factory()->create(['isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);
        $other = Book::factory()->create(['isbn13' => null, 'isbn10' => null]);

        $this->put("/catalogo/{$other->id}", $this->payload(['isbn' => self::ISBN13]))->assertSessionHasErrors('isbn13');
    }

    public function test_update_keeps_own_isbn(): void
    {
        $book = Book::factory()->create(['isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);

        $this->put("/catalogo/{$book->id}", $this->payload(['isbn' => self::ISBN13]))->assertSessionHasNoErrors();
    }

    // ---------- Scanner e consulta ----------

    public function test_scanner_page_renders(): void
    {
        $this->get('/catalogo/scanner')->assertOk()->assertSee('Escanear livro')->assertSee('Digitar ISBN manualmente');
    }

    public function test_lookup_endpoint_reports_invalid_isbn(): void
    {
        Http::fake();

        $this->postJson('/catalogo/isbn', ['isbn' => '9780306406158'])
            ->assertOk()->assertJsonPath('status', 'invalid');

        Http::assertNothingSent();
    }

    public function test_lookup_endpoint_returns_confirmation_form_when_found(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [['id' => 'g1', 'volumeInfo' => ['title' => 'Livro Encontrado', 'authors' => ['Autor X'], 'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => self::ISBN13]]]]]]),
            'openlibrary.org/*' => Http::response('', 404),
        ]);

        $response = $this->postJson('/catalogo/isbn', ['isbn' => '0-306-40615-2'])->assertOk()->assertJsonPath('status', 'found');

        $html = $response->json('html');
        $this->assertStringContainsString('Livro Encontrado', $html);
        $this->assertStringContainsString('Confirmar e salvar', $html);
        $this->assertSame(0, Book::count(), 'Nada deve ser gravado antes da confirmação.');
    }

    public function test_lookup_endpoint_returns_existing_book(): void
    {
        $book = Book::factory()->create(['title' => 'Cadastrado', 'isbn13' => self::ISBN13, 'isbn10' => self::ISBN10]);
        Http::fake();

        $response = $this->postJson('/catalogo/isbn', ['isbn' => self::ISBN13])->assertJsonPath('status', 'exists');

        $this->assertStringContainsString(route('books.show', $book), $response->json('html'));
        Http::assertNothingSent();
    }

    public function test_lookup_endpoint_handles_external_failure(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->postJson('/catalogo/isbn', ['isbn' => self::ISBN13])->assertOk()->assertJsonPath('status', 'unavailable');
    }

    public function test_lookup_endpoint_requires_isbn(): void
    {
        $this->postJson('/catalogo/isbn', [])->assertUnprocessable();
    }
}
