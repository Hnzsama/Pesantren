<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletTransactionItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CooperativeSalesSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = auth()->check() ? auth()->user()->school_id : (School::first()->id ?? null);
        
        if (!$schoolId) {
            $this->command->error('No school found.');
            return;
        }

        $students = Student::where('school_id', $schoolId)->get();
        if ($students->isEmpty()) {
            $this->command->error('No students found.');
            return;
        }

        // Get admin koperasi (or the user running this)
        $admin = auth()->check() ? auth()->user() : User::role(['admin_koperasi'])->first();
        if (!$admin) {
            $this->command->error('No admin_koperasi user found.');
            return;
        }

        // Get products
        $products = Product::where('school_id', $schoolId)->where('is_active', true)->get();
        if ($products->isEmpty()) {
            $this->command->error('No active products found.');
            return;
        }

        $this->command->info("Generating 30 realistic cooperative sales transactions...");

        for ($i = 0; $i < 30; $i++) {
            $student = $students->random();
            $daysAgo = rand(0, 10); // Last 10 days
            $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $invoiceCode = 'INV-POS-' . time() . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);

            // Determine how many items to buy (1 to 3)
            $itemsCount = rand(1, 3);
            $selectedProducts = $products->random($itemsCount);
            
            $transactionTotal = 0;
            $itemsData = [];

            foreach ($selectedProducts as $product) {
                $qty = rand(1, 3);
                $subtotal = $product->selling_price * $qty;
                $transactionTotal += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'price' => $product->selling_price,
                    'subtotal' => $subtotal,
                ];
            }

            $transaction = WalletTransaction::create([
                'school_id' => $schoolId,
                'invoice_code' => $invoiceCode,
                'student_id' => $student->id,
                'operator_id' => $admin->id,
                'pic_user_id' => $admin->id,
                'type' => 'DEBIT',
                'category' => 'PURCHASE',
                'amount' => $transactionTotal,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'description' => 'Pembelian Koperasi - Kasir: ' . $admin->name,
                'payment_method' => 'cash', // Or whatever it is called
                'status' => 'PAID',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($itemsData as $itemData) {
                WalletTransactionItem::create([
                    'wallet_transaction_id' => $transaction->id,
                    'product_id' => $itemData['product_id'],
                    'product_name' => $itemData['product_name'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'subtotal' => $itemData['subtotal'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        $this->command->info('✅ Successfully seeded 30 cooperative sales transactions with items!');
    }
}
