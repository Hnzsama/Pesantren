<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();
        foreach ($schools as $school) {
            // Tahun Ajaran Aktif
            AcademicYear::firstOrCreate(
                ['school_id' => $school->id, 'name' => '2025/2026'],
                [
                    'start_date' => '2025-07-01',
                    'end_date' => '2026-06-30',
                    'is_active' => true,
                ]
            );
            // Tahun Ajaran Sebelumnya (Arsip)
            AcademicYear::firstOrCreate(
                ['school_id' => $school->id, 'name' => '2024/2025'],
                [
                    'start_date' => '2024-07-01',
                    'end_date' => '2025-06-30',
                    'is_active' => false,
                ]
            );
        }
    }
}
