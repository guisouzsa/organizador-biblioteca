<?php

namespace Database\Factories;

use App\Enums\BorrowerStatus;
use App\Enums\BorrowerType;
use App\Models\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Usada apenas em testes. */
class BorrowerFactory extends Factory
{
    protected $model = Borrower::class;

    public function definition(): array
    {
        return [
            'type' => BorrowerType::Student,
            'name' => fake()->name(),
            'registration_number' => (string) fake()->unique()->numberBetween(100000, 999999),
            'class_group' => '2º A',
            'status' => BorrowerStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => BorrowerStatus::Inactive]);
    }
}
