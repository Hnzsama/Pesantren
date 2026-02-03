<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = [
            'Pesantren Al-Falah',
            'Pesantren Al-Hidayah',
            'Pesantren An-Nur',
        ];

        foreach ($schools as $schoolName) {
            $school = \App\Models\School::firstOrCreate(
                ['name' => $schoolName],
                [
                    'address' => 'Jl. Contoh No. 123',
                    'slug' => Str::slug($schoolName),
                    'phone' => '08123456789',
                    'email' => strtolower(str_replace(' ', '', $schoolName)) . '@example.com',
                    'website' => 'https://' . strtolower(str_replace(' ', '', $schoolName)) . '.com',
                ]
            );

            // Seed Departments for each school
            $departments = [
                ['name' => 'Madrasah Ibtidaiyah', 'code' => 'MI', 'description' => 'Setingkat SD'],
                ['name' => 'Madrasah Tsanawiyah', 'code' => 'MTs', 'description' => 'Setingkat SMP'],
                ['name' => 'Madrasah Aliyah', 'code' => 'MA', 'description' => 'Setingkat SMA'],
            ];

            foreach ($departments as $dept) {
                \App\Models\Department::firstOrCreate(
                    ['school_id' => $school->id, 'code' => $dept['code']],
                    array_merge($dept, ['school_id' => $school->id])
                );
            }
        }
    }
}
