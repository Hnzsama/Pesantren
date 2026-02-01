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
            \App\Models\School::firstOrCreate(
                ['name' => $schoolName],
                [
                    'address' => 'Jl. Contoh No. 123',
                    'slug' => Str::slug($schoolName),
                    'phone' => '08123456789',
                    'email' => strtolower(str_replace(' ', '', $schoolName)) . '@example.com',
                    'website' => 'https://' . strtolower(str_replace(' ', '', $schoolName)) . '.com',
                ]
            );
        }
    }
}
