<?php

namespace App\Observers;

use App\Models\Bill;

class BillObserver
{
    /**
     * Handle the Bill "created" event.
     */
    public function created(Bill $bill): void
    {
        $bill->load(['student.parent', 'category']);
        
        $parent = $bill->student->parent;
        
        if ($parent && $parent->fcm_token) {
            $fcmService = app(\App\Services\FcmService::class);
            $title = "Tagihan Baru: " . ($bill->category->name ?? 'Pembayaran');
            $body = "Halo, ada tagihan baru untuk " . $bill->student->full_name . " sebesar Rp " . number_format($bill->amount, 0, ',', '.') . ".";
            
            $fcmService->sendToUser($parent, $title, $body, [
                'type' => 'bill',
                'id' => $bill->id,
            ]);
        }
    }

    /**
     * Handle the Bill "updated" event.
     */
    public function updated(Bill $bill): void
    {
        //
    }

    /**
     * Handle the Bill "deleted" event.
     */
    public function deleted(Bill $bill): void
    {
        //
    }

    /**
     * Handle the Bill "restored" event.
     */
    public function restored(Bill $bill): void
    {
        //
    }

    /**
     * Handle the Bill "force deleted" event.
     */
    public function forceDeleted(Bill $bill): void
    {
        //
    }
}
