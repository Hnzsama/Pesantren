<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = \App\Models\Student::first(['*']);
        $event = \App\Models\Event::first(['*']);
        if ($student && $event) {
            \App\Models\EventAttendance::create([
                'event_id' => $event->id,
                'student_id' => $student->id,
                'check_in_time' => now(),
                'status' => 'HADIR',
            ]);
        }
    }
}
