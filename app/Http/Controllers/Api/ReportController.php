<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StudentReport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get academic years and semesters for filtering.
     */
    public function getFilters(Request $request)
    {
        $school_id = $request->header('X-School-Id') ?? auth()->user()?->school_id;

        if (!$school_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'School ID not found'
            ], 400);
        }

        $filters = AcademicYear::where('school_id', $school_id)
            ->with(['semesters' => function ($q) {
                $q->orderBy('type', 'asc');
            }])
            ->orderBy('name', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $filters
        ]);
    }

    /**
     * Display a listing of student reports.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $studentIds = $user->students()->pluck('id');

        $query = StudentReport::with(['student:id,full_name,nis', 'semester:id,type,academic_year_id', 'semester.academicYear:id,name'])
            ->whereIn('student_id', $studentIds);

        if ($request->has('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->has('academic_year_id')) {
            $query->whereHas('semester', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        if ($request->has('student_id')) {
            // Ensure the requested student belongs to the parent
            if (!$studentIds->contains($request->student_id)) {
                return response()->json(['message' => 'Unauthorized student access'], 403);
            }
            $query->where('student_id', $request->student_id);
        }

        $reports = $query->latest()->paginate($request->get('per_page', 15));

        // Add accessor-like URLs for frontend
        $reports->getCollection()->transform(function ($report) {
            return [
                'id' => $report->id,
                'student' => $report->student,
                'semester' => $report->semester,
                'academic_year' => $report->semester?->academicYear,
                'files' => $report->files, // Array of URLs from accessor
                'file_url' => $report->file_url, // Single URL from accessor
                'uploaded_at' => $report->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($reports);
    }
}
