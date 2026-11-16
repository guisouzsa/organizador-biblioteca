<?php

namespace Tests\Feature;

use App\Exceptions\ExternalLookupException;
use App\Services\Catalog\OpenLibraryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryServiceTest extends TestCase
{
    private const ISBN = '9780306406157';

    public function test_finds_and_normalizes_edition(): void
    {
        Http::fake([
            'openlibrary.org/isbn/*' => Http::response([
                'key' => '/books/OL123M',
                'title' => 'Título OL',
                'subtitle' => 'Sub OL',
                'authors' => [['key' => '/authors/OL1A']],
                'publishers' => ['Editora OL'],
                'publish_date' => 'May 2019',
                'number_of_pages' => 200,
                'isbn_10' => ['0306406152'],
                'edition_name' => '2ª edição',
                'covers' => [555],
                'description' => ['type' => '/type/text', 'value' => 'Desc OL'],
                'languages' => [['key' => '/languages/por']],
            ]),
            'openlibrary.org/authors/OL1A.json' => Http::response(['name' => 'Autora OL']),
        ]);

        $book = app(OpenLibraryService::class)->findByIsbn(self::ISBN);

        $this->assertSame('open_library', $book['provider']);
        $this->assertSame('OL123M', $book['external_id']);
        $this->assertSame('Título OL', $book['title']);
        $this->assertSame(['Autora OL'], $book['authors']);
        $this->assertSame('Editora OL', $book['publisher']);
        $this->assertSame(2019, $book['publication_year']);
        $this->assertSame(200, $book['pages']);
        $this->assertSame('0306406152', $book['isbn10']);
        $this->assertSame(self::ISBN, $book['isbn13']);
        $this->assertSame('2ª edição', $book['edition']);
        $this->assertSame('Desc OL', $book['description']);
        $this->assertSame('por', $book['language']);
        $this->assertSame('https://covers.openlibrary.org/b/id/555-L.jpg', $book['cover_url']);
        $this->assertSame('https://openlibrary.org/books/OL123M', $book['source_url']);
    }

    public function test_returns_null_on_404(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response('', 404)]);

        $this->assertNull(app(OpenLibraryService::class)->findByIsbn(self::ISBN));
    }

    public function test_author_lookup_failure_does_not_break_lookup(): void
    {
        Http::fake([
            'openlibrary.org/isbn/*' => Http::response(['key' => '/books/OL1M', 'title' => 'X', 'authors' => [['key' => '/authors/OL9A']]]),
            'openlibrary.org/authors/*' => Http::response('', 500),
        ]);

        $book = app(OpenLibraryService::class)->findByIsbn(self::ISBN);

        $this->assertSame('X', $book['title']);
        $this->assertSame([], $book['authors']);
        $this->assertNull($book['publisher']);
        $this->assertNull($book['cover_url']);
    }

    public function test_server_error_raises_lookup_exception(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response('', 500)]);

        $this->expectException(ExternalLookupException::class);
        app(OpenLibraryService::class)->findByIsbn(self::ISBN);
    }

    public function test_connection_failure_raises_lookup_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(ExternalLookupException::class);
        app(OpenLibraryService::class)->findByIsbn(self::ISBN);
    }
}
