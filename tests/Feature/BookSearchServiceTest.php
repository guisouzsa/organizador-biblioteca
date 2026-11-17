<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\Catalog\BookSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_local_catalog_before_collecting_external_results(): void
    {
        $local = Book::factory()->create(['title' => 'Biologia brasileira']);
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => []]),
            'openlibrary.org/search.json*' => Http::response(['docs' => []]),
        ]);

        $result = app(BookSearchService::class)->search('Biologia');

        $this->assertSame($local->id, $result['local'][0]->id);
        $this->assertSame([], $result['external']);
    }

    public function test_prioritizes_portuguese_without_discarding_other_languages(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [[
                'id' => 'en', 'volumeInfo' => ['title' => 'Science', 'language' => 'en'],
            ]]]),
            'openlibrary.org/search.json*' => Http::response(['docs' => [[
                'key' => '/works/OL1W', 'title' => 'Ciência', 'language' => ['por'],
            ]]]),
        ]);

        $result = app(BookSearchService::class)->search('science');

        $this->assertSame('Ciência', $result['external'][0]['title']);
        $this->assertSame('Science', $result['external'][1]['title']);
    }

    public function test_reuses_cached_provider_results_for_identical_searches(): void
    {
        Cache::clear();
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => []]),
            'openlibrary.org/search.json*' => Http::response(['docs' => []]),
        ]);

        $service = app(BookSearchService::class);
        $service->search('literatura brasileira');
        $service->search('literatura brasileira');

        Http::assertSentCount(2);
    }
}
