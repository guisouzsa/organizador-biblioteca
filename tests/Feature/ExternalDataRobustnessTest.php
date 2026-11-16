<?php

namespace Tests\Feature;

use App\Exceptions\ExternalLookupException;
use App\Models\Book;
use App\Services\GoogleBooksService;
use App\Services\OpenLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Dados malformados/hostis vindos das APIs externas não podem quebrar nem contaminar o catálogo. */
class ExternalDataRobustnessTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9780306406157';

    public function test_google_with_wrong_types_is_sanitized_instead_of_crashing(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['items' => [['id' => 'p1', 'volumeInfo' => [
            'title' => 'Só título',
            'authors' => 'texto em vez de lista',
            'publisher' => ['não', 'é', 'texto'],
            'pageCount' => 0,
            'publishedDate' => 'sem ano',
            'categories' => 'x',
            'imageLinks' => ['thumbnail' => 'javascript:alert(1)'],
            'canonicalVolumeLink' => 'javascript:alert(2)',
        ]]]])]);

        $book = app(GoogleBooksService::class)->findByIsbn(self::ISBN);

        $this->assertSame('Só título', $book['title']);
        $this->assertSame([], $book['authors']);
        $this->assertNull($book['publisher']);
        $this->assertNull($book['pages']);
        $this->assertNull($book['publication_year']);
        $this->assertSame([], $book['categories']);
        $this->assertNull($book['cover_url']);
        $this->assertNull($book['source_url']);
    }

    public function test_open_library_with_wrong_types_does_not_invent_values(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([
            'key' => '/books/../../etc', 'title' => 'Só título OL',
            'publishers' => 'não é lista', 'number_of_pages' => 'abc', 'covers' => ['x'],
            'authors' => [['key' => '/authors/../x']], 'languages' => [['key' => 'qualquer']],
        ])]);

        $book = app(OpenLibraryService::class)->findByIsbn(self::ISBN);

        $this->assertNull($book['publisher'], 'Texto no lugar de lista não pode virar o primeiro caractere.');
        $this->assertNull($book['pages']);
        $this->assertNull($book['cover_url']);
        $this->assertNull($book['external_id']);
        $this->assertNull($book['source_url']);
        $this->assertNull($book['language']);
        $this->assertSame([], $book['authors']);
        Http::assertSentCount(1); // chave de autor suspeita não gera requisição
    }

    public function test_non_json_success_response_is_a_source_failure_not_a_not_found(): void
    {
        Http::fake(['*' => Http::response('<html>erro</html>', 200)]);

        $this->expectException(ExternalLookupException::class);
        app(GoogleBooksService::class)->findByIsbn(self::ISBN);
    }

    public function test_rate_limit_has_specific_message_and_does_not_leak_api_key(): void
    {
        config(['services.google_books.key' => 'SEGREDO-DA-CHAVE']);
        Http::fake(['*' => Http::response([], 429)]);

        try {
            app(GoogleBooksService::class)->findByIsbn(self::ISBN);
            $this->fail('Deveria lançar exceção.');
        } catch (ExternalLookupException $e) {
            $this->assertStringContainsString('429', $e->getMessage());
            $this->assertStringNotContainsString('SEGREDO-DA-CHAVE', $e->getMessage().$e->getTraceAsString());
        }
    }

    public function test_api_key_is_sent_only_when_configured(): void
    {
        config(['services.google_books.key' => null]);
        Http::fake(['googleapis.com/*' => Http::response(['totalItems' => 0])]);
        app(GoogleBooksService::class)->findByIsbn(self::ISBN);
        Http::assertSent(fn ($r) => ! str_contains($r->url(), 'key='));

        config(['services.google_books.key' => 'abc']);
        app(GoogleBooksService::class)->findByIsbn(self::ISBN);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'key=abc'));
    }

    public function test_connection_failure_message_does_not_leak_api_key(): void
    {
        config(['services.google_books.key' => 'SEGREDO-DA-CHAVE']);
        Http::fake(fn () => throw new ConnectionException('cURL error: https://x/?key=SEGREDO-DA-CHAVE'));

        try {
            app(GoogleBooksService::class)->findByIsbn(self::ISBN);
            $this->fail('Deveria lançar exceção.');
        } catch (ExternalLookupException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertStringNotContainsString('SEGREDO-DA-CHAVE', $e->getMessage());
        }
    }

    public function test_cover_url_with_dangerous_scheme_is_rejected_on_save(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'file:///etc/passwd'] as $url) {
            $this->post('/catalogo', ['title' => 'X', 'cover_url' => $url])->assertSessionHasErrors('cover_url');
        }
        $this->assertSame(0, Book::count());
    }

    public function test_html_in_titles_is_escaped_everywhere(): void
    {
        $book = Book::factory()->create(['title' => '<script>alert(1)</script>', 'authors' => '<img src=x onerror=alert(2)>']);

        foreach (['/catalogo', "/catalogo/{$book->id}", "/catalogo/{$book->id}/editar"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
        }
    }

    public function test_javascript_source_url_stored_in_database_is_not_rendered_as_link(): void
    {
        $book = Book::factory()->create();
        $book->sources()->create(['provider' => 'google_books', 'source_url' => 'javascript:alert(1)']);

        $this->get("/catalogo/{$book->id}")->assertOk()->assertDontSee('href="javascript:', false);
    }

    public function test_lookup_endpoint_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['totalItems' => 0])]);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/catalogo/isbn', ['isbn' => '9780000000002'])->assertOk();
        }

        $this->postJson('/catalogo/isbn', ['isbn' => self::ISBN])->assertStatus(429);
    }
}
