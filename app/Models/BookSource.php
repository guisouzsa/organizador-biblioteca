<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookSource extends Model
{
    public const PROVIDER_GOOGLE_BOOKS = 'google_books';

    public const PROVIDER_OPEN_LIBRARY = 'open_library';

    public const PROVIDER_MANUAL = 'manual';

    public const STATUS_UNVERIFIED = 'unverified';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'book_id', 'provider', 'external_id', 'source_url',
        'retrieved_at', 'raw_metadata', 'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'retrieved_at' => 'datetime',
            'raw_metadata' => 'array',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public static function providerLabel(string $provider): string
    {
        return match ($provider) {
            self::PROVIDER_GOOGLE_BOOKS => 'Google Books',
            self::PROVIDER_OPEN_LIBRARY => 'Open Library',
            self::PROVIDER_MANUAL => 'Cadastro manual',
            default => $provider,
        };
    }
}
