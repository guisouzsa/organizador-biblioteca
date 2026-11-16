<?php

namespace App\Services;

use App\Exceptions\ExternalLookupException;
use App\Models\BookSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Consulta o Open Library por ISBN (edição) e normaliza o resultado. */
class OpenLibraryService
{
    private const MAX_AUTHOR_LOOKUPS = 4;

    public function __construct(private IsbnValidatorService $isbn) {}

    /**
     * @return array<string, mixed>|null  null quando o ISBN não é encontrado
     *
     * @throws ExternalLookupException
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $config = config('services.open_library');
        $base = rtrim($config['base_url'], '/');
        $timeout = $config['timeout'] ?? 8;

        try {
            $response = Http::timeout($timeout)->acceptJson()->get("{$base}/isbn/{$isbn13}.json");

            if ($response->status() === 404) {
                return null;
            }
            if ($response->failed()) {
                throw new ExternalLookupException('Open Library respondeu HTTP '.$response->status().'.');
            }

            $edition = $response->json();
            if (! is_array($edition) || blank($edition['title'] ?? null)) {
                return null;
            }

            $authors = $this->authors($edition, $base, $timeout);
        } catch (ConnectionException $e) {
            throw new ExternalLookupException('Open Library indisponível (falha de conexão).', 0, $e);
        }

        return $this->normalize($edition, $authors, $isbn13, $base, $config['covers_url'] ?? 'https://covers.openlibrary.org');
    }

    /**
     * Resolve nomes dos autores (a edição só traz chaves). Falhas aqui não derrubam a consulta.
     *
     * @return list<string>
     */
    private function authors(array $edition, string $base, int $timeout): array
    {
        $names = [];
        $refs = array_slice($edition['authors'] ?? [], 0, self::MAX_AUTHOR_LOOKUPS);

        foreach ($refs as $ref) {
            $key = $ref['key'] ?? null;
            if (! is_string($key) || ! str_starts_with($key, '/authors/')) {
                continue;
            }

            try {
                $author = Http::timeout($timeout)->acceptJson()->get("{$base}{$key}.json");
                if ($author->successful() && filled($author->json('name'))) {
                    $names[] = $author->json('name');
                }
            } catch (ConnectionException) {
                // Mantém os demais autores.
            }
        }

        return $names;
    }

    private function normalize(array $edition, array $authors, string $isbn13, string $base, string $coversUrl): array
    {
        $isbn10 = null;
        foreach ($edition['isbn_10'] ?? [] as $candidate) {
            $value = $this->isbn->normalize($candidate);
            if ($this->isbn->isValidIsbn10($value)) {
                $isbn10 = $value;
                break;
            }
        }

        $coverId = collect($edition['covers'] ?? [])->first(fn ($id) => is_int($id) && $id > 0);
        $description = $edition['description'] ?? null;
        if (is_array($description)) {
            $description = $description['value'] ?? null;
        }

        $subjects = collect($edition['subjects'] ?? [])
            ->map(fn ($s) => is_array($s) ? ($s['name'] ?? null) : $s)
            ->filter()->values()->all();

        $pages = $edition['number_of_pages'] ?? null;

        return [
            'provider' => BookSource::PROVIDER_OPEN_LIBRARY,
            'external_id' => isset($edition['key']) ? basename($edition['key']) : null,
            'source_url' => isset($edition['key']) ? $base.$edition['key'] : null,
            'title' => $edition['title'] ?? null,
            'subtitle' => $edition['subtitle'] ?? null,
            'authors' => $authors,
            'publisher' => $edition['publishers'][0] ?? null,
            'publication_year' => $this->year($edition['publish_date'] ?? null),
            'isbn10' => $isbn10,
            'isbn13' => $isbn13,
            'edition' => $edition['edition_name'] ?? null,
            'pages' => is_int($pages) && $pages > 0 ? $pages : null,
            'description' => is_string($description) ? $description : null,
            'categories' => $subjects,
            'cover_url' => $coverId ? rtrim($coversUrl, '/')."/b/id/{$coverId}-L.jpg" : null,
            'language' => $this->language($edition['languages'][0]['key'] ?? null),
            'raw' => $edition,
        ];
    }

    private function year(?string $date): ?int
    {
        return $date !== null && preg_match('/(\d{4})/', $date, $m) ? (int) $m[1] : null;
    }

    /** "/languages/por" → "por" */
    private function language(?string $key): ?string
    {
        return $key ? basename($key) : null;
    }
}
