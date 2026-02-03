<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;

class WalletTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first student and admin
        $student = Student::first();
        $admin = User::role('admin')->first();

        if (!$student || !$admin) {
            $this->command->warn('No student or admin found. Skipping WalletTransaction seeder.');
            return;
        }

        $transactions = [
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-TOP-' . time() . '-001',
                'student_id' => $student->id,
                'operator_id' => null,
                'pic_user_id' => $admin->id,
                'type' => 'CREDIT',
                'category' => 'TOPUP',
                'amount' => 500000,
                'payment_fee' => 4000,
                'admin_fee' => 1000,
                'description' => 'Top up saldo dompet via BCA Virtual Account',
                'payment_method' => 'payment_gateway',
                'status' => 'PAID',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-WD-' . time() . '-002',
                'student_id' => $student->id,
                'operator_id' => $admin->id,
                'pic_user_id' => $admin->id,
                'type' => 'DEBIT',
                'category' => 'WITHDRAWAL',
                'amount' => 100000,
                'payment_fee' => 0,
                'admin_fee' => 2500,
                'description' => 'Penarikan tunai ke rekening orangtua',
                'payment_method' => 'cash',
                'status' => 'PAID',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-PUR-' . time() . '-003',
                'student_id' => $student->id,
                'operator_id' => null,
                'pic_user_id' => $admin->id,
                'type' => 'DEBIT',
                'category' => 'PURCHASE',
                'amount' => 25000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'description' => 'Pembelian makan siang di kantin sekolah',
                'payment_method' => 'cash',
                'status' => 'PAID',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-TOP-' . time() . '-004',
                'student_id' => $student->id,
                'operator_id' => null,
                'pic_user_id' => $admin->id,
                'type' => 'CREDIT',
                'category' => 'TOPUP',
                'amount' => 200000,
                'payment_fee' => 3000,
                'admin_fee' => 1000,
                'description' => 'Top up saldo via QRIS (pending)',
                'payment_method' => 'payment_gateway',
                'status' => 'UNPAID',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-PUR-' . time() . '-005',
                'student_id' => $student->id,
                'operator_id' => null,
                'pic_user_id' => $admin->id,
                'type' => 'DEBIT',
                'category' => 'PURCHASE',
                'amount' => 50000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'description' => 'Pembelian buku pelajaran (dibatalkan)',
                'payment_method' => 'cash',
                'status' => 'CANCELLED',
            ],
        ];

        foreach ($transactions as $txn) {
            WalletTransaction::create($txn);
        }

        $this->command->info('Created 5 sample wallet transactions.');
    }
}
