<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Usada apenas em testes. */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'authors' => fake()->name(),
            'publisher' => fake()->company(),
            'publication_year' => fake()->numberBetween(2015, 2025),
        ];
    }
}
