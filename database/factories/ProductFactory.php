<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
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
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->ean8(),
            'type' => 'PRODUCT',
            'selling_price' => $this->faker->numberBetween(1000, 50000),
            'purchase_price' => $this->faker->numberBetween(500, 40000),
            'stock' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
