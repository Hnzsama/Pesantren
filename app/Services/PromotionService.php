<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Student;
use App\Models\StudentAcademic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionService
{
    /**
     * Get students ready for promotion from a specific level
     */
    public function getStudentsForPromotion(string $levelId): Collection
    {
        return Student::where('level_id', $levelId)
            ->where('status', 'ACTIVE')
            ->with('level.department')
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Find the next level in the same department
     */
    public function getNextLevel(Level $currentLevel): ?Level
    {
        return Level::where('department_id', $currentLevel->department_id)
            ->where('grade_level', '>', $currentLevel->grade_level)
            ->orderBy('grade_level', 'asc')
            ->first();
    }

    /**
     * Prepare promotion data with default actions
     */
    public function preparePromotionData(Collection $students): array
    {
        $promotionData = [];

        foreach ($students as $student) {
            $currentLevel = $student->level;
            $nextLevel = $this->getNextLevel($currentLevel);

            $promotionData[] = [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'student_nis' => $student->nis,
                'current_level_id' => $currentLevel->id,
                'current_level_name' => $currentLevel->name,
                'action' => $nextLevel ? 'PROMOTE' : 'GRADUATE',
                'target_level_id' => $nextLevel?->id,
                'target_level_name' => $nextLevel?->name,
            ];
        }

        return $promotionData;
    }

    /**
     * Calculate statistics from promotion data
     */
    public function calculateStats(array $promotionData): array
    {
        $stats = [
            'total' => count($promotionData),
            'promoted' => 0,
            'graduated' => 0,
            'retained' => 0,
        ];

        foreach ($promotionData as $data) {
            switch ($data['action']) {
                case 'PROMOTE':
                    $stats['promoted']++;
                    break;
                case 'GRADUATE':
                    $stats['graduated']++;
                    break;
                case 'RETAIN':
                    $stats['retained']++;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Execute the promotion with transaction
     */
    public function executePromotion(
        array $promotionData,
        string $targetAcademicYearId,
        string $sourceAcademicYearId,
        string $executedBy
    ): array {
        return DB::transaction(function () use ($promotionData, $targetAcademicYearId, $sourceAcademicYearId, $executedBy) {
            $stats = [
                'promoted' => 0,
                'graduated' => 0,
                'retained' => 0,
            ];

            foreach ($promotionData as $data) {
                $studentId = $data['student_id'];
                $action = $data['action'];

                // Lock student
                $student = Student::lockForUpdate()->find($studentId);
                if (!$student) {
                    continue;
                }

                // Update old academic record
                $oldAcademic = StudentAcademic::where('student_id', $studentId)
                    ->where('academic_year_id', $sourceAcademicYearId)
                    ->lockForUpdate()
                    ->first();

                if ($oldAcademic) {
                    $oldStatus = match($action) {
                        'PROMOTE' => 'PROMOTED',
                        'RETAIN' => 'RETAINED',  
                        'GRADUATE' => 'GRADUATED',
                        default => 'ONGOING'
                    };
                    $oldAcademic->update(['status' => $oldStatus]);
                }

                // Execute action
                if ($action === 'PROMOTE') {
                    // Create new academic record
                    StudentAcademic::create([
                        'student_id' => $studentId,
                        'academic_year_id' => $targetAcademicYearId,
                        'level_id' => $data['target_level_id'],
                        'status' => 'ONGOING',
                    ]);

                    // Update student's current level
                    $student->update([
                        'level_id' => $data['target_level_id'],
                        'status' => 'ACTIVE'
                    ]);

                    $stats['promoted']++;

                } elseif ($action === 'RETAIN') {
                    // Stay in same level for new academic year
                    StudentAcademic::create([
                        'student_id' => $studentId,
                        'academic_year_id' => $targetAcademicYearId,
                        'level_id' => $data['current_level_id'],
                        'status' => 'ONGOING',
                    ]);

                    $student->update(['status' => 'ACTIVE']);
                    $stats['retained']++;

                } elseif ($action === 'GRADUATE') {
                    $student->update(['status' => 'GRADUATED']);
                    $stats['graduated']++;
                }

                // Log the promotion
                DB::table('academic_promotions_log')->insert([
                    'id' => (string) Str::ulid(),
                    'student_id' => $studentId,
                    'from_academic_year_id' => $sourceAcademicYearId,
                    'to_academic_year_id' => in_array($action, ['PROMOTE', 'RETAIN']) ? $targetAcademicYearId : null,
                    'action' => $action,
                    'from_level_id' => $data['current_level_id'],
                    'to_level_id' => $data['target_level_id'] ?? null,
                    'executed_by' => $executedBy,
                    'executed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $stats;
        });
    }
}
