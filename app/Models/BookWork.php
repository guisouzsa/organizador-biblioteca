<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Criação intelectual compartilhada por uma ou mais edições (`books`). */
class BookWork extends Model
{
    protected $fillable = [
        'preferred_title', 'authors', 'original_language', 'first_publication_year', 'description',
    ];

    protected function casts(): array
    {
        return ['first_publication_year' => 'integer'];
    }

    public function editions(): HasMany
    {
        return $this->hasMany(Book::class, 'work_id');
    }
}
