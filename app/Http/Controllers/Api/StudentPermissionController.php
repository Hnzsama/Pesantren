<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentPermission;
use Illuminate\Http\Request;

class StudentPermissionController extends Controller
{
    /**
     * Display a list of permission requests for the authenticated parent's students.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all student names belonging to this parent
        $studentNames = $user->students()->pluck('full_name')->toArray();

        // Fetch permissions for those students
        $permissions = StudentPermission::query()
            ->whereIn('student_name', $studentNames)
            ->latest()
            ->get();

        return response()->json($permissions);
    }

    /**
     * Store a new permission request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|ulid',
            'reason' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = $request->user();

        // Verify the student belongs to this parent
        $student = Student::where('id', $validated['student_id'])
            ->where('parent_user_id', $user->id)
            ->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 403);
        }

        // Create the permission request
        $permission = StudentPermission::create([
            'school_id' => $student->school_id,
            'student_name' => $student->full_name,
            'reason' => $validated['reason'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permission request submitted successfully.',
            'data' => $permission,
        ], 201);
    }
}

/*
|--------------------------------------------------------------------------
| API Documentation (Frontend)
|--------------------------------------------------------------------------
|
| 1. GET /api/permissions
|    Middleware: auth:sanctum
|
|    Mengambil daftar pengajuan izin untuk semua santri milik wali.
|
|    Example Response (200 OK):
|    [
|        {
|            "id": 1,
|            "student_name": "Ahmad Fauzi",
|            "reason": "Sakit demam",
|            "start_date": "2026-01-25",
|            "end_date": "2026-01-27",
|            "status": "pending",
|            "admin_note": null,
|            "created_at": "2026-01-24T10:00:00.000000Z",
|            "updated_at": "2026-01-24T10:00:00.000000Z"
|        }
|    ]
|
| 2. POST /api/permissions
|    Middleware: auth:sanctum
|
|    Mengajukan permohonan izin baru untuk santri.
|
|    Request Body:
|    {
|        "student_id": "01hk...",
|        "reason": "Sakit demam tinggi",
|        "start_date": "2026-01-25",
|        "end_date": "2026-01-27"
|    }
|
|    Example Response (201 Created):
|    {
|        "message": "Permission request submitted successfully.",
|        "data": {
|            "id": 1,
|            "student_name": "Ahmad Fauzi",
|            "reason": "Sakit demam tinggi",
|            "start_date": "2026-01-25",
|            "end_date": "2026-01-27",
|            "status": "pending",
|            "admin_note": null,
|            "created_at": "2026-01-24T10:00:00.000000Z",
|            "updated_at": "2026-01-24T10:00:00.000000Z"
|        }
|    }
|
|    Status Values:
|    - pending: Menunggu persetujuan admin
|    - approved: Disetujui
|    - rejected: Ditolak
|
*/
