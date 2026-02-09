<?php

namespace App\Observers;

use App\Models\Attendance;

class AttendanceObserver
{
    /**
     * Handle the Attendance "created" event.
     * Notifikasi kehadiran dikirim via ActivityAttendanceObserver, bukan dari sini.
     */
    public function created(Attendance $attendance): void
    {
        //
    }

    /**
     * Handle the Attendance "updated" event.
     */
    public function updated(Attendance $attendance): void
    {
        //
    }

    /**
     * Handle the Attendance "deleted" event.
     */
    public function deleted(Attendance $attendance): void
    {
        //
    }

    /**
     * Handle the Attendance "restored" event.
     */
    public function restored(Attendance $attendance): void
    {
        //
    }

    /**
     * Handle the Attendance "force deleted" event.
     */
    public function forceDeleted(Attendance $attendance): void
    {
        //
    }
}
