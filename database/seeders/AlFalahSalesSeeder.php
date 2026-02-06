<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;

class AlFalahSalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find Al-Falah school
        $school = School::where('name', 'like', '%Al-Falah%')->first();
        
        if (!$school) {
            $this->command->error('School "Al-Falah" not found. Please run SchoolSeeder first.');
            return;
        }

        // Get students from Al-Falah
        $students = Student::where('school_id', $school->id)->get();
        
        if ($students->isEmpty()) {
            $this->command->error('No students found for Al-Falah. Please run StudentSeeder first.');
            return;
        }

        // Get admin or operator for pic_user_id
        $admin = User::role(['admin', 'admin_koperasi'])->first();
        
        if (!$admin) {
            $this->command->error('No admin user found. Please run UserSeeder first.');
            return;
        }

        $this->command->info("Generating 100 sales transactions for {$school->name}...");

        // Generate 100 transactions spread over last 30 days
        for ($i = 0; $i < 100; $i++) {
            // Random student
            $student = $students->random();
            
            // Random date within last 30 days
            $daysAgo = rand(0, 29);
            $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            // Random amount between 5,000 - 50,000
            $amount = rand(5, 50) * 1000;
            
            // Generate unique invoice code
            $invoiceCode = 'INV-PUR-' . $school->id . '-' . time() . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            
            WalletTransaction::create([
                'school_id' => $school->id,
                'invoice_code' => $invoiceCode,
                'student_id' => $student->id,
                'operator_id' => null,
                'pic_user_id' => $admin->id,
                'type' => 'DEBIT',
                'category' => 'PURCHASE',
                'amount' => $amount,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'description' => 'Pembelian di Koperasi - ' . $this->getRandomItem(),
                'payment_method' => 'cash',
                'status' => 'PAID',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->command->info('✅ Successfully created 100 sales transactions for Al-Falah!');
    }

    /**
     * Get random cooperative item description
     */
    private function getRandomItem(): string
    {
        $items = [
            'Alat Tulis',
            'Buku Pelajaran',
            'Snack Ringan',
            'Minuman',
            'Perlengkapan Mandi',
            'Kebutuhan Sehari-hari',
            'Makanan Ringan',
            'Buku Catatan',
            'Pulpen & Pensil',
            'Peralatan Olahraga',
        ];

        return $items[array_rand($items)];
    }
}
