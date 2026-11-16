<?php

namespace App\Services;

use App\Exceptions\ExternalLookupException;
use App\Models\BookSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Consulta o Google Books por ISBN e normaliza o resultado. */
class GoogleBooksService
{
    public function __construct(private IsbnValidatorService $isbn) {}

    /**
     * @return array<string, mixed>|null  null quando o ISBN não é encontrado
     *
     * @throws ExternalLookupException
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $config = config('services.google_books');

        $query = ['q' => 'isbn:'.$isbn13, 'maxResults' => 5];
        if (filled($config['key'] ?? null)) {
            $query['key'] = $config['key'];
        }

        try {
            $response = Http::timeout($config['timeout'] ?? 8)
                ->acceptJson()
                ->get(rtrim($config['base_url'], '/').'/volumes', $query);
        } catch (ConnectionException $e) {
            throw new ExternalLookupException('Google Books indisponível (falha de conexão).', 0, $e);
        }

        if ($response->failed()) {
            throw new ExternalLookupException('Google Books respondeu HTTP '.$response->status().'.');
        }

        $items = $response->json('items') ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        return $this->normalize($this->pickItem($items, $isbn13), $isbn13);
    }

    /** Prefere o item cujos identificadores contêm o ISBN pesquisado. */
    private function pickItem(array $items, string $isbn13): array
    {
        foreach ($items as $item) {
            foreach ($item['volumeInfo']['industryIdentifiers'] ?? [] as $identifier) {
                if (($identifier['identifier'] ?? null) === $isbn13) {
                    return $item;
                }
            }
        }

        return $items[0];
    }

    private function normalize(array $item, string $searchedIsbn): array
    {
        $info = $item['volumeInfo'] ?? [];

        $isbn10 = $isbn13 = null;
        foreach ($info['industryIdentifiers'] ?? [] as $identifier) {
            $value = $this->isbn->normalize($identifier['identifier'] ?? '');
            if (($identifier['type'] ?? '') === 'ISBN_13' && $this->isbn->isValidIsbn13($value)) {
                $isbn13 = $value;
            } elseif (($identifier['type'] ?? '') === 'ISBN_10' && $this->isbn->isValidIsbn10($value)) {
                $isbn10 = $value;
            }
        }

        $cover = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;
        if ($cover) {
            $cover = str_replace(['http://', '&edge=curl'], ['https://', ''], $cover);
        }

        return [
            'provider' => BookSource::PROVIDER_GOOGLE_BOOKS,
            'external_id' => $item['id'] ?? null,
            'source_url' => $info['canonicalVolumeLink'] ?? $info['infoLink'] ?? ($item['selfLink'] ?? null),
            'title' => $info['title'] ?? null,
            'subtitle' => $info['subtitle'] ?? null,
            'authors' => array_values(array_filter($info['authors'] ?? [])),
            'publisher' => $info['publisher'] ?? null,
            'publication_year' => $this->year($info['publishedDate'] ?? null),
            'isbn10' => $isbn10,
            'isbn13' => $isbn13 ?? $searchedIsbn,
            'edition' => null,
            'pages' => isset($info['pageCount']) && $info['pageCount'] > 0 ? (int) $info['pageCount'] : null,
            'description' => $info['description'] ?? null,
            'categories' => array_values(array_filter($info['categories'] ?? [])),
            'cover_url' => $cover,
            'language' => $info['language'] ?? null,
            'raw' => $item,
        ];
    }

    private function year(?string $date): ?int
    {
        return $date !== null && preg_match('/^(\d{4})/', $date, $m) ? (int) $m[1] : null;
    }
}
