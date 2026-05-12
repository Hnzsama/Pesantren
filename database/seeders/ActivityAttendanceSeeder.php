<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivityAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = \App\Models\Student::first(['*']);
        $activity = \App\Models\Activity::first(['*']);
        if ($student && $activity) {
            \App\Models\ActivityAttendance::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'activity_id' => $activity->id,
                'date' => now()->format('Y-m-d'),
                'status' => 'present',
                'remarks' => 'Hadir tepat waktu',
            ]);
        }
    }
}
