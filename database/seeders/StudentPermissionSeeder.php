<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = \App\Models\Student::first(['*']);
        if ($student) {
            \App\Models\StudentPermission::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'reason' => 'Sakit Demam',
                'start_date' => now()->subDays(5),
                'end_date' => now()->subDays(3),
                'status' => 'approved',
                'admin_note' => 'Istirahat total di rumah.',
            ]);

            \App\Models\StudentPermission::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'reason' => 'Acara Pernikahan Kakak',
                'start_date' => now()->addDays(2),
                'end_date' => now()->addDays(4),
                'status' => 'pending',
            ]);
        }
    }
}
