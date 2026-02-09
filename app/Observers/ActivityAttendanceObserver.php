<?php

namespace App\Observers;

use App\Models\ActivityAttendance;

class ActivityAttendanceObserver
{
    /**
     * Handle the ActivityAttendance "created" event.
     */
    public function created(ActivityAttendance $activityAttendance): void
    {
        $activityAttendance->load(['student.parent', 'activity']);

        $parent = $activityAttendance->student?->parent;

        if ($parent && $parent->fcm_token) {
            $fcmService = app(\App\Services\FcmService::class);
            $activityName = $activityAttendance->activity?->name ?? 'Kegiatan';
            $title = 'Kehadiran: ' . $activityAttendance->student->full_name;
            $body = 'Santri ' . $activityAttendance->student->full_name . ' telah tercatat ' . $activityAttendance->status . ' untuk ' . $activityName . ' pada ' . $activityAttendance->date->format('d/m/Y') . '.';

            $fcmService->sendToUser($parent, $title, $body, [
                'type' => 'activity_attendance',
                'id' => $activityAttendance->id,
            ]);
        }
    }
}
