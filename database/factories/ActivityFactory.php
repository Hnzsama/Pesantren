<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Sholat Dhuha', 'Ngaji Pagi', 'Setoran Hafalan', 'Kerja Bakti', 'Olahraga Pagi']),
            'type' => $this->faker->randomElement(['daily', 'rutinan']),
            'description' => $this->faker->sentence(),
            'school_id' => \App\Models\School::inRandomOrder()->first()?->id,
            'level_id' => \App\Models\Level::inRandomOrder()->first()?->id,
        ];
    }
}
