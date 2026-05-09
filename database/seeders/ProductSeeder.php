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
                ['name' => 'Air Mineral 600ml', 'sku' => 'AQUA-600', 'purchase_price' => 2500, 'selling_price' => 3500, 'stock' => 50],
                ['name' => 'Buku Tulis Sidu 38', 'sku' => 'BUKU-38', 'purchase_price' => 3000, 'selling_price' => 4500, 'stock' => 100],
                ['name' => 'Pulpen Faster', 'sku' => 'PUL-FST', 'purchase_price' => 1500, 'selling_price' => 2500, 'stock' => 200],
                ['name' => 'Snack Taro 20g', 'sku' => 'SNC-TRA', 'purchase_price' => 1500, 'selling_price' => 2000, 'stock' => 30],
                ['name' => 'Sabun Mandi Lifebuoy', 'sku' => 'SBN-LFB', 'purchase_price' => 3000, 'selling_price' => 4000, 'stock' => 40],
            ];

            foreach ($products as $p) {
                Product::updateOrCreate(
                    ['school_id' => $school->id, 'sku' => $p['sku']],
                    [
                        'name' => $p['name'],
                        'type' => 'PRODUCT',
                        'purchase_price' => $p['purchase_price'],
                        'selling_price' => $p['selling_price'],
                        'stock' => $p['stock'],
                        'is_active' => true,
                    ]
                );
            }

            // Services
            $services = [
                ['name' => 'Fotokopi A4', 'sku' => 'SRV-CPY', 'purchase_price' => 0, 'selling_price' => 500],
                ['name' => 'Print Dokumen B/W', 'sku' => 'SRV-PRT-BW', 'purchase_price' => 0, 'selling_price' => 1000],
                ['name' => 'Jilid Mika', 'sku' => 'SRV-JLD', 'purchase_price' => 0, 'selling_price' => 5000],
            ];

            foreach ($services as $s) {
                Product::updateOrCreate(
                    ['school_id' => $school->id, 'sku' => $s['sku']],
                    [
                        'name' => $s['name'],
                        'type' => 'SERVICE',
                        'purchase_price' => $s['purchase_price'],
                        'selling_price' => $s['selling_price'],
                        'stock' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
