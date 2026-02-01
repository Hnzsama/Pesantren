<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\AcademicYear;

class AcademicPromotionSeeder extends Seeder
{
    public function run(): void
    {
        // Contoh Log Kenaikan Kelas (Hanya jika data siswa & tahun ajaran tersedia)
        // Ambil data acak sebagai contoh
        $student = Student::first();
        $prevYear = AcademicYear::where('name', '2024/2025')->first();
        $currYear = AcademicYear::where('name', '2025/2026')->first();
        if ($student && $prevYear && $currYear) {
            DB::table('academic_promotions_log')->updateOrInsert(
                [
                    'student_id' => $student->id,
                    'from_academic_year_id' => $prevYear->id,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::ulid(), // Manual ULID jika tidak auto-increment
                    'to_academic_year_id' => $currYear->id,
                    'action' => 'PROMOTE',
                    'executed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    // Optional: 'executed_by' => User::role('admin')->first()->id ?? null
                ]
            );
        }
    }
}
