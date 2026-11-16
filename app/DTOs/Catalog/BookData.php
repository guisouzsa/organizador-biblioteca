<?php

namespace App\DTOs\Catalog;

/** Dados validados de um livro (cadastro e edição). Imutável. */
final readonly class BookData
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<string>  $newCategories
     */
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public ?string $authors,
        public ?string $publisher,
        public ?string $isbn10,
        public ?string $isbn13,
        public ?string $edition,
        public ?int $publicationYear,
        public ?int $pages,
        public ?string $description,
        public ?string $coverUrl,
        public ?string $language,
        public ?string $format,
        public ?int $collectionId,
        public array $categoryIds = [],
        public array $newCategories = [],
        public ?string $lookupToken = null,
    ) {}

    /** @param  array<string, mixed>  $data  entrada validada + isbn10/isbn13 já normalizados */
    public static function fromArray(array $data): self
    {
        $blank = fn ($v) => is_string($v) && trim($v) === '' ? null : $v;
        $names = preg_split('/[;,\r\n]+/', (string) ($data['new_categories'] ?? '')) ?: [];

        return new self(
            title: trim((string) $data['title']),
            subtitle: $blank($data['subtitle'] ?? null),
            authors: $blank($data['authors'] ?? null),
            publisher: $blank($data['publisher'] ?? null),
            isbn10: $data['isbn10'] ?? null,
            isbn13: $data['isbn13'] ?? null,
            edition: $blank($data['edition'] ?? null),
            publicationYear: isset($data['publication_year']) && $data['publication_year'] !== '' ? (int) $data['publication_year'] : null,
            pages: isset($data['pages']) && $data['pages'] !== '' ? (int) $data['pages'] : null,
            description: $blank($data['description'] ?? null),
            coverUrl: $blank($data['cover_url'] ?? null),
            language: $blank($data['language'] ?? null),
            format: $blank($data['format'] ?? null),
            collectionId: isset($data['collection_id']) && $data['collection_id'] !== '' ? (int) $data['collection_id'] : null,
            categoryIds: array_map('intval', $data['categories'] ?? []),
            newCategories: array_values(array_filter(array_map('trim', $names))),
            lookupToken: $data['lookup_token'] ?? null,
        );
    }

    /** Atributos persistidos em `books`. @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'title' => $this->title, 'subtitle' => $this->subtitle, 'authors' => $this->authors,
            'publisher' => $this->publisher, 'isbn10' => $this->isbn10, 'isbn13' => $this->isbn13,
            'edition' => $this->edition, 'publication_year' => $this->publicationYear, 'pages' => $this->pages,
            'description' => $this->description, 'cover_url' => $this->coverUrl, 'language' => $this->language,
            'format' => $this->format, 'collection_id' => $this->collectionId,
        ];
    }
}
