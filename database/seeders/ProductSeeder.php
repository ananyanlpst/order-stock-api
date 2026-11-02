<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'DEMO-001',
                'name' => 'Demo Pen',
                'price' => '15.50',
                'stock_quantity' => 100,
            ],
            [
                'sku' => 'DEMO-002',
                'name' => 'Demo Notebook',
                'price' => '99.90',
                'stock_quantity' => 50,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }
    }
}
