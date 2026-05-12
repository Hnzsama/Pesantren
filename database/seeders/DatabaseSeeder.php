<?php

namespace Database\Seeders;

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
            BillCategorySeeder::class,
            UserSeeder::class, // Handles Admins, Walis, and Students
            AcademicPromotionSeeder::class,

            // Master Data
            PaymentChannelSeeder::class,
            SettingSeeder::class,

            // Content & Activities
            ActivitySeeder::class,
            EventSeeder::class,
            NewsSeeder::class,
            VideoLectureSeeder::class,
            AchievementSeeder::class,

            // Financial & Feedback
            BillSeeder::class,
            WalletTransactionSeeder::class,
            FeedbackSeeder::class,

            // Attendance & Permissions
            ActivityAttendanceSeeder::class,
            EventAttendanceSeeder::class,
            StudentPermissionSeeder::class,
            StudentReportSeeder::class,

            // Cooperative
            ProductSeeder::class,
        ]);
    }
}
