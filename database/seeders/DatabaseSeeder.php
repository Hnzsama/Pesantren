<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SchoolSeeder::class,
            DepartmentSeeder::class,
            AcademicYearSeeder::class,
            SemesterSeeder::class,
            LevelSeeder::class,
            UserSeeder::class, // Handles Admins, Walis, and Students
            AcademicPromotionSeeder::class,
            
            // Other seeders if needed...
            // PaymentChannelSeeder::class,
            // SettingSeeder::class,
        ]);
    }
}
