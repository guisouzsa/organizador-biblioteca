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
        'collection_id',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'pages' => 'integer',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_category');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookCollection::class, 'collection_id');
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
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->where('categories.id', $id)))
            ->when($filters['publisher'] ?? null, fn (Builder $q, $v) => $q->where('publisher', $v))
            ->when($filters['year'] ?? null, fn (Builder $q, $v) => $q->where('publication_year', $v))
            ->when($filters['collection'] ?? null, fn (Builder $q, $v) => $q->where('collection_id', $v));
    }

    private function likeOperator(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
