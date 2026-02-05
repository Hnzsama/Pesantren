<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Student;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Get list of activities for a student on a specific date,
     * merged with their attendance status.
     */
    public function index(Request $request)
    {
        $request->validate([
            'student_id' => 'required|ulid',
            'date' => 'required|date',
            'type' => 'nullable|string', // daily, rutinan
        ]);

        $user = $request->user();

        // 1. Validate Student Ownership
        $student = Student::where('id', $request->student_id)
            ->where('parent_user_id', $user->id) // Ensure parent owns student
            ->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 403);
        }

        // 2. Fetch Available Activities (Definitions)
        // Filter by School and Level (if activity is level-specific)
        $activitiesQuery = Activity::query()
            ->where('school_id', $student->school_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('level_id')
                    ->orWhere('level_id', $student->level_id);
            });

        if ($request->has('type')) {
            $activitiesQuery->where('type', $request->type);
        }

        $activities = $activitiesQuery->get();

        // 3. Fetch Attendance Records for this Student & Date
        $attendances = ActivityAttendance::query()
            ->where('student_id', $student->id)
            ->whereDate('date', $request->date)
            ->whereIn('activity_id', $activities->pluck('id'))
            ->get()
            ->keyBy('activity_id');

        // 4. Merge Data
        $results = $activities->map(function ($activity) use ($attendances) {
            $attendance = $attendances->get($activity->id);

            return [
                'activity' => $activity,
                'attendance' => $attendance, // null if no record (pending)
            ];
        });

        return response()->json($results);
    }
}

/*
|--------------------------------------------------------------------------
| API Documentation (Frontend)
|--------------------------------------------------------------------------
|
| Endpoint: GET /api/activities
| Middleware: auth:sanctum
|
| Mengambil daftar kegiatan (Activity) yang tersedia untuk siswa tersebut,
| digabung dengan status kehadirannya (ActivityAttendance) pada tanggal tertentu.
|
| Parameters (Query Strings):
| 1. student_id (Required)
|    - ID santri. Harus milik user yang login.
|
| 2. date (Required)
|    - Tanggal untuk cek kehadiran.
|    - Format: YYYY-MM-DD
|
| 3. type (Optional)
|    - Filter tipe kegiatan.
|    - Values: 'daily' (Harian), 'rutinan' (Mingguan/Bulanan)
|
| Example Response (200 OK):
| [
|     {
|         "activity": {
|             "id": "01hk...",
|             "name": "Shalat Subuh Berjamaah",
|             "type": "daily",
|             "description": "Wajib di masjid",
|             "school_id": "...",
|             "level_id": null
|         },
|         "attendance": {
|             "id": "01hk...",
|             "status": "present", // present, late, absent, permission
|             "remarks": null,
|             "date": "2026-01-24"
|         }
|     },
|     {
|         "activity": {
|             "id": "01hk...",
|             "name": "Mengaji Kitab",
|             "type": "daily"
|         },
|         "attendance": null // null means PENDING / Belum Ada Absensi
|     }
| ]
|
*/
