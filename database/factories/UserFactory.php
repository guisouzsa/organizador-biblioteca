<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** Usada apenas em testes. */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('senha-de-teste-123'),
            'role' => UserRole::Viewer,
            'active' => true,
            'must_change_password' => false,
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }

    public function librarian(): static
    {
        return $this->role(UserRole::Librarian);
    }

    public function assistant(): static
    {
        return $this->role(UserRole::Assistant);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
