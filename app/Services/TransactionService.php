<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransactionService
{
    /**
     * Validate withdrawal request against all security rules
     * 
     * @throws \Exception
     */
    public function validateWithdrawal(Student $student, float $amount, string $pin): void
    {
        // 1. Check if transactions are allowed for this student
        if (!$student->allow_transaction) {
            throw new \Exception('Transaksi diblokir. Hubungi admin untuk aktivasi akun.');
        }

        // 2. Verify PIN
        if (!$student->wallet_pin) {
            throw new \Exception('PIN belum diatur. Silakan hubungi admin untuk setup PIN.');
        }

        if (!Hash::check($pin, $student->wallet_pin)) {
            throw new \Exception('PIN salah. Periksa kembali PIN Anda.');
        }

        // 3. Check balance sufficiency
        if ($student->wallet_balance < $amount) {
            throw new \Exception('Saldo tidak cukup. Saldo Anda: Rp ' . number_format($student->wallet_balance, 0, ',', '.'));
        }

        // 4. Check daily withdrawal limit
        $todaysWithdrawals = $this->getTodaysWithdrawalSum($student);
        $remainingLimit = $student->daily_cash_limit - $todaysWithdrawals;

        if ($amount > $remainingLimit) {
            throw new \Exception(
                'Melebihi limit tarik tunai harian. ' .
                'Sisa limit: Rp ' . number_format($remainingLimit, 0, ',', '.') . '. ' .
                'Total yang sudah ditarik hari ini: Rp ' . number_format($todaysWithdrawals, 0, ',', '.')
            );
        }

        // 5. Minimum withdrawal check
        if ($amount < 1000) {
            throw new \Exception('Nominal penarikan minimal Rp 1.000');
        }
    }

    /**
     * Process cash withdrawal transaction
     * 
     * @throws \Exception
     */
    /**
     * Process cash withdrawal transaction
     * 
     * @throws \Exception
     */
    public function processWithdrawal(Student $student, float $amount, User $admin): WalletTransaction
    {
        return DB::transaction(function () use ($student, $amount, $admin) {
            // Deduct student balance
            $student->decrement('wallet_balance', $amount);

            // Create transaction record
            $transaction = WalletTransaction::create([
                'school_id' => $student->school_id,
                'invoice_code' => 'WITHDRAW-' . strtoupper(uniqid()),
                'student_id' => $student->id,
                'pic_user_id' => $admin->id,
                'operator_id' => $admin->id, // Set operator ID
                'type' => 'DEBIT',
                'category' => 'WITHDRAWAL', // New category
                'amount' => $amount,
                'payment_method' => 'cash', // Standardized enum
                'status' => 'PAID',
                'description' => 'Penarikan Tunai - Teller: ' . $admin->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Process cash topup transaction
     * 
     * @throws \Exception
     */
    public function processTopup(Student $student, float $amount, User $admin): WalletTransaction
    {
        return DB::transaction(function () use ($student, $amount, $admin) {
            // Credit student balance
            $student->increment('wallet_balance', $amount);

            // Create transaction record
            $transaction = WalletTransaction::create([
                'school_id' => $student->school_id,
                'invoice_code' => 'TOPUP-' . strtoupper(uniqid()),
                'student_id' => $student->id,
                'pic_user_id' => $admin->id,
                'operator_id' => $admin->id,
                'type' => 'CREDIT',
                'category' => 'TOPUP',
                'amount' => $amount,
                'payment_method' => 'cash',
                'status' => 'PAID',
                'description' => 'Topup Tunai - Teller: ' . $admin->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Process Deposit to HQ (Setor Tunai ke Pusat)
     * Resets/Decreases Admin's Cash Liability
     */
    public function processDepositToHQ(User $admin, float $amount): WalletTransaction
    {
        return DB::transaction(function () use ($admin, $amount) {
            // Create transaction record - No Student involved!
            $transaction = WalletTransaction::create([
                'school_id' => $admin->school_id,
                'invoice_code' => 'DEPOSIT-' . strtoupper(uniqid()),
                'student_id' => null, // Allowed by new migration
                'pic_user_id' => $admin->id,
                'operator_id' => $admin->id,
                'type' => 'DEBIT', // Money leaving local admin
                'category' => 'DEPOSIT_TO_HQ',
                'amount' => $amount,
                'payment_method' => 'cash',
                'status' => 'PAID',
                'description' => 'Setor Tunai ke Pusat - Admin: ' . $admin->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Get remaining daily withdrawal limit for student
     */
    public function getRemainingDailyLimit(Student $student): float
    {
        $todaysWithdrawals = $this->getTodaysWithdrawalSum($student);
        $remaining = $student->daily_cash_limit - $todaysWithdrawals;
        
        return max(0, $remaining); // Never return negative
    }

    /**
     * Get sum of today's cash withdrawals
     */
    public function getTodaysWithdrawalSum(Student $student): float
    {
        return WalletTransaction::where('student_id', $student->id)
            ->where('type', 'DEBIT')
            ->where('payment_method', 'cash')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
    }

    /**
     * Get today's withdrawal transactions
     */
    public function getTodaysWithdrawals(Student $student)
    {
        return WalletTransaction::where('student_id', $student->id)
            ->where('type', 'DEBIT')
            ->where('payment_method', 'cash')
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Generate default PIN from student's birth date (DDMMYY)
     */
    public function generateDefaultPin(Student $student): string
    {
        if ($student->birth_date) {
            return $student->birth_date->format('dmy'); // Format: 010199 for Jan 1, 1999
        }
        
        // Fallback to generic PIN if no birth date
        return '123456';
    }

    /**
     * Reset student PIN to default
     */
    public function resetStudentPin(Student $student): string
    {
        $defaultPin = $this->generateDefaultPin($student);
        
        $student->update([
            'wallet_pin' => Hash::make($defaultPin)
        ]);

        return $defaultPin; // Return plaintext for admin to communicate
    }

    /**
     * Process POS purchase transaction with optional items and stock deduction
     * 
     * @throws \Exception
     */
    public function processPurchase(?Student $student, float $amount, ?string $pin, User $operator, array $items = [], string $paymentMethod = 'wallet'): WalletTransaction
    {
        return DB::transaction(function () use ($student, $amount, $pin, $operator, $items, $paymentMethod) {
            // 1. Validations if student is provided
            if ($student) {
                if (!$student->allow_transaction) {
                    throw new \Exception('Transaksi diblokir. Hubungi admin untuk aktivasi akun.');
                }

                // PIN check only if using wallet
                if ($paymentMethod === 'wallet') {
                    if (!$student->wallet_pin) {
                        throw new \Exception('PIN belum diatur. Silakan hubungi admin untuk setup PIN.');
                    }

                    if (!Hash::check($pin, $student->wallet_pin)) {
                        throw new \Exception('PIN salah. Periksa kembali PIN Anda.');
                    }

                    if (($student->wallet_balance - $amount) < 10000) {
                        throw new \Exception('Saldo minimal Rp 10.000 harus tersisa di dompet. Saldo tersedia: Rp ' . number_format(max(0, $student->wallet_balance - 10000), 0, ',', '.'));
                    }
                }
            } else {
                // If no student, must use cash
                if ($paymentMethod === 'wallet') {
                    throw new \Exception('Pembayaran dompet hanya untuk santri terdaftar.');
                }
            }

            // 3. Balance Check (Global)
            if ($amount <= 0) {
                throw new \Exception('Total transaksi tidak boleh 0.');
            }

            // 4. Stock Check (If items provided)
            $productsToUpdate = [];
            foreach ($items as $item) {
                $product = \App\Models\Product::findOrFail($item['product_id']);
                
                if ($product->type === 'PRODUCT') {
                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Stok tidak cukup untuk: {$product->name}. Tersisa: {$product->stock}");
                    }
                    $productsToUpdate[] = [
                        'product' => $product,
                        'quantity' => $item['quantity']
                    ];
                }
            }

            // 5. Processing - Decrement Balance (Only if wallet)
            if ($student && $paymentMethod === 'wallet') {
                $student->decrement('wallet_balance', $amount);
            }

            // 6. Create Transaction
            $transaction = WalletTransaction::create([
                'school_id' => $operator->school_id,
                'invoice_code' => 'PURC-' . strtoupper(uniqid()),
                'student_id' => $student?->id,
                'pic_user_id' => $operator->id,
                'operator_id' => $operator->id,
                'type' => 'DEBIT',
                'category' => 'PURCHASE',
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'PAID',
                'description' => ($student ? 'Pembelian Koperasi (' . $paymentMethod . ')' : 'Pembelian Umum (Tunai)') . ' - Kasir: ' . $operator->name,
                'product_id' => count($items) === 1 ? $items[0]['product_id'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 7. Save Transaction Items
            foreach ($items as $itemData) {
                $product = \App\Models\Product::find($itemData['product_id']);
                \App\Models\WalletTransactionItem::create([
                    'wallet_transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $itemData['quantity'],
                    'price' => $product->selling_price,
                    'subtotal' => $product->selling_price * $itemData['quantity'],
                ]);
            }

            // 8. Update Stock & Log Movements
            foreach ($productsToUpdate as $data) {
                $product = $data['product'];
                $qty = $data['quantity'];
                $oldStock = $product->stock;
                $newStock = $oldStock - $qty;

                $product->update(['stock' => $newStock]);

                \App\Models\ProductMovement::create([
                    'school_id' => $product->school_id,
                    'product_id' => $product->id,
                    'type' => 'OUT',
                    'quantity' => $qty,
                    'stock_before' => $oldStock,
                    'stock_after' => $newStock,
                    'source' => 'POS',
                    'reference_id' => $transaction->invoice_code,
                    'note' => 'Penjualan POS',
                    'pic_user_id' => $operator->id,
                ]);
            }

            return $transaction;
        });
    }
}
