<?php

namespace App\Observers;

use App\Models\StudentReport;

class StudentReportObserver
{
    /**
     * Handle the StudentReport "created" event.
     */
    public function created(StudentReport $studentReport): void
    {
        $studentReport->load(['student.parent', 'semester']);
        
        $parent = $studentReport->student->parent;
        
        if ($parent && $parent->fcm_token) {
            $fcmService = app(\App\Services\FcmService::class);
            $semesterName = $studentReport->semester->name ?? 'Terbaru';
            $title = "Rapor Baru: Semester " . $semesterName;
            $body = "Alhamdulillah, rapor ananda " . $studentReport->student->full_name . " untuk semester ini telah tersedia. Silakan cek aplikasi.";
            
            $fcmService->sendToUser($parent, $title, $body, [
                'type' => 'report',
                'id' => $studentReport->id,
            ]);
        }
    }

    /**
     * Handle the StudentReport "updated" event.
     */
    public function updated(StudentReport $studentReport): void
    {
        //
    }

    /**
     * Handle the StudentReport "deleted" event.
     */
    public function deleted(StudentReport $studentReport): void
    {
        //
    }

    /**
     * Handle the StudentReport "restored" event.
     */
    public function restored(StudentReport $studentReport): void
    {
        //
    }

    /**
     * Handle the StudentReport "force deleted" event.
     */
    public function forceDeleted(StudentReport $studentReport): void
    {
        //
    }
}
