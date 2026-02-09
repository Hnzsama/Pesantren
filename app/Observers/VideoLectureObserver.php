<?php

namespace App\Observers;

use App\Models\VideoLecture;

class VideoLectureObserver
{
    /**
     * Handle the VideoLecture "created" event.
     */
    public function created(VideoLecture $videoLecture): void
    {
        $fcmService = app(\App\Services\FcmService::class);
        $title = "Kajian Baru: " . $videoLecture->title;
        $body = "Hadirilah kajian baru: " . \Illuminate\Support\Str::limit($videoLecture->description, 100);
        $data = [
            'type' => 'video_lecture',
            'id' => $videoLecture->id,
        ];

        // Notify Admins and Guardians
        $fcmService->sendToRole('admin', $videoLecture->school_id, $title, $body, $data);
        $fcmService->sendToRole('wali_santri', null, $title, $body, $data);
    }

    /**
     * Handle the VideoLecture "updated" event.
     */
    public function updated(VideoLecture $videoLecture): void
    {
        //
    }

    /**
     * Handle the VideoLecture "deleted" event.
     */
    public function deleted(VideoLecture $videoLecture): void
    {
        //
    }

    /**
     * Handle the VideoLecture "restored" event.
     */
    public function restored(VideoLecture $videoLecture): void
    {
        //
    }

    /**
     * Handle the VideoLecture "force deleted" event.
     */
    public function forceDeleted(VideoLecture $videoLecture): void
    {
        //
    }
}
