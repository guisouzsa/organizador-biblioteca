<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'parent_id', 'archived_at'];

    protected function casts(): array
    {
        return ['type' => CategoryType::class, 'archived_at' => 'datetime'];
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Cria (ou recupera) a categoria pelo nome, evitando duplicatas por slug. */
    public static function findOrCreateByName(string $name, CategoryType $type = CategoryType::Theme): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'type' => $type]);
    }
}
