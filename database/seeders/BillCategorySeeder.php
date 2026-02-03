<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BillCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = \App\Models\School::all();

        foreach ($schools as $school) {
            $categories = [
                'SPP Bulanan' => 'Iuran bulanan pendidikan santri',
                'Uang Gedung' => 'Biaya pembangunan dan pemeliharaan gedung',
                'Uang Makan' => 'Biaya catering bulanan',
                'Seragam' => 'Pembelian seragam sekolah',
                'Kitab & Buku' => 'Pembelian buku pelajaran dan kitab kuning',
                'Kegiatan & Ekstrakurikuler' => 'Biaya kegiatan tambahan',
                'Lainnya' => 'Tagihan lainnya',
            ];

            foreach ($categories as $name => $description) {
                \App\Models\BillCategory::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'name' => $name,
                    ],
                    [
                        'description' => $description,
                    ]
                );
            }
        }
    }
}
