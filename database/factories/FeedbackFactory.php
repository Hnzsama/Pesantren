<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\School;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasUser = fake()->boolean(70); // 70% chance to be from a registered user
        
        $user = $hasUser ? User::inRandomOrder()->first() : null;
        $school = $user ? $user->school : School::inRandomOrder()->first();

        return [
            'school_id' => $school?->id,
            'user_id' => $user?->id,
            'sender_name' => $user?->name ?? fake()->name(),
            'email' => $user?->email ?? fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'category' => fake()->randomElement(['fasilitas', 'akademik', 'layanan']),
            'message' => fake()->paragraph(),
            'is_read' => fake()->boolean(30),
            'admin_reply' => null,
            'replied_at' => null,
        ];
    }
}
