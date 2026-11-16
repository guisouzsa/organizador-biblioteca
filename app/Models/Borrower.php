<?php

namespace App\Models;

use App\Enums\BorrowerStatus;
use App\Enums\BorrowerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Leitor (aluno, servidor...). Nunca é excluído: muda de situação (RN-PES-02). */
class Borrower extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'name', 'registration_number', 'class_group', 'email', 'phone',
        'status', 'status_changed_at', 'user_id', 'anonymized_at', 'demo_batch',
    ];

    protected function casts(): array
    {
        return [
            'type' => BorrowerType::class,
            'status' => BorrowerStatus::class,
            'status_changed_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->whereNull('returned_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isActive(): bool
    {
        return $this->status === BorrowerStatus::Active;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = $this->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(fn (Builder $q) => $q
            ->where('name', $like, "%{$term}%")
            ->orWhere('registration_number', $like, "%{$term}%")
            ->orWhere('class_group', $like, "%{$term}%"));
    }
}
