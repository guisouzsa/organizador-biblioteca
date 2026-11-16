<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro bibliográfico (obra/edição). Não representa exemplares físicos.
 */
class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'subtitle', 'authors', 'publisher', 'isbn10', 'isbn13', 'edition',
        'publication_year', 'pages', 'description', 'cover_url', 'language', 'format',
        'work_id', 'collection_group', 'material_type', 'audience', 'education_stage',
        'collection_id', 'archived_at', 'demo_batch',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'pages' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Livros não arquivados. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Único ponto da regra "este ISBN já existe" (13 ou 10). */
    public function scopeWithIsbn(Builder $query, ?string $isbn13, ?string $isbn10 = null): Builder
    {
        return $query->where(function (Builder $q) use ($isbn13, $isbn10) {
            $q->when($isbn13, fn ($q) => $q->orWhere('isbn13', $isbn13))
                ->when($isbn10, fn ($q) => $q->orWhere('isbn10', $isbn10));
        })->when(blank($isbn13) && blank($isbn10), fn ($q) => $q->whereRaw('1 = 0'));
    }

    /** Totais de exemplares (total e disponíveis), derivados. */
    public function scopeWithCopyCounts(Builder $query): Builder
    {
        return $query->withCount([
            'copies',
            'copies as available_copies_count' => fn (Builder $q) => $q->available(),
        ]);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_category');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookCollection::class, 'collection_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(BookWork::class, 'work_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BookSource::class);
    }

    /** @return list<string> */
    public function authorsList(): array
    {
        return array_values(array_filter(array_map('trim', explode(';', (string) $this->authors))));
    }

    public function collectionGroupLabel(): string
    {
        return $this->collection_group === 'school' ? 'Acervo escolar' : 'Acervo geral';
    }

    public function materialTypeLabel(): ?string
    {
        return match ($this->material_type) {
            'textbook' => 'Livro didático', 'support' => 'Material de apoio',
            'literature' => 'Literatura', 'technical' => 'Técnico',
            'academic' => 'Acadêmico', 'reference' => 'Referência',
            'popular_science' => 'Divulgação científica', 'other' => 'Outro',
            null, '' => null,
            default => $this->material_type,
        };
    }

    /** Busca por título, subtítulo, autores ou ISBN. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = $this->likeOperator();
        $escaped = '%'.$term.'%';
        $digits = preg_replace('/[\s\-]/', '', $term);

        return $query->where(function (Builder $q) use ($like, $escaped, $digits) {
            $q->where('title', $like, $escaped)
                ->orWhere('subtitle', $like, $escaped)
                ->orWhere('authors', $like, $escaped)
                ->orWhere('isbn13', $like, '%'.$digits.'%')
                ->orWhere('isbn10', $like, '%'.strtoupper($digits).'%');
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! ($filters['archived'] ?? false), fn (Builder $q) => $q->active())
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $id)))
            ->when($filters['discipline'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $id)->where('categories.type', 'discipline')))
            ->when($filters['subject'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $id)->whereIn('categories.type', ['subject', 'theme'])))
            ->when($filters['genre'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $id)->where('categories.type', 'genre')))
            ->when($filters['publisher'] ?? null, fn (Builder $q, $v) => $q->where('publisher', $v))
            ->when($filters['year'] ?? null, fn (Builder $q, $v) => $q->where('publication_year', $v))
            ->when($filters['collection'] ?? null, fn (Builder $q, $v) => $q->where('collection_id', $v))
            ->when($filters['collection_group'] ?? null, fn (Builder $q, $v) => $q->where('collection_group', $v))
            ->when($filters['material_type'] ?? null, fn (Builder $q, $v) => $q->where('material_type', $v))
            ->when($filters['language'] ?? null, fn (Builder $q, $v) => $q->where('language', $v))
            ->when($filters['education_stage'] ?? null, fn (Builder $q, $v) => $q->where('education_stage', $v));
    }

    private function likeOperator(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
