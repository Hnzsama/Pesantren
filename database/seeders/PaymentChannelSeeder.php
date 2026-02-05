<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PaymentChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ============================================
        // 🏦 VIRTUAL ACCOUNTS - Flat Fee IDR 4.000
        // ============================================
        $virtualAccounts = [
            ['code' => 'bca_va', 'name' => 'BCA Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'briva', 'name' => 'BRI Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'bni_va', 'name' => 'BNI Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'mandiri_va', 'name' => 'Mandiri Bill Payment', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'permata_va', 'name' => 'Permata Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'cimb_va', 'name' => 'CIMB Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'danamon_va', 'name' => 'Danamon Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'bsi_va', 'name' => 'BSI Virtual Account', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
            ['code' => 'other_va', 'name' => 'ATM Bersama / Prima / Alto', 'type' => 'VIRTUAL_ACCOUNT', 'fee_flat' => 4000, 'fee_percent' => 0],
        ];

        // ============================================
        // 💳 E-WALLETS - Percentage Fee
        // ============================================
        $ewallets = [
            ['code' => 'gopay', 'name' => 'GoPay', 'type' => 'E_WALLET', 'fee_flat' => 0, 'fee_percent' => 2.0],
            ['code' => 'shopeepay', 'name' => 'ShopeePay', 'type' => 'E_WALLET', 'fee_flat' => 0, 'fee_percent' => 2.0],
            ['code' => 'dana', 'name' => 'DANA', 'type' => 'E_WALLET', 'fee_flat' => 0, 'fee_percent' => 1.5],
        ];

        // ============================================
        // 📱 QRIS - Low Percentage Fee
        // ============================================
        $qris = [
            ['code' => 'qris', 'name' => 'QRIS', 'type' => 'QRIS', 'fee_flat' => 0, 'fee_percent' => 0.7],
        ];

        // ============================================
        // 💳 CREDIT/DEBIT CARDS - Mixed Fee
        // ============================================
        $cards = [
            ['code' => 'credit_card', 'name' => 'Visa / Mastercard / JCB / Amex', 'type' => 'CARD', 'fee_flat' => 2000, 'fee_percent' => 2.9],
        ];

        // ============================================
        // 🏪 RETAIL STORES - Flat Fee
        // ============================================
        $retailStores = [
            ['code' => 'indomaret', 'name' => 'Indomaret', 'type' => 'RETAIL_STORE', 'fee_flat' => 1000, 'fee_percent' => 0],
            ['code' => 'alfamart', 'name' => 'Alfamart / Alfamidi / Dan+Dan', 'type' => 'RETAIL_STORE', 'fee_flat' => 5000, 'fee_percent' => 0],
        ];

        // ============================================
        // 🛒 PAY LATER - Percentage Fee
        // ============================================
        $payLater = [
            ['code' => 'akulaku', 'name' => 'Akulaku PayLater', 'type' => 'E_WALLET', 'fee_flat' => 0, 'fee_percent' => 1.7],
            ['code' => 'kredivo', 'name' => 'Kredivo', 'type' => 'E_WALLET', 'fee_flat' => 0, 'fee_percent' => 2.0],
        ];

        // Merge all categories
        $allChannels = array_merge(
            $virtualAccounts,
            $ewallets,
            $qris,
            $cards,
            $retailStores,
            $payLater
        );

        // Seed to database
        foreach ($allChannels as $channel) {
            \App\Models\PaymentChannel::updateOrCreate(
                ['code' => $channel['code']],
                $channel
            );
        }
    }
}
