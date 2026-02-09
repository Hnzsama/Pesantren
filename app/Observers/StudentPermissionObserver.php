<?php

namespace App\Observers;

use App\Models\StudentPermission;

class StudentPermissionObserver
{
    /**
     * Handle the StudentPermission "created" event.
     */
    public function created(StudentPermission $studentPermission): void
    {
        $studentPermission->load(['student.parent']);
        
        $parent = $studentPermission->student->parent;
        
        if ($parent && $parent->fcm_token) {
            $fcmService = app(\App\Services\FcmService::class);
            $title = "Pengajuan Izin: " . $studentPermission->student->full_name;
            $body = "Halo, ada pengajuan izin baru untuk " . $studentPermission->student->full_name . " (" . $studentPermission->reason . ").";
            
            $fcmService->sendToUser($parent, $title, $body, [
                'type' => 'permission',
                'id' => $studentPermission->id,
            ]);
        }
    }

    /**
     * Handle the StudentPermission "updated" event.
     */
    public function updated(StudentPermission $studentPermission): void
    {
        if ($studentPermission->wasChanged('status')) {
            $studentPermission->load(['student.parent']);
            $parent = $studentPermission->student->parent;
            
            if ($parent && $parent->fcm_token) {
                $statusIndo = match($studentPermission->status) {
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => $studentPermission->status
                };
                
                $fcmService = app(\App\Services\FcmService::class);
                $title = "Status Izin: " . $statusIndo;
                $body = "Pengajuan izin " . $studentPermission->student->full_name . " (" . $studentPermission->reason . ") telah " . strtolower($statusIndo) . ".";
                
                $fcmService->sendToUser($parent, $title, $body, [
                    'type' => 'permission',
                    'id' => $studentPermission->id,
                ]);
            }
        }
    }

    /**
     * Handle the StudentPermission "deleted" event.
     */
    public function deleted(StudentPermission $studentPermission): void
    {
        //
    }

    /**
     * Handle the StudentPermission "restored" event.
     */
    public function restored(StudentPermission $studentPermission): void
    {
        //
    }

    /**
     * Handle the StudentPermission "force deleted" event.
     */
    public function forceDeleted(StudentPermission $studentPermission): void
    {
        //
    }
}
