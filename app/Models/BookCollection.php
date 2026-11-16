<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Coleção didática (tabela "collections"). */
class BookCollection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'name', 'publisher', 'fnde_code', 'program', 'cycle',
        'knowledge_area', 'source', 'verification_status',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'collection_id');
    }
}
