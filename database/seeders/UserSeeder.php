<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Create Super Admin
        $superAdmin = \App\Models\User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'phone_number' => '081234567890',
                'password' => bcrypt('password'),
            ]
        );
        $superAdmin->assignRole('super_admin');
        $schools = \App\Models\School::all();

        foreach ($schools as $schoolIndex => $school) {
            // 1. Create Admin
            $admin = \App\Models\User::firstOrCreate(
                ['email' => 'admin@' . strtolower(str_replace(' ', '', $school->name)) . '.com'],
                [
                    'name' => 'Admin ' . $school->name,
                    'phone_number' => fake()->unique()->phoneNumber(),
                    'password' => bcrypt('password'),
                    'school_id' => $school->id,
                ]
            );
            $admin->assignRole('admin');

            // 2. Create Admin Koperasi
            $koperasi = \App\Models\User::firstOrCreate(
                ['email' => 'koperasi@' . strtolower(str_replace(' ', '', $school->name)) . '.com'],
                [
                    'name' => 'Koperasi ' . $school->name,
                    'phone_number' => fake()->unique()->phoneNumber(),
                    'password' => bcrypt('password'),
                    'school_id' => $school->id,
                ]
            );
            $koperasi->assignRole('admin_koperasi');

            // 3. Create 10 Wali Santri
            for ($i = 1; $i <= 10; $i++) {
                $wali = \App\Models\User::firstOrCreate(
                    ['email' => "wali{$i}@" . strtolower(str_replace(' ', '', $school->name)) . '.com'],
                    [
                        'name' => "Wali Santri {$i} - {$school->name}",
                        'phone_number' => fake()->unique()->phoneNumber(),
                        'password' => bcrypt('password'),
                        'school_id' => $school->id,
                    ]
                );
                $wali->assignRole('wali_santri');

                // 4. Create 10 Students (Santri) for each Wali
                // We need to assign them to a level. Let's pick random level from this school.
                $levels = \App\Models\Level::where('school_id', $school->id)->get();

                if ($levels->count() > 0) {
                    for ($j = 1; $j <= 10; $j++) {
                         // Generate standard numeric NIS: Year + SchoolIndex(2) + Sequence(4)
                         // e.g. 2024010001
                         $studentSeq = ($i - 1) * 10 + $j;
                         $nis = date('Y') . sprintf('%02d', $schoolIndex + 1) . sprintf('%04d', $studentSeq);

                         \App\Models\Student::firstOrCreate(
                            ['nis' => $nis],
                            [
                                'school_id' => $school->id,
                                'parent_user_id' => $wali->id, // Link to the Wali
                                'full_name' => "Santri {$j} of Wali {$i}", // Model uses full_name, not name
                                'gender' => ($j % 2 == 0) ? 'L' : 'P',
                                // 'place_of_birth' => 'City', // Not in fillable/table apparently? Checked fillable, it is implicit or guarded? Model lists it in fillable? Wait.
                                // Fillable: nis, full_name, parent_user_id, wallet_balance, status, gender, photo_url, level_id, school_id, wallet_pin, daily_cash_limit, allow_transaction
                                // birth_date is in casts. so likely 'birth_date' column.
                                // Address is NOT in fillable.
                                'status' => 'ACTIVE',
                                'level_id' => $levels->random()->id,
                                'wallet_balance' => 0,
                                'allow_transaction' => true,
                                'daily_cash_limit' => 50000,
                            ]
                        );
                    }
                }
            }
        }
    }
}
