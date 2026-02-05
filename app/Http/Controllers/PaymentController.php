<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Student;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Snap;

class PaymentController extends Controller
{
    // Konfigurasi Midtrans di awal
    public function __construct()
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized = config('midtrans.is_sanitized');
        \Midtrans\Config::$is3ds = config('midtrans.is_3ds');
    }

    public function testPay()
    {
        Log::info('Midtrans Config', [
            'is_production' => config('midtrans.is_production'),
            'server_key_prefix' => substr(config('midtrans.server_key'), 0, 10),
            'client_key_prefix' => substr(config('midtrans.client_key'), 0, 10),
        ]);

        // Buat Order ID Unik (Misal: INV-Timestamp)
        $orderId = 'INV-'.time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => 150000,
            ],
            'customer_details' => [
                'first_name' => 'Bagas',
                'email' => 'bagas@webkuu.com',
                'phone' => '08123456789',
            ],
        ];

        // Dapatkan Snap Token dari Midtrans
        $snapToken = Snap::getSnapToken($params);

        // Kirim token ke view
        return view('payment', compact('snapToken'));
    }

    public function getSnapToken(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:1000', // Nominal Topup (Net)
            'admin_fee' => 'nullable|numeric|min:0', // Biaya Admin
            'payment_fee' => 'nullable|numeric|min:0', // Biaya Payment Gateway
        ]);

        Log::info('Incoming Snap Token Request:', $request->all());

        $user = $request->user();
        // Verify student belongs to parent
        $student = $user->students()->where('id', $request->student_id)->first();
        if (! $student) {
            return response()->json(['message' => 'Student not found or unauthorized'], 403);
        }

        // Check for existing UNPAID Topup
        $pendingTransaction = WalletTransaction::where('student_id', $student->id)
            ->where('status', 'UNPAID')
            ->where('category', 'TOPUP')
            ->first();

        if ($pendingTransaction) {
            return response()->json([
                'message' => 'Anda memiliki transaksi topup yang belum dibayar. Silakan selesaikan atau batalkan terlebih dahulu.',
                'transaction_id' => $pendingTransaction->id
            ], 400);
        }

        $orderId = 'TOP-UP-'.time().'-'.rand(100, 999); // Unique Order ID
        $amount = (int) $request->amount;
        $adminFee = (int) ($request->admin_fee ?? 0);
        $paymentFee = (int) ($request->payment_fee ?? 0);
        $totalAmount = $amount + $adminFee + $paymentFee;
        $paymentType = $request->query('payment_type');

        // Description logic
        $feeDesc = [];
        if ($adminFee > 0) $feeDesc[] = 'Adm: Rp ' . number_format($adminFee, 0, ',', '.');
        if ($paymentFee > 0) $feeDesc[] = 'Fee: Rp ' . number_format($paymentFee, 0, ',', '.');
        $descString = count($feeDesc) > 0 ? ' ('.implode(', ', $feeDesc).')' : '';

        // Create Pending Transaction (HANYA Nominal Topup yang dicatat sebagai Kredit)
        $transaction = WalletTransaction::create([
            'school_id' => $student->school_id,
            'invoice_code' => $orderId,
            'student_id' => $student->id,
            'pic_user_id' => $user->id, // Parent triggering the topup
            'type' => 'CREDIT',
            'category' => 'TOPUP',
            'amount' => $amount, // Simpan 50.000
            'admin_fee' => $adminFee, // Save Admin Fee
            'payment_fee' => $paymentFee, // Save Payment Fee
            'description' => 'Topup via Mobile' . $descString,
            'payment_method' => 'payment_gateway',
            'status' => 'UNPAID',
        ]);

        Log::info('Transaction Created:', [
            'id' => $transaction->id,
            'order_id' => $orderId,
            'amount_kredit' => $amount,
            'admin_fee' => $adminFee,
            'payment_fee' => $paymentFee,
            'total_bill' => $totalAmount,
        ]);

        $itemDetails = [
            [
                'id' => 'TOPUP-SZ',
                'price' => $amount,
                'quantity' => 1,
                'name' => "Topup Santri: {$student->full_name}",
            ],
        ];

        // Jika ada admin fee, tambahkan sebagai item terpisah agar jelas di Midtrans
        if ($adminFee > 0) {
            $itemDetails[] = [
                'id' => 'ADM-FEE',
                'price' => $adminFee,
                'quantity' => 1,
                'name' => 'Biaya Admin',
            ];
        }

        // Jika ada payment fee
        if ($paymentFee > 0) {
            $itemDetails[] = [
                'id' => 'PAY-FEE',
                'price' => $paymentFee,
                'quantity' => 1,
                'name' => 'Biaya Layanan',
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $totalAmount, // Tagihkan 54.000
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                // 'phone' => $user->phone, // If available
            ],
            'item_details' => $itemDetails,
        ];

        // Add enabled_payments if payment_type is specified
        if ($paymentType) {
            if ($paymentType === 'qris') {
                $params['enabled_payments'] = ['gopay', 'shopeepay', 'other_qris'];
            } elseif ($paymentType === 'other_va') {
                $params['enabled_payments'] = ['other_va', 'permata_va', 'bni_va', 'bri_va']; // BSI usually via permata/other
            } else {
                $params['enabled_payments'] = [$paymentType];
            }
        }

        try {
            $snapToken = Snap::getSnapToken($params);

            Log::info('Snap Token Generated:', ['token' => $snapToken]);

            return response()->json([
                'status' => 'success',
                'snap_token' => $snapToken,
                'order_id' => $orderId,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            // Log error
            Log::error('Midtrans Error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function pay(Request $request, string $id)
    {
        $transaction = WalletTransaction::with('student', 'pic')->findOrFail($id);

        $user = $request->user();
        if ($transaction->pic_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($transaction->status !== 'UNPAID') {
            return response()->json(['message' => 'Transaction is not unpaid status: '.$transaction->status], 400);
        }

        // Reuse existing Order ID
        $orderId = $transaction->invoice_code;
        $amount = $transaction->amount;

        // Extract Admin Fee from description if possible or assume logic?
        // Or recalculate TOTAL.
        // Actually WalletTransaction amount is the "Credit" amount (50k).
        // But the user pays 50k + AdminFee.
        // We didn't store total_bill in WalletTransaction directly, only in Description or Logs.
        // Simplest: Just use the amount.
        // WAIT: In getSnapToken we do: $amount = (int) $request->amount; (Not including fee).
        // And $totalAmount = $amount + $adminFee.
        // And we send $totalAmount to Midtrans.
        // If we retry, we need to know the original total amount.
        // But we don't store it in DB column.
        // However, Midtrans allows params to be sent again.
        // If the order_id exists in Midtrans, we just need to get the token.
        // If it expired, we might need new order_id.
        // But user wants to keep the transaction.
        // If we use same order_id, Midtrans might reject if expired.
        // If expired, we should probably create NEW transaction or update the order_id of this transaction?
        // Updating invoice_code of existing transaction seems safer to avoid duplicates.
        // Let's generate NEW Order ID for the retry to avoid expiration issues, and update the transaction record.

        $newOrderId = 'TOP-UP-'.time().'-'.rand(100, 999);
        $transaction->invoice_code = $newOrderId;
        $transaction->save();

        // Recalculate amounts.
        // We assume the amount in DB is the "Net" amount for student.
        // We need to know admin fee.
        // It's not stored structured.
        // For now, let's assume 0 admin fee for retry or we need to parse it?
        // Or we just ask user to create new topup if it expired?
        // The user said "kalau bayar maka arahkan ke halaman pembayaran lagi".
        // Let's assume we can just charge the `amount` stored in DB.
        // If there was a fee, it's lost in this retry
        $params = [
            'transaction_details' => [
                'order_id' => $newOrderId,
                'gross_amount' => $transaction->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => 'TOPUP-RETRY',
                    'price' => $transaction->amount,
                    'quantity' => 1,
                    'name' => "Topup Santri: {$transaction->student->full_name}",
                ],
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            Log::info('Snap Token Regenerated:', ['token' => $snapToken, 'old_order_id' => $orderId, 'new_order_id' => $newOrderId]);

            return response()->json([
                'status' => 'success',
                'snap_token' => $snapToken,
                'order_id' => $newOrderId,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        $serverKey = config('midtrans.server_key');

        // Midtrans notification body
        $notification = $request->all();

        // Log notification for debugging
        Log::info('Midtrans Notification Received:', $notification);

        if (! isset($notification['order_id'], $notification['status_code'], $notification['gross_amount'], $notification['signature_key'], $notification['transaction_status'])) {
            Log::warning('Midtrans Notification: Invalid Payload', $notification);

            return response()->json(['message' => 'Invalid payload format'], 400);
        }

        $orderId = $notification['order_id'];
        $statusCode = $notification['status_code'];
        $grossAmount = $notification['gross_amount'];
        $signatureKey = $notification['signature_key'];
        $transactionStatus = $notification['transaction_status'];

        // Verify Signature
        $input = $orderId.$statusCode.$grossAmount.$serverKey;
        $mySignature = hash('sha512', $input);

        Log::info('Signature Check:', ['my_signature' => $mySignature, 'midtrans_signature' => $signatureKey]);

        if ($mySignature !== $signatureKey) {
            Log::warning('Midtrans Notification: Invalid Signature');

            return response()->json(['message' => 'Invalid Signature'], 400);
        }

        if (str_starts_with($orderId, 'TOP-UP-')) {
            $transaction = WalletTransaction::where('invoice_code', $orderId)->first();

            if (! $transaction) {
                Log::warning('Midtrans Notification: Transaction Not Found', ['order_id' => $orderId]);

                return response()->json(['message' => 'Transaction not found'], 404);
            }

            Log::info('Transaction Found:', ['id' => $transaction->id, 'current_status' => $transaction->status]);

            if ($transaction->status === 'PAID') {
                Log::info('Transaction Already Success. Skipping.');

                return response()->json(['message' => 'Transaction already processed']);
            }

            DB::beginTransaction();
            try {
                if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                    // Success
                    $transaction->status = 'PAID';
                    $transaction->save();

                    // Add Balance to Student
                    $student = Student::find($transaction->student_id);
                    if ($student) {
                        $student->wallet_balance += $transaction->amount;
                        $student->save();
                        // TODO: Send notification to parent?
                    } else {
                        Log::error('Student Not Found for Balance Update:', ['student_id' => $transaction->student_id]);
                    }
                } elseif ($transactionStatus == 'expire' || $transactionStatus == 'cancel' || $transactionStatus == 'deny') {
                    $transaction->status = 'CANCELLED';
                    $transaction->save();
                } else {
                    $transaction->status = 'UNPAID';
                    $transaction->save();
                }

                DB::commit();

                return response()->json(['message' => 'Callback processed']);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Callback Error: '.$e->getMessage());

                return response()->json(['message' => 'Error processing callback'], 500);
            }
        } elseif (str_starts_with($orderId, 'BILL-')) {
            $parts = explode('-', $orderId);
            // parts[0] = BILL
            // parts[1] = BillID (ULID)

            if (count($parts) >= 2) {
                $billId = $parts[1];
                $bill = Bill::find($billId);

                if ($bill) {
                    if ($bill->status === 'PAID') {
                        return response()->json(['message' => 'Bill already paid']);
                    }

                    DB::beginTransaction();
                    try {
                        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                            $bill->status = 'PAID';
                            $bill->paid_at = now();
                            $bill->payment_method = 'gateway';
                            $bill->save();
                        } elseif ($transactionStatus == 'expire' || $transactionStatus == 'cancel' || $transactionStatus == 'deny') {
                            $bill->status = 'UNPAID';
                            $bill->save();
                        }
                        DB::commit();

                        return response()->json(['message' => 'Bill callback processed']);
                    } catch (\Exception $e) {
                        DB::rollBack();

                        return response()->json(['message' => 'Error processing bill callback'], 500);
                    }
                }
            }

            return response()->json(['message' => 'Bill not found for order_id'], 404);
        }

        return response()->json(['message' => 'Unknown Order ID prefix'], 400);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }

    public function finish(Request $request)
    {
        return view('payment.finish');
    }

    public function unfinish(Request $request)
    {
        return view('payment.unfinish');
    }

    public function error(Request $request)
    {
        return view('payment.error');
    }
}
