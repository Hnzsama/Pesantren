<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\StudentAcademic;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AcademicSimulationService
{
    /**
     * Get simulation data for a specific department and school.
     * Returns students grouped by level with their default promotion action.
     */
    public function getSimulationData(string $schoolId, string $departmentId, string $targetAcademicYearId): array
    {
        $department = Department::findOrFail($departmentId);
        $levels = $department->levels()->orderBy('grade_level')->get();
        
        $simulationData = [];

        foreach ($levels as $level) {
            $students = Student::where('school_id', $schoolId)
                ->where('level_id', $level->id)
                ->where('status', 'ACTIVE') // Only active students
                ->orderBy('full_name')
                ->get();

            // Determine next level (if any)
            $nextLevel = $this->getNextLevel($level);

            foreach ($students as $student) {
                $simulationData[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'current_level_id' => $level->id,
                    'current_level_name' => $level->name,
                    'action' => $nextLevel ? 'PROMOTE' : 'GRADUATE', // Default action
                    'target_level_id' => $nextLevel?->id,
                    'target_level_name' => $nextLevel?->name,
                    'notes' => '',
                ];
            }
        }

        return $simulationData;
    }

    /**
     * Find the next logical level for a given level within the same department.
     */
    public function getNextLevel(Level $currentLevel): ?Level
    {
        return Level::where('department_id', $currentLevel->department_id)
            ->where('grade_level', '>', $currentLevel->grade_level)
            ->orderBy('grade_level', 'asc')
            ->first();
    }

    /**
     * Calculate summary statistics from simulation data.
     */
    public function calculateSummary(array $simulationData): array
    {
        $summary = [
            'total_students' => count($simulationData),
            'promoted' => 0,
            'graduated' => 0,
            'retained' => 0,
            'transferred' => 0,
        ];

        foreach ($simulationData as $data) {
            switch ($data['action']) {
                case 'PROMOTE':
                    $summary['promoted']++;
                    break;
                case 'GRADUATE':
                    $summary['graduated']++;
                    break;
                case 'RETAIN':
                    $summary['retained']++;
                    break;
                case 'TRANSFER_OUT':
                    $summary['transferred']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Validate the simulation data for potential issues.
     */
    public function validateSimulation(array $simulationData, string $targetAcademicYearId): array
    {
        $warnings = [];
        $targetYear = AcademicYear::find($targetAcademicYearId);

        if (!$targetYear) {
            $warnings[] = "Tahun ajaran tujuan tidak valid.";
        }

        // Check for missing target levels for PROMOTE action
        foreach ($simulationData as $data) {
            if ($data['action'] === 'PROMOTE' && empty($data['target_level_id'])) {
                $warnings[] = "Siswa {$data['student_name']} diset 'NAIK KELAS' tetapi tidak memiliki level tujuan.";
            }
        }

        return $warnings;
    }

    /**
     * Execute the promotion simulation (Transactional).
     */
    public function executePromotion(string $schoolId, string $sourceAcademicYearId, string $targetAcademicYearId, array $simulationData, string $executedBy): array
    {
        Log::info('Service: executePromotion called', [
             'data_count' => count($simulationData),
             'target_year' => $targetAcademicYearId
        ]);

        return DB::transaction(function () use ($schoolId, $sourceAcademicYearId, $targetAcademicYearId, $simulationData, $executedBy) {
            
            // 1. Guard: Check for duplicate execution
            $existingRecords = StudentAcademic::where('academic_year_id', $targetAcademicYearId)
                ->whereHas('student', fn($q) => $q->whereIn('id', array_column($simulationData, 'student_id')))
                ->exists();

            if ($existingRecords) {
                Log::warning('Service: Execution blocked - duplicate records found');
                throw new \Exception('Promosi gagal: Terdeteksi data akademik yang sudah ada untuk tahun ajaran target.');
            }

            Log::info('Service: Transaction started. Processing ' . count($simulationData) . ' records.');

            $stats = [
                'promoted' => 0,
                'retained' => 0,
                'graduated' => 0,
                'transferred' => 0,
            ];

            foreach ($simulationData as $index => $data) {
                try {
                    $studentId = $data['student_id'];
                    $action = $data['action'];

                    if ($index === 0) {
                        Log::info('Service: Processing item 0', ['student_id' => $studentId, 'action' => $action]);
                    }
                    
                    // Lock Student
                    $student = Student::lockForUpdate()->find($studentId);
                    
                    if (!$student) {
                        Log::error("Service: Student not found for ID: $studentId");
                        continue;
                    }

                    // Lock Old Academic
                    $oldAcademic = StudentAcademic::where('student_id', $studentId)
                        ->where('academic_year_id', $sourceAcademicYearId)
                        ->lockForUpdate()
                        ->first();

                    // Update old record if exists
                    if ($oldAcademic) {
                        $oldStatus = match($action) {
                            'PROMOTE' => 'PROMOTED',
                            'RETAIN' => 'RETAINED',
                            'GRADUATE' => 'GRADUATED',
                            'TRANSFER_OUT' => 'TRANSFERRED',
                            default => 'ONGOING'
                        };
                        $oldAcademic->update(['status' => $oldStatus]);
                    }

                    // Handle Actions
                    if ($action === 'PROMOTE') {
                        StudentAcademic::create([
                            'student_id' => $studentId,
                            'academic_year_id' => $targetAcademicYearId,
                            'level_id' => $data['target_level_id'],
                            'status' => 'ONGOING',
                        ]);
                        
                        $student->update([
                            'level_id' => $data['target_level_id'],
                            'status' => 'ACTIVE'
                        ]);
                        $stats['promoted']++;

                    } elseif ($action === 'RETAIN') {
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

                    } elseif ($action === 'TRANSFER_OUT') {
                        $student->update(['status' => 'INACTIVE']);
                        $stats['transferred']++;
                    }

                    // 2. Logging
                    DB::table('academic_promotions_log')->insert([
                        'id' => (string) Str::ulid(),
                        'student_id' => $studentId,
                        'from_academic_year_id' => $sourceAcademicYearId,
                        'to_academic_year_id' => in_array($action, ['PROMOTE', 'RETAIN']) ? $targetAcademicYearId : null,
                        'action' => $action,
                        'from_level_id' => $data['current_level_id'] ?? null,
                        'to_level_id' => $data['target_level_id'] ?? null,
                        'executed_by' => $executedBy,
                        'executed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                } catch (\Exception $e) {
                    Log::error("Service: Error processing student $studentId: " . $e->getMessage());
                    throw $e; // Re-throw to rollback transaction
                }
            }
            
            Log::info('Service: Promotion Loop Completed', ['stats' => $stats]);

            return $stats;
        });
    }
}
