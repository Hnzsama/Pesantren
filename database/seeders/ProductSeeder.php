<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\School;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            // Products
            $products = [
                ['name' => 'Air Mineral 600ml', 'sku' => 'AQUA-600', 'price' => 3500, 'stock' => 50],
                ['name' => 'Buku Tulis Sidu 38', 'sku' => 'BUKU-38', 'price' => 4500, 'stock' => 100],
                ['name' => 'Pulpen Faster', 'sku' => 'PUL-FST', 'price' => 2500, 'stock' => 200],
                ['name' => 'Snack Taro 20g', 'sku' => 'SNC-TRA', 'price' => 2000, 'stock' => 30],
                ['name' => 'Sabun Mandi Lifebuoy', 'sku' => 'SBN-LFB', 'price' => 4000, 'stock' => 40],
            ];

            foreach ($products as $p) {
                Product::updateOrCreate(
                    ['school_id' => $school->id, 'sku' => $p['sku']],
                    [
                        'name' => $p['name'],
                        'type' => 'PRODUCT',
                        'price' => $p['price'],
                        'stock' => $p['stock'],
                        'is_active' => true,
                    ]
                );
            }

            // Services
            $services = [
                ['name' => 'Fotokopi A4', 'sku' => 'SRV-CPY', 'price' => 500],
                ['name' => 'Print Dokumen B/W', 'sku' => 'SRV-PRT-BW', 'price' => 1000],
                ['name' => 'Jilid Mika', 'sku' => 'SRV-JLD', 'price' => 5000],
            ];

            foreach ($services as $s) {
                Product::updateOrCreate(
                    ['school_id' => $school->id, 'sku' => $s['sku']],
                    [
                        'name' => $s['name'],
                        'type' => 'SERVICE',
                        'price' => $s['price'],
                        'stock' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
