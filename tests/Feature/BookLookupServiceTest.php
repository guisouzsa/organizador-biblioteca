<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\Catalog\BookLookupService;
use App\Services\Catalog\MetadataComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9780306406157';

    private function googleResponse(string $title = 'Filosofia: volume único', string $publisher = 'Saraiva'): array
    {
        return ['items' => [[
            'id' => 'g1',
            'volumeInfo' => [
                'title' => $title, 'authors' => ['Marilena Chaui'], 'publisher' => $publisher,
                'publishedDate' => '2016',
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => self::ISBN]],
            ],
        ]]];
    }

    private function openLibraryResponse(string $title = 'Filosofia: Volume Único', string $publisher = 'Saraiva'): array
    {
        return ['key' => '/books/OL1M', 'title' => $title, 'publishers' => [$publisher], 'publish_date' => '2016', 'number_of_pages' => 400];
    }

    public function test_invalid_isbn_does_not_hit_external_apis(): void
    {
        Http::fake();

        $result = app(BookLookupService::class)->lookup('9780306406158');

        $this->assertSame('invalid', $result['status']);
        Http::assertNothingSent();
    }

    public function test_non_isbn_ean13_is_rejected(): void
    {
        Http::fake();

        $result = app(BookLookupService::class)->lookup('7891000100103');

        $this->assertSame('invalid', $result['status']);
        Http::assertNothingSent();
    }

    public function test_existing_book_is_returned_without_external_calls(): void
    {
        Http::fake();
        $book = Book::factory()->create(['isbn13' => self::ISBN, 'isbn10' => '0306406152']);

        $result = app(BookLookupService::class)->lookup('0-306-40615-2'); // ISBN-10 digitado

        $this->assertSame('exists', $result['status']);
        $this->assertTrue($result['book']->is($book));
        Http::assertNothingSent();
    }

    public function test_merges_sources_and_fills_gaps_from_second_source(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response($this->googleResponse()),
            'openlibrary.org/*' => Http::response($this->openLibraryResponse()),
        ]);

        $result = app(BookLookupService::class)->lookup(self::ISBN);

        $this->assertSame('found', $result['status']);
        $this->assertCount(2, $result['sources']);
        $this->assertSame('Filosofia: volume único', $result['merged']['title']); // prioridade Google
        $this->assertSame(400, $result['merged']['pages']);                         // veio do Open Library
        $this->assertSame(['Marilena Chaui'], $result['merged']['authors']);
        $this->assertSame([], $result['divergences']); // diferem só em caixa
        $this->assertNotNull($result['token']);
    }

    public function test_reports_divergences_between_sources(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response($this->googleResponse(publisher: 'Saraiva')),
            'openlibrary.org/*' => Http::response($this->openLibraryResponse(publisher: 'Editora Moderna')),
        ]);

        $result = app(BookLookupService::class)->lookup(self::ISBN);

        $this->assertArrayHasKey('publisher', $result['divergences']);
        $this->assertSame('Saraiva', $result['divergences']['publisher']['values']['google_books']);
        $this->assertSame('Editora Moderna', $result['divergences']['publisher']['values']['open_library']);
    }

    public function test_falls_back_to_open_library_when_google_fails(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response([], 503),
            'openlibrary.org/*' => Http::response($this->openLibraryResponse()),
        ]);

        $result = app(BookLookupService::class)->lookup(self::ISBN);

        $this->assertSame('found', $result['status']);
        $this->assertCount(1, $result['sources']);
        $this->assertSame('open_library', $result['sources'][0]['provider']);
        $this->assertArrayHasKey('google_books', $result['errors']);
    }

    public function test_not_found_when_no_source_knows_the_isbn(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['totalItems' => 0]),
            'openlibrary.org/*' => Http::response('', 404),
        ]);

        $this->assertSame('not_found', app(BookLookupService::class)->lookup(self::ISBN)['status']);
    }

    public function test_unavailable_when_all_sources_fail(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $result = app(BookLookupService::class)->lookup(self::ISBN);

        $this->assertSame('unavailable', $result['status']);
        $this->assertCount(2, $result['errors']);
    }

    public function test_sources_are_recoverable_by_token(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response($this->googleResponse()),
            'openlibrary.org/*' => Http::response('', 404),
        ]);
        $service = app(BookLookupService::class);

        $result = $service->lookup(self::ISBN);

        $this->assertSame('g1', $service->sourcesForToken($result['token'])[0]['external_id']);
        $this->assertSame([], $service->sourcesForToken('inexistente'));
    }

    public function test_comparison_normalizes_case_accents_and_author_order(): void
    {
        $comparison = new MetadataComparisonService;

        $divergences = $comparison->divergences([
            ['provider' => 'a', 'title' => 'Matemática Básica', 'authors' => ['João Silva', 'Ana Lima']],
            ['provider' => 'b', 'title' => 'matematica basica', 'authors' => ['ana lima', 'joao silva']],
        ]);

        $this->assertSame([], $divergences);
    }
}
