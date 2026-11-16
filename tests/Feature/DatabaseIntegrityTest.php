<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\Catalog\BookCatalogService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restrições de integridade. Rodam em SQLite (padrão) e em PostgreSQL
 * (veja phpunit.pgsql.xml), pois dependem do comportamento real do banco.
 */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    private const ISBN13 = '9780306406157';

    public function test_unique_isbn13_is_enforced_by_the_database(): void
    {
        Book::factory()->create(['isbn13' => self::ISBN13]);

        $this->expectException(UniqueConstraintViolationException::class);
        Book::factory()->create(['isbn13' => self::ISBN13]);
    }

    public function test_unique_isbn10_is_enforced_by_the_database(): void
    {
        Book::factory()->create(['isbn10' => '0306406152']);

        $this->expectException(UniqueConstraintViolationException::class);
        Book::factory()->create(['isbn10' => '0306406152']);
    }

    public function test_many_books_without_isbn_are_allowed(): void
    {
        Book::factory()->count(3)->create(['isbn13' => null, 'isbn10' => null]);

        $this->assertSame(3, Book::whereNull('isbn13')->count());
    }

    public function test_concurrent_duplicate_save_falls_back_to_duplicate_flow(): void
    {
        $existing = Book::factory()->create(['isbn13' => self::ISBN13, 'isbn10' => '0306406152']);

        // Simula a corrida: a checagem prévia não vê o livro, mas a restrição única do banco barra o INSERT.
        $this->partialMock(BookCatalogService::class, function ($mock) use ($existing) {
            $mock->shouldReceive('findDuplicate')->andReturn(null, $existing);
        });

        $this->post('/catalogo', ['title' => 'Corrida', 'isbn' => self::ISBN13])
            ->assertSessionHas('duplicate_book_id', $existing->id);

        $this->assertSame(1, Book::count());
        $this->assertSame(0, Book::where('title', 'Corrida')->count());
    }

    public function test_deleting_a_book_cascades_to_sources_and_category_links(): void
    {
        $book = Book::factory()->create();
        $book->sources()->create(['provider' => 'manual']);
        $book->categories()->attach(Category::findOrCreateByName('Física'));

        $book->delete();

        $this->assertSame(0, \DB::table('book_sources')->count());
        $this->assertSame(0, \DB::table('book_category')->count());
        $this->assertSame(1, Category::count(), 'A categoria em si não deve ser apagada.');
    }

    public function test_deleting_a_collection_keeps_its_books(): void
    {
        $collection = BookCollection::create(['name' => 'Coleção']);
        $book = Book::factory()->create(['collection_id' => $collection->id]);

        $collection->delete();

        $this->assertNull($book->fresh()->collection_id);
    }

    public function test_a_book_cannot_have_two_sources_from_the_same_provider(): void
    {
        $book = Book::factory()->create();
        $book->sources()->create(['provider' => 'google_books']);

        $this->expectException(UniqueConstraintViolationException::class);
        $book->sources()->create(['provider' => 'google_books']);
    }

    public function test_raw_metadata_round_trips_unicode_json(): void
    {
        $book = Book::factory()->create();
        $raw = ['title' => 'Educação Física — ação', 'nested' => ['autores' => ['João', 'Conceição']], 'n' => 3];

        $book->sources()->create(['provider' => 'google_books', 'raw_metadata' => $raw]);

        $this->assertSame($raw, $book->sources()->first()->raw_metadata);
    }

    public function test_search_is_case_insensitive_for_ascii(): void
    {
        Book::factory()->create(['title' => 'Geography Atlas']);

        $this->assertSame(1, Book::search('GEOGRAPHY')->count());
        $this->assertSame(1, Book::search('atlas')->count());
    }

    public function test_year_and_pages_fit_the_column_types(): void
    {
        $book = Book::factory()->create(['publication_year' => 2026, 'pages' => 20000]);

        $this->assertSame(20000, $book->fresh()->pages);
    }
}
