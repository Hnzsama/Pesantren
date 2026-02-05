<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

class StudentImportService
{
    /**
     * Simulate student import with strictly DETERMINISTIC parent matching.
     * Parents must exist in `users` table with matching `phone_number`.
     * Handles NIS Auto-Generation if missing.
     */
    public function simulateImport(array $rows, string $schoolId): array
    {
        $stats = [
            'total_rows' => count($rows),
            'inserted' => 0,
            'updated' => 0,
            'orphaned' => 0, // Parent not found
            'matched' => 0,  // Parent found
            'details' => [], 
        ];

        // Fetch existing students for update check (only if NIS is provided)
        $existingStudents = Student::where('school_id', $schoolId)->whereNotNull('nis')->get()->keyBy('nis');
        
        $phones = collect($rows)->pluck('parent_phone')->map(fn($p) => $this->cleanPhone($p))->filter()->unique();
        $existingParents = User::whereIn('phone_number', $phones)->get()->keyBy('phone_number');

        foreach ($rows as $index => $row) {
            $nis = isset($row['nis']) ? trim($row['nis']) : '';
            
            // Determine Action
            if (empty($nis)) {
                $action = 'INSERT (AUTO-NIS)';
            } elseif (isset($existingStudents[$nis])) {
                $action = 'UPDATE';
            } else {
                $action = 'INSERT';
            }
            
            // Parent Lookup
            $rawPhone = isset($row['parent_phone']) ? $row['parent_phone'] : '';
            $phone = $this->cleanPhone($rawPhone);
            
            $matchStatus = 'NO_MATCH';
            $parentId = null;
            $parentName = null;

            if ($phone && isset($existingParents[$phone])) {
                $user = $existingParents[$phone];
                $matchStatus = 'MATCHED';
                $parentId = $user->id;
                $parentName = $user->name;
                $stats['matched']++;
            } else {
                $matchStatus = 'NO_MATCH';
                $stats['orphaned']++;
            }

            if (str_contains($action, 'INSERT')) $stats['inserted']++;
            else $stats['updated']++;

            $stats['details'][] = [
                'row_id' => $index + 1,
                'student' => [
                    'name' => $row['name'] ?? 'Unknown',
                    'nis' => $nis,
                    'gender' => $row['gender'] ?? 'L'
                ],
                'parent_input' => [
                    'phone' => $rawPhone,
                    'name_in_csv' => $row['parent_name'] ?? ''
                ],
                'action' => $action,
                'match_status' => $matchStatus,
                'found_parent' => $parentName ? ['id' => $parentId, 'name' => $parentName] : null,
                'selected_parent_id' => $parentId 
            ];
        }

        return $stats;
    }

    private function cleanPhone($phone)
    {
        if (!$phone) return '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }
        return $phone;
    }

    public function generateNis(string $schoolId): string
    {
        // Simple NIS Generation: Year + Random 4 digits (Verification needed to avoid collision)
        // In real app, might want a sequence table or max(nis) + 1
        $prefix = date('Y');
        do {
            $nis = $prefix . mt_rand(1000, 9999);
        } while (Student::where('school_id', $schoolId)->where('nis', $nis)->exists());
        
        return $nis;
    }
}
