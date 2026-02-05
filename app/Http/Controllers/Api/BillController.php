<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillController extends Controller
{
    public function __construct()
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized = config('midtrans.is_sanitized');
        \Midtrans\Config::$is3ds = config('midtrans.is_3ds');
    }

    /**
     * Get bills for a specific student.
     * Only students belonging to the authenticated parent can be queried.
     */
    public function index(Request $request)
    {
        $request->validate([
            'student_id' => 'required|ulid',
        ]);

        $user = $request->user();

        // Verify the student belongs to this parent
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($request->student_id, $studentIds)) {
            return response()->json(['message' => 'Student not found or unauthorized.'], 403);
        }

        // Fetch bills with relationships
        $bills = Bill::query()
            ->with(['category:id,name,description', 'event:id,title,event_date,amount'])
            ->where('student_id', $request->student_id)
            ->latest('due_date')
            ->get();

        // Format the response to match Flutter app expectations
        $formattedBills = $bills->map(function ($bill) {
            return [
                'id' => $bill->id,
                'student_id' => $bill->student_id,
                'title' => $bill->title,
                'amount' => $bill->amount,
                'admin_fee' => $bill->admin_fee,
                'payment_fee' => $bill->payment_fee,
                'status' => $bill->status,
                'due_date' => $bill->due_date->format('Y-m-d'),
                'paid_at' => $bill->paid_at?->format('Y-m-d H:i:s'),
                'payment_method' => $bill->payment_method,
                'category' => $bill->category ? [
                    'id' => $bill->category->id,
                    'name' => $bill->category->name,
                    'description' => $bill->category->description,
                ] : null,
                'event' => $bill->event ? [
                    'id' => $bill->event->id,
                    'title' => $bill->event->title,
                    'event_date' => $bill->event->event_date->format('Y-m-d'),
                    'amount' => $bill->event->amount,
                ] : null,
            ];
        });

        return response()->json($formattedBills);
    }

    public function show($id)
    {
        $bill = Bill::with(['category', 'event', 'student'])->findOrFail($id);

        $user = request()->user();
        if (! $user->students()->where('id', $bill->student_id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $bill->id,
            'student_id' => $bill->student_id,
            'title' => $bill->title,
            'amount' => $bill->amount,
            'admin_fee' => $bill->admin_fee,
            'payment_fee' => $bill->payment_fee,
            'status' => $bill->status,
            'due_date' => $bill->due_date->format('Y-m-d'),
            'paid_at' => $bill->paid_at?->format('Y-m-d H:i:s'),
            'payment_method' => $bill->payment_method,
            'bill_code' => $bill->bill_code,
            'category' => $bill->category ? [
                'id' => $bill->category->id,
                'name' => $bill->category->name,
                'description' => $bill->category->description,
            ] : null,
            'event' => $bill->event ? [
                'id' => $bill->event->id,
                'title' => $bill->event->title,
                'event_date' => $bill->event->event_date->format('Y-m-d'),
                'amount' => $bill->event->amount,
            ] : null,
        ]);
    }

    public function pay(Request $request, $id)
    {
        $request->validate([
            'admin_fee' => 'nullable|numeric|min:0',
            'payment_fee' => 'nullable|numeric|min:0',
            'payment_type' => 'nullable|string',
        ]);

        $bill = Bill::with('student')->findOrFail($id);
        $user = $request->user();

        if (! $user->students()->where('id', $bill->student_id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($bill->status === 'PAID') {
            return response()->json(['message' => 'Bill already paid'], 400);
        }

        // Generate Order ID for Midtrans: BILL-{ulid}-{time}
        $orderId = 'BILL-'.$bill->id.'-'.time();

        $amount = (int) $bill->amount;
        $adminFee = (int) ($request->admin_fee ?? 0);
        $paymentFee = (int) ($request->payment_fee ?? 0);
        $totalAmount = $amount + $adminFee + $paymentFee;
        $paymentType = $request->payment_type;

        // Log Requests for Debugging
        Log::info('Bill Pay Request:', [
            'bill_id' => $bill->id,
            'amount' => $amount,
            'admin_fee' => $adminFee,
            'payment_fee' => $paymentFee,
            'total_amount' => $totalAmount,
            'payment_type' => $paymentType
        ]);

        // Update Bill details BEFORE generating snap token
        // so we preserve the fees even if they pay later
        $bill->update([
            'admin_fee' => $adminFee,
            'payment_fee' => $paymentFee,
            'payment_method' => $paymentType, // Store intended payment method
        ]);

        $itemDetails = [
            [
                'id' => $bill->bill_code ?? 'BILL-'.$bill->id,
                'price' => $amount,
                'quantity' => 1,
                'name' => 'Tagihan: '.substr($bill->title, 0, 40),
            ],
        ];

        if ($adminFee > 0) {
            $itemDetails[] = [
                'id' => 'ADM-FEE',
                'price' => $adminFee,
                'quantity' => 1,
                'name' => 'Biaya Admin',
            ];
        }

        if ($paymentFee > 0) {
            $itemDetails[] = [
                'id' => 'PAY-FEE',
                'price' => $paymentFee,
                'quantity' => 1,
                'name' => 'Biaya Layanan',
            ];
        }

        // Setup Params
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $totalAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => $itemDetails,
        ];

        // Add enabled_payments if payment_type is specified
        if ($paymentType) {
            if ($paymentType === 'qris') {
                $params['enabled_payments'] = ['gopay', 'shopeepay', 'other_qris'];
            } elseif ($paymentType === 'other_va') {
                $params['enabled_payments'] = ['other_va', 'permata_va', 'bni_va', 'bri_va'];
            } else {
                $params['enabled_payments'] = [$paymentType];
            }
        }

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            return response()->json([
                'status' => 'success',
                'snap_token' => $snapToken,
                'order_id' => $orderId,
                'bill_id' => $bill->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

/*
|--------------------------------------------------------------------------
| API Documentation (Frontend)
|--------------------------------------------------------------------------
|
| Endpoint: GET /api/bills
| Middleware: auth:sanctum
|
| Mengambil daftar tagihan untuk santri tertentu.
| User yang login (wali_santri) hanya bisa melihat tagihan santri yang terkait dengannya.
|
| Parameters (Query Strings):
| 1. student_id (Required)
|    - ID santri yang ingin dicek tagihannya.
|    - Contoh: ?student_id=01hk...
|
| Example Response (200 OK):
| [
|     {
|         "id": "01hk...",
|         "student_id": "01hk...",
|         "title": "SPP Januari 2026",
|         "amount": "350000.00",
|         "status": "UNPAID",
|         "due_date": "2026-01-10",
|         "paid_at": null,
|         "payment_method": null,
|         "category": {
|             "id": "01hk...",
|             "name": "SPP",
|             "description": "Sumbangan Pembinaan Pendidikan"
|         },
|         "event": null
|     },
|     {
|         "id": "01hk...",
|         "student_id": "01hk...",
|         "title": "Biaya Transportasi & Akomodasi",
|         "amount": "1500000.00",
|         "status": "UNPAID",
|         "due_date": "2026-02-01",
|         "paid_at": null,
|         "payment_method": null,
|         "category": {
|             "id": "01hk...",
|             "name": "Lainnya"
|         },
|         "event": {
|             "id": "01hk...",
|             "title": "Ziarah Wali Songo",
|             "event_date": "2026-02-15",
|             "amount": "1500000.00"
|         }
|     }
| ]
|
| Status Values:
| - UNPAID: Belum dibayar
| - PAID: Sudah dibayar
| - CANCELLED: Dibatalkan
|
*/
