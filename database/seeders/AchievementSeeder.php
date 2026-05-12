<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = \App\Models\Student::first(['*']);
        if ($student) {
            \App\Models\Achievement::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'title' => 'Juara 1 Lomba MTQ Nasional',
                'date_achieved' => now()->subMonths(2),
                'description' => 'Berhasil meraih juara 1 tingkat nasional di Jakarta.',
                'images' => [],
            ]);

            \App\Models\Achievement::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'title' => 'Tahfidz 30 Juz',
                'date_achieved' => now()->subMonths(1),
                'description' => 'Menyelesaikan setoran hafalan 30 juz sekali duduk.',
                'images' => [],
            ]);
        }
    }
}
