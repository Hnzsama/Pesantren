<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentAcademic;
use Illuminate\Support\Facades\DB;

class AcademicService
{
    /**
     * Promote students from one level to another in batch.
     */
    public function promoteStudents(Level $fromLevel, Level $toLevel, AcademicYear $finishedYear, array $studentIds)
    {
        return DB::transaction(function () use ($fromLevel, $toLevel, $finishedYear, $studentIds) {
            $students = Student::whereIn('id', $studentIds)
                ->where('level_id', $fromLevel->id)
                ->get();

            $count = 0;
            foreach ($students as $student) {
                // 1. Create History Record for the FINISHED year
                StudentAcademic::create([
                    'student_id' => $student->id,
                    'level_id' => $fromLevel->id,
                    'academic_year_id' => $finishedYear->id,
                    'status' => 'PROMOTED',
                    'notes' => "Naik ke {$toLevel->name}",
                ]);

                // 2. Update Student Level
                $student->update(['level_id' => $toLevel->id]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Graduate students in batch.
     */
    public function graduateStudents(Level $fromLevel, AcademicYear $finishedYear, array $studentIds)
    {
        return DB::transaction(function () use ($fromLevel, $finishedYear, $studentIds) {
            $students = Student::whereIn('id', $studentIds)
                ->where('level_id', $fromLevel->id)
                ->get();

            $count = 0;
            foreach ($students as $student) {
                // 1. Create History Record
                StudentAcademic::create([
                    'student_id' => $student->id,
                    'level_id' => $fromLevel->id,
                    'academic_year_id' => $finishedYear->id,
                    'status' => 'GRADUATED',
                    'notes' => 'Lulus dari '.($fromLevel->department?->name ?? 'system'),
                ]);

                // 2. Update Student Status
                $student->update([
                    'status' => 'GRADUATED',
                    'level_id' => null, // Optional: Clear level or keep as "Alumni Class"
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Retain students (Tinggal Kelas).
     */
    public function retainStudents(Level $level, AcademicYear $finishedYear, array $studentIds)
    {
        return DB::transaction(function () use ($level, $finishedYear, $studentIds) {
            $students = Student::whereIn('id', $studentIds)
                ->where('level_id', $level->id)
                ->get();

            foreach ($students as $student) {
                StudentAcademic::create([
                    'student_id' => $student->id,
                    'level_id' => $level->id,
                    'academic_year_id' => $finishedYear->id,
                    'status' => 'RETAINED',
                    'notes' => 'Tinggal kelas',
                ]);
                // No level change
            }
        });
    }
}
