<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug'];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    /** Cria (ou recupera) a categoria pelo nome, evitando duplicatas por slug. */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }
}
