<?php

namespace Tests\Feature;

use App\Exceptions\ExternalLookupException;
use App\Services\Catalog\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    private const ISBN = '9780306406157';

    private function fakeItem(): array
    {
        return [
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => 'Título de Teste',
                'subtitle' => 'Subtítulo',
                'authors' => ['Autora Um', 'Autor Dois'],
                'publisher' => 'Editora Teste',
                'publishedDate' => '2019-05-01',
                'description' => 'Descrição.',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '0306406152'],
                    ['type' => 'ISBN_13', 'identifier' => self::ISBN],
                ],
                'pageCount' => 320,
                'categories' => ['Mathematics'],
                'imageLinks' => ['thumbnail' => 'http://books.google.com/x?id=abc&edge=curl'],
                'language' => 'pt-BR',
                'canonicalVolumeLink' => 'https://books.google.com/books/about/x?id=abc',
            ],
        ];
    }

    public function test_finds_and_normalizes_book_by_isbn(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['items' => [$this->fakeItem()]])]);

        $book = app(GoogleBooksService::class)->findByIsbn(self::ISBN);

        $this->assertSame('google_books', $book['provider']);
        $this->assertSame('abc123', $book['external_id']);
        $this->assertSame('Título de Teste', $book['title']);
        $this->assertSame('Subtítulo', $book['subtitle']);
        $this->assertSame(['Autora Um', 'Autor Dois'], $book['authors']);
        $this->assertSame('Editora Teste', $book['publisher']);
        $this->assertSame(2019, $book['publication_year']);
        $this->assertSame('0306406152', $book['isbn10']);
        $this->assertSame(self::ISBN, $book['isbn13']);
        $this->assertSame(320, $book['pages']);
        $this->assertSame(['Mathematics'], $book['categories']);
        $this->assertSame('https://books.google.com/x?id=abc', $book['cover_url']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=isbn%3A'.self::ISBN));
    }

    public function test_returns_null_when_isbn_not_found(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['totalItems' => 0])]);

        $this->assertNull(app(GoogleBooksService::class)->findByIsbn(self::ISBN));
    }

    public function test_does_not_invent_missing_fields(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['items' => [['id' => 'x', 'volumeInfo' => ['title' => 'Só título']]]])]);

        $book = app(GoogleBooksService::class)->findByIsbn(self::ISBN);

        $this->assertSame('Só título', $book['title']);
        $this->assertNull($book['publisher']);
        $this->assertNull($book['publication_year']);
        $this->assertNull($book['pages']);
        $this->assertSame([], $book['authors']);
    }

    public function test_http_error_raises_lookup_exception(): void
    {
        Http::fake(['googleapis.com/*' => Http::response([], 503)]);

        $this->expectException(ExternalLookupException::class);
        app(GoogleBooksService::class)->findByIsbn(self::ISBN);
    }

    public function test_connection_failure_raises_lookup_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(ExternalLookupException::class);
        app(GoogleBooksService::class)->findByIsbn(self::ISBN);
    }
}
