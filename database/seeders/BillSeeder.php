<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\BillCategory;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first student
        $student = Student::first();

        if (!$student) {
            $this->command->warn('No student found. Skipping Bill seeder.');
            return;
        }

        // Get bill categories
        $sppCategory = BillCategory::where('school_id', $student->school_id)
            ->where('name', 'SPP Bulanan')
            ->first();

        $uniformCategory = BillCategory::where('school_id', $student->school_id)
            ->where('name', 'Seragam')
            ->first();

        $foodCategory = BillCategory::where('school_id', $student->school_id)
            ->where('name', 'Uang Makan')
            ->first();

        $bookCategory = BillCategory::where('school_id', $student->school_id)
            ->where('name', 'Kitab & Buku')
            ->first();

        $bills = [
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-BILL-' . time() . '-001',
                'student_id' => $student->id,
                'bill_category_id' => $sppCategory?->id,
                'event_id' => null,
                'title' => 'SPP Bulan Januari 2026',
                'bill_code' => 'SPP-JAN-2026',
                'amount' => 500000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'status' => 'PAID',
                'due_date' => Carbon::parse('2026-01-10'),
                'paid_at' => Carbon::parse('2026-01-08 10:30:00'),
                'payment_method' => 'cash',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-BILL-' . time() . '-002',
                'student_id' => $student->id,
                'bill_category_id' => $sppCategory?->id,
                'event_id' => null,
                'title' => 'SPP Bulan Februari 2026',
                'bill_code' => 'SPP-FEB-2026',
                'amount' => 500000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'status' => 'UNPAID',
                'due_date' => Carbon::parse('2026-02-10'),
                'paid_at' => null,
                'payment_method' => null,
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-BILL-' . time() . '-003',
                'student_id' => $student->id,
                'bill_category_id' => $uniformCategory?->id,
                'event_id' => null,
                'title' => 'Seragam Olahraga',
                'bill_code' => 'UNI-SPORT-2026',
                'amount' => 250000,
                'payment_fee' => 3000,
                'admin_fee' => 1000,
                'status' => 'PAID',
                'due_date' => Carbon::parse('2026-01-15'),
                'paid_at' => Carbon::parse('2026-01-14 14:20:00'),
                'payment_method' => 'payment_gateway',
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-BILL-' . time() . '-004',
                'student_id' => $student->id,
                'bill_category_id' => $foodCategory?->id,
                'event_id' => null,
                'title' => 'Uang Makan Bulan Januari 2026',
                'bill_code' => 'FOOD-JAN-2026',
                'amount' => 300000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'status' => 'UNPAID',
                'due_date' => Carbon::parse('2026-01-20'),
                'paid_at' => null,
                'payment_method' => null,
            ],
            [
                'school_id' => $student->school_id,
                'invoice_code' => 'INV-BILL-' . time() . '-005',
                'student_id' => $student->id,
                'bill_category_id' => $bookCategory?->id,
                'event_id' => null,
                'title' => 'Kitab Nahwu & Shorof',
                'bill_code' => 'BOOK-NAHWU-2026',
                'amount' => 150000,
                'payment_fee' => 0,
                'admin_fee' => 0,
                'status' => 'CANCELLED',
                'due_date' => Carbon::parse('2026-01-25'),
                'paid_at' => null,
                'payment_method' => null,
            ],
        ];

        foreach ($bills as $bill) {
            Bill::create($bill);
        }

        $this->command->info('Created 5 sample bills.');
    }
}
