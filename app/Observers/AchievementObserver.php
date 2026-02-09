<?php

namespace App\Observers;

use App\Models\Achievement;

class AchievementObserver
{
    /**
     * Handle the Achievement "created" event.
     */
    public function created(Achievement $achievement): void
    {
        $achievement->load(['student.parent']);

        $parent = $achievement->student->parent;

        if ($parent && $parent->fcm_token) {
            $fcmService = app(\App\Services\FcmService::class);
            $title = 'Prestasi Baru: '.$achievement->title;
            $body = 'Alhamdulillah, '.$achievement->student->full_name.' meraih prestasi baru. Silakan cek detailnya.';

            $fcmService->sendToUser($parent, $title, $body, [
                'type' => 'achievement',
                'id' => $achievement->id,
            ]);
        }
    }

    /**
     * Handle the Achievement "updated" event.
     */
    public function updated(Achievement $achievement): void
    {
        //
    }

    /**
     * Handle the Achievement "deleted" event.
     */
    public function deleted(Achievement $achievement): void
    {
        //
    }

    /**
     * Handle the Achievement "restored" event.
     */
    public function restored(Achievement $achievement): void
    {
        //
    }

    /**
     * Handle the Achievement "force deleted" event.
     */
    public function forceDeleted(Achievement $achievement): void
    {
        //
    }
}
