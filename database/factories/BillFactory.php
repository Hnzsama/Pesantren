<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => \App\Models\School::factory(),
            'invoice_code' => 'INV-' . $this->faker->unique()->numberBetween(100000, 999999),
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(10000, 100000),
            'status' => 'UNPAID',
            'due_date' => now()->addMonth(),
        ];
    }
}
