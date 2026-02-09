<?php

namespace App\Observers;

use App\Models\SowanSchedule;

class SowanScheduleObserver
{
    /**
     * Handle the SowanSchedule "created" event.
     */
    public function created(SowanSchedule $sowanSchedule): void
    {
        //
    }

    public function updated(SowanSchedule $sowanSchedule): void
    {
        \Illuminate\Support\Facades\Log::info('SowanScheduleObserver: updated event fired', ['id' => $sowanSchedule->id, 'changes' => $sowanSchedule->getChanges()]);

        if ($sowanSchedule->wasChanged('status')) {
            $sowanSchedule->load(['user']);
            $user = $sowanSchedule->user;

            \Illuminate\Support\Facades\Log::info('SowanScheduleObserver: status changed', [
                'status' => $sowanSchedule->status,
                'user_id' => $sowanSchedule->user_id,
                'has_user' => boolval($user),
                'fcm_token' => $user ? $user->fcm_token : 'N/A',
            ]);

            if ($user && $user->fcm_token) {
                $statusIndo = match ($sowanSchedule->status) {
                    'confirmed' => 'Dikonfirmasi',
                    'completed' => 'Selesai',
                    'rejected' => 'Ditolak',
                    default => $sowanSchedule->status
                };

                $fcmService = app(\App\Services\FcmService::class);
                $title = 'Status Sowan: '.$statusIndo;
                $body = 'Pengajuan sowan Anda untuk tanggal '.$sowanSchedule->request_date->format('d M Y').' telah '.strtolower($statusIndo).'.';

                $fcmService->sendToUser($user, $title, $body, [
                    'type' => 'sowan',
                    'id' => $sowanSchedule->id,
                ]);
            }
        }
    }

    /**
     * Handle the SowanSchedule "deleted" event.
     */
    public function deleted(SowanSchedule $sowanSchedule): void
    {
        //
    }

    /**
     * Handle the SowanSchedule "restored" event.
     */
    public function restored(SowanSchedule $sowanSchedule): void
    {
        //
    }

    /**
     * Handle the SowanSchedule "force deleted" event.
     */
    public function forceDeleted(SowanSchedule $sowanSchedule): void
    {
        //
    }
}
