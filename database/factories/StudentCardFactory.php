<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentCard>
 */
class StudentCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'card_number' => $this->faker->unique()->numerify('##########'),
            'status' => 'printed',
            'issued_at' => now()->toDateString(),
            'issued_by' => \App\Models\User::factory(),
        ];
    }
}
