<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
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
            'full_name' => $this->faker->name(),
            'nis' => $this->faker->unique()->numerify('##########'),
            'gender' => $this->faker->randomElement(['L', 'P']),
            'status' => 'ACTIVE',
            'wallet_balance' => 0,
            'wallet_pin' => bcrypt('123456'),
        ];
    }
}
