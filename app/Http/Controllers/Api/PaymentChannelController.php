<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\PaymentChannel;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PaymentChannelController extends Controller
{
    /**
     * Get list of active payment channels and global admin fee
     */
    public function index(): JsonResponse
    {
        $adminFee = (int) Setting::getGlobal('midtrans_admin_fee', 0);
        
        $channels = PaymentChannel::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function ($channel) {
                return [
                    'code' => $channel->code,
                    'name' => $channel->name,
                    'type' => $channel->type,
                    'fee_flat' => (int) $channel->fee_flat,
                    'fee_percent' => (float) $channel->fee_percent,
                    'icon_url' => $channel->icon_url ?? null, // Assuming this might exist or be added later
                ];
            });

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Payment channels retrieved successfully',
            ],
            'data' => [
                'admin_fee' => $adminFee,
                'channels' => $channels,
            ],
        ]);
    }
}
