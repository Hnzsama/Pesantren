<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = \App\Models\School::all();

        foreach ($schools as $school) {
            $levels = $school->levels;

            foreach ($levels as $level) {
                // Create Daily Activities
                \App\Models\Activity::factory()->count(3)->create([
                    'school_id' => $school->id,
                    'level_id' => $level->id,
                    'type' => 'daily',
                ]);

                // Create Rutinan Activities
                \App\Models\Activity::factory()->count(2)->create([
                    'school_id' => $school->id,
                    'level_id' => $level->id,
                    'type' => 'rutinan',
                ]);
            }
        }
    }
}
