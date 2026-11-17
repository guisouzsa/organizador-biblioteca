<?php

namespace App\Services\Catalog;

use App\Exceptions\ExternalLookupException;
use App\Models\Book;
use App\Services\Catalog\Contracts\BibliographicProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BookSearchService
{
    private const CACHE_TTL_SECONDS = 3600;

    /** @var list<BibliographicProvider> */
    private array $providers;

    public function __construct(GoogleBooksService $google, OpenLibraryService $openLibrary)
    {
        $this->providers = [$google, $openLibrary];
    }

    /** @return array{local: list<Book>, external: list<array<string, mixed>>, errors: array<string, string>} */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['local' => [], 'external' => [], 'errors' => []];
        }

        $local = Book::query()->active()->search($query)->limit($limit)->get()->all();
        $external = [];
        $errors = [];

        foreach ($this->providers as $provider) {
            try {
                $cacheKey = 'bibliographic-search:'.$provider->name().':'.hash('sha256', Str::lower($query).'|'.$limit);
                $items = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => $provider->search($query, $limit));
                $external = [...$external, ...$items];
            } catch (ExternalLookupException $e) {
                $errors[$provider->name()] = $e->getMessage();
            }
        }

        $external = collect($external)
            ->unique(fn (array $item) => ($item['isbn13'] ?? null) ?: mb_strtolower(($item['title'] ?? '').'|'.implode(';', $item['authors'] ?? [])))
            ->sortByDesc(fn (array $item) => $this->relevanceScore($item, $query))
            ->take($limit)->values()->all();

        return ['local' => $local, 'external' => $external, 'errors' => $errors];
    }

    /** @param array<string, mixed> $item */
    private function relevanceScore(array $item, string $query): int
    {
        $needle = Str::lower(Str::ascii($query));
        $title = Str::lower(Str::ascii((string) ($item['title'] ?? '')));
        $authors = Str::lower(Str::ascii(implode(' ', $item['authors'] ?? [])));
        $publisher = Str::lower(Str::ascii((string) ($item['publisher'] ?? '')));
        $isbn = preg_replace('/\D/', '', $query) ?: null;
        $itemIsbns = array_filter([$item['isbn13'] ?? null, $item['isbn10'] ?? null]);

        $score = 0;
        if ($isbn && in_array($isbn, $itemIsbns, true)) $score += 1000;
        if ($title === $needle) $score += 50;
        elseif ($needle !== '' && str_contains($title, $needle)) $score += 30;
        if ($needle !== '' && str_contains($authors, $needle)) $score += 20;
        if ($needle !== '' && str_contains($publisher, $needle)) $score += 10;
        if (in_array(strtolower((string) ($item['language'] ?? '')), ['pt', 'por', 'pt-br'], true)) $score += 100;

        return $score;
    }
}
