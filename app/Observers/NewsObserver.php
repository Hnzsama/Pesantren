<?php

namespace App\Observers;

use App\Models\News;

class NewsObserver
{
    /**
     * Handle the News "created" event.
     */
    public function created(News $news): void
    {
        if ($news->is_published) {
            $this->notifyUsers($news);
        }
    }

    /**
     * Handle the News "updated" event.
     */
    public function updated(News $news): void
    {
        if ($news->is_published && $news->wasChanged('is_published')) {
            $this->notifyUsers($news);
        }
    }

    /**
     * Notify users based on target role.
     */
    protected function notifyUsers(News $news): void
    {
        $fcmService = app(\App\Services\FcmService::class);
        $title = "Berita Baru: " . $news->title;
        $body = \Illuminate\Support\Str::limit(strip_tags($news->content), 100);
        $data = [
            'type' => 'news',
            'id' => $news->id,
        ];

        if ($news->target_role === 'all') {
            $fcmService->sendToRole('admin', $news->school_id, $title, $body, $data);
            $fcmService->sendToRole('wali_santri', null, $title, $body, $data);
        } else {
            // Map legacy 'guardian' role to 'wali_santri' if necessary, or assume target_role is correct
            $targetRole = $news->target_role === 'guardian' ? 'wali_santri' : $news->target_role;
            $fcmService->sendToRole($targetRole, $news->school_id, $title, $body, $data);
        }
    }

    /**
     * Handle the News "deleted" event.
     */
    public function deleted(News $news): void
    {
        //
    }

    /**
     * Handle the News "restored" event.
     */
    public function restored(News $news): void
    {
        //
    }

    /**
     * Handle the News "force deleted" event.
     */
    public function forceDeleted(News $news): void
    {
        //
    }
}
