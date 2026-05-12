<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = \App\Models\Student::first(['*']);
        $semester = \App\Models\Semester::first(['*']);
        $uploader = \App\Models\User::role('admin')->first();

        if ($student && $semester && $uploader) {
            \App\Models\StudentReport::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'file_path' => json_encode(['reports/sample.pdf']),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }
}
