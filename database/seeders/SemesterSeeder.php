<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil semua tahun ajaran yang sudah dibuat
        $academicYears = AcademicYear::all();
        foreach ($academicYears as $year) {
            // Semester Ganjil
            Semester::firstOrCreate(
                ['academic_year_id' => $year->id, 'type' => 'GANJIL'],
                [
                    'school_id' => $year->school_id,
                    'start_date' => $year->start_date, // Juli
                    'end_date' => date('Y-m-d', strtotime($year->start_date . ' + 6 months - 1 day')), // Desember
                ]
            );
            // Semester Genap
            Semester::firstOrCreate(
                ['academic_year_id' => $year->id, 'type' => 'GENAP'],
                [
                    'school_id' => $year->school_id,
                    'start_date' => date('Y-m-d', strtotime($year->start_date . ' + 6 months')), // Januari
                    'end_date' => $year->end_date, // Juni
                ]
            );
        }
    }
}
