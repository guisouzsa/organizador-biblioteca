<?php

namespace App\Services\Catalog;

use App\Exceptions\ExternalLookupException;
use App\Models\BookSource;
use App\Services\Catalog\Concerns\SanitizesExternalData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Consulta o Google Books por ISBN e normaliza o resultado. */
class GoogleBooksService
{
    use SanitizesExternalData;

    public function __construct(private IsbnValidatorService $isbn) {}

    /**
     * @return array<string, mixed>|null null quando o ISBN não é encontrado
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
        } catch (ConnectionException) {
            // Não encadeia a exceção original: a mensagem dela contém a URL (e a chave, se houver).
            throw new ExternalLookupException('Google Books indisponível (falha de conexão ou tempo esgotado).');
        }

        if ($response->status() === 429) {
            throw new ExternalLookupException('Google Books: limite de requisições atingido (HTTP 429). Configure GOOGLE_BOOKS_API_KEY.');
        }

        if ($response->failed()) {
            throw new ExternalLookupException('Google Books respondeu HTTP '.$response->status().'.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new ExternalLookupException('Google Books retornou uma resposta que não é JSON válido.');
        }

        $items = $body['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        $items = array_values(array_filter($items, 'is_array'));

        return $items === [] ? null : $this->normalize($this->pickItem($items, $isbn13), $isbn13);
    }

    /** Prefere o item cujos identificadores contêm o ISBN pesquisado. */
    private function pickItem(array $items, string $isbn13): array
    {
        foreach ($items as $item) {
            $identifiers = $item['volumeInfo']['industryIdentifiers'] ?? [];
            foreach (is_array($identifiers) ? $identifiers : [] as $identifier) {
                if (is_array($identifier) && ($identifier['identifier'] ?? null) === $isbn13) {
                    return $item;
                }
            }
        }

        return $items[0];
    }

    private function normalize(array $item, string $searchedIsbn): array
    {
        $info = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : [];

        $isbn10 = $isbn13 = null;
        $identifiers = is_array($info['industryIdentifiers'] ?? null) ? $info['industryIdentifiers'] : [];
        foreach ($identifiers as $identifier) {
            if (! is_array($identifier)) {
                continue;
            }
            $value = $this->isbn->normalize($this->text($identifier['identifier'] ?? null, 40));
            if (($identifier['type'] ?? '') === 'ISBN_13' && $this->isbn->isValidIsbn13($value)) {
                $isbn13 = $value;
            } elseif (($identifier['type'] ?? '') === 'ISBN_10' && $this->isbn->isValidIsbn10($value)) {
                $isbn10 = $value;
            }
        }

        $links = is_array($info['imageLinks'] ?? null) ? $info['imageLinks'] : [];
        $cover = $this->httpUrl($links['thumbnail'] ?? null) ?? $this->httpUrl($links['smallThumbnail'] ?? null);
        if ($cover) {
            $cover = str_replace(['http://', '&edge=curl'], ['https://', ''], $cover);
        }

        return [
            'provider' => BookSource::PROVIDER_GOOGLE_BOOKS,
            'external_id' => $this->text($item['id'] ?? null, 255),
            'source_url' => $this->httpUrl($info['canonicalVolumeLink'] ?? null)
                ?? $this->httpUrl($info['infoLink'] ?? null)
                ?? $this->httpUrl($item['selfLink'] ?? null),
            'title' => $this->text($info['title'] ?? null, 255),
            'subtitle' => $this->text($info['subtitle'] ?? null, 255),
            'authors' => $this->textList($info['authors'] ?? null),
            'publisher' => $this->text($info['publisher'] ?? null, 255),
            'publication_year' => $this->yearFrom($info['publishedDate'] ?? null),
            'isbn10' => $isbn10,
            'isbn13' => $isbn13 ?? $searchedIsbn,
            'edition' => null,
            'pages' => $this->positiveInt($info['pageCount'] ?? null),
            'description' => $this->text($info['description'] ?? null),
            'categories' => $this->textList($info['categories'] ?? null),
            'cover_url' => $cover,
            'language' => $this->text($info['language'] ?? null, 10),
            'raw' => $item,
        ];
    }
}
