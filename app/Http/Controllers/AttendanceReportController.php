<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Level;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function print(Request $request)
    {
        $type = $request->query('type', 'daily'); // 'daily' or 'activity'
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $levelId = $request->query('level_id');
        $activityId = $request->query('activity_id');

        $user = auth()->user();
        $schoolId = $user->school_id;

        $level = Level::find($levelId);
        $specificActivity = $activityId ? Activity::find($activityId) : null;

        // Get activities based on type or specific ID
        $activities = Activity::query()
            ->where('school_id', $schoolId)
            ->when($activityId, fn($q) => $q->where('id', $activityId))
            ->when(!$activityId && $type === 'daily', fn($q) => $q->where('type', 'daily'))
            ->when(!$activityId && $type === 'activity', fn($q) => $q->where('type', '!=', 'daily'))
            ->when($levelId, function ($q) use ($levelId) {
                $q->where(function ($query) use ($levelId) {
                    $query->where('level_id', $levelId)
                        ->orWhereNull('level_id');
                });
            })
            ->orderBy('name')
            ->get();

        $students = Student::query()
            ->where('school_id', $schoolId)
            ->when($levelId, fn($q) => $q->where('level_id', $levelId))
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->get();

        $studentIds = $students->pluck('id')->toArray();
        $activityIds = $activities->pluck('id')->toArray();

        $records = ActivityAttendance::whereIn('student_id', $studentIds)
            ->whereIn('activity_id', $activityIds)
            ->where('date', $date)
            ->get();

        $matrix = [];
        foreach ($records as $record) {
            $matrix[$record->student_id][$record->activity_id] = $record->status;
        }

        if ($specificActivity) {
            $title = 'Laporan Absensi: ' . $specificActivity->name;
        } else {
            $title = ($type === 'daily' ? 'Laporan Absensi Harian' : 'Laporan Absensi Kegiatan');
        }
        $dateString = Carbon::parse($date)->translatedFormat('d F Y');

        return view('pages.activities.attendance.print', compact(
            'title',
            'dateString',
            'activities',
            'students',
            'matrix',
            'level'
        ));
    }
}
