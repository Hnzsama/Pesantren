<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get IDs of students belonging to this parent
        $studentIds = $user->students()->pluck('id')->toArray();

        $query = WalletTransaction::query()
            ->with(['student:id,full_name,school_id']) // Eager load student
            ->whereIn('student_id', $studentIds); // Security: Only show own students

        // Filter by specific Student ID (must be one of the parent's students)
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by Type (CREDIT / DEBIT)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by Category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        // Filter by Date Range
        if ($request->has('start_date') && $request->has('end_date')) {
             $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $transactions = $query->latest()->paginate(10);

        return response()->json($transactions);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = WalletTransaction::with(['student:id,full_name,school_id'])->findOrFail($id);
        
        $user = request()->user();
        // Security check: ensure student belongs to parent
        // Just check if the student associated with transaction is one of the user's students
        $isMyStudent = $user->students()->where('id', $transaction->student_id)->exists();

        if (! $isMyStudent) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $transaction->id,
            'invoice_code' => $transaction->invoice_code,
            'school_id' => $transaction->school_id,
            'student_id' => $transaction->student_id,
            'student_name' => $transaction->student->full_name,
            'type' => $transaction->type,
            'category' => $transaction->category,
            'amount' => $transaction->amount,
            'admin_fee' => $transaction->admin_fee,
            'payment_fee' => $transaction->payment_fee,
            'description' => $transaction->description,
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
        ]);
    }

    public function cancel(string $id)
    {
        $transaction = WalletTransaction::with('student', 'pic')->findOrFail($id);

        $user = request()->user();
        
        // Check if user is the PIC (Creator) or if the transaction belongs to one of their students
        $isCreator = $transaction->pic_user_id === $user->id;
        $isParent = $user->students()->where('id', $transaction->student_id)->exists();

        if (! $isCreator && ! $isParent) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($transaction->status !== 'UNPAID') {
            return response()->json(['message' => 'Cannot cancel transaction with status: ' . $transaction->status], 400); 
        }

        $transaction->status = 'CANCELLED';
        $transaction->description = $transaction->description . " (Dibatalkan User)";
        $transaction->save();
        
        return response()->json(['message' => 'Transaction cancelled', 'transaction' => $transaction]);
    }
}

/*
|--------------------------------------------------------------------------
| API Documentation (Frontend)
|--------------------------------------------------------------------------
|
| Endpoint: GET /api/transactions
| Middleware: auth:sanctum
|
| user yang login (wali_santri) hanya bisa melihat transaksi dari siswa yang terkait dengannya.
|
| Parameters (Query Strings):
| 1. student_id (Optional)
|    - Filter berdasarkan ID santri spesifik.
|    - Contoh: ?student_id=01hk...
|
| 2. type (Optional)
|    - Filter berdasarkan tipe transaksi.
|    - Values: 'CREDIT' (Pemasukan/Topup), 'DEBIT' (Pengeluaran/Belanja)
|    - Contoh: ?type=CREDIT
|
| 3. category (Optional)
|    - Filter berdasarkan kategori.
|    - Values: 'TOPUP', 'WITHDRAWAL', 'PURCHASE', 'DEPOSIT_TO_HQ'
|    - Contoh: ?category=PURCHASE
|
| 4. start_date & end_date (Optional)
|    - Filter berdasarkan rentang tanggal.
|    - Format: YYYY-MM-DD
|    - Contoh: ?start_date=2026-01-01&end_date=2026-01-31
|
| 5. page (Optional)
|    - Pagination.
|    - Contoh: ?page=2
|
| Example Response (200 OK):
| {
|     "current_page": 1,
|     "data": [
|         {
|             "id": "01hk...",
|             "invoice_code": "INV/...",
|             "student_id": "01hk...",
|             "type": "DEBIT",
|             "category": "PURCHASE",
|             "amount": "50000.00",
|             "description": "Jajan di Kantin",
|             "payment_method": "balance",
|             "created_at": "2026-01-24T10:00:00.000000Z",
|             "updated_at": "2026-01-24T10:00:00.000000Z",
|             "student": {
|                 "id": "01hk...",
|                 "full_name": "Ahmad"
|             }
|         }
|     ],
|     ... (Pagination Metadata)
| }
|
|--------------------------------------------------------------------------
| ALUR TOPUP UANG SAKU (MIDTRANS)
|--------------------------------------------------------------------------
|
| 1. Request Snap Token (Frontend)
|    Endpoint: GET /api/midtrans/snap-token
|    Headers: Authorization: Bearer <token>
|    Query Params:
|      - amount: int (Required, min 1000)
|      - student_id: string ULID (Required, ID santri yang mau diisi)
|      - payment_type: string (Optional, e.g., 'qris', 'bank_transfer', 'other_va')
|    
|    Response:
|    {
|      "status": "success",
|      "snap_token": "midtrans-snap-token...",
|      "order_id": "INV-123456...",
|      "transaction_id": "01hk..." (ID WalletTransaction status PENDING)
|    }
|
| 2. Proses Pembayaran (Frontend)
|    - Gunakan snap_token di Midtrans SDK (Mobile/Web).
|    - User menyelesaikan pembayaran.
|
| 3. Callback / Webhook (Backend Automatis)
|    Endpoint: POST /api/midtrans/callback
|    - Midtrans mengirim notifikasi ke backend.
|    - Backend memverifikasi signature.
|    - Jika status 'settlement' / 'capture':
|      a. Update status WalletTransaction jadi 'SUCCESS'.
|      b. Tambahkan saldo ke table `students.uang_saku`.
|    - Jika status 'expire' / 'cancel':
|      a. Update status WalletTransaction jadi 'FAILED'.
|
*/
