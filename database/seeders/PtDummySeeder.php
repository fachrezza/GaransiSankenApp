<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Services\PtDatabaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PtDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBanners();

        $datasets = [
            'IAK' => [
                'branch' => 'Jakarta Pusat',
                'or' => [
                    ['document_no' => 'OR/IAK/2026/001', 'branch_name' => 'Jakarta Pusat', 'customer_code' => 'CUST-IAK-01', 'customer_name' => 'Toko Elektronik Makmur Jaya', 'amount' => 15000000, 'status' => 'pending'],
                    ['document_no' => 'OR/IAK/2026/002', 'branch_name' => 'Bekasi', 'customer_code' => 'CUST-IAK-02', 'customer_name' => 'CV Sinar Abadi Bekasi', 'amount' => 42000000, 'status' => 'approved'],
                ],
                'products' => [
                    ['sku' => 'SKN-DISP-001', 'name' => 'Dispenser Sanken HWD-C520IC', 'category' => 'Dispenser', 'price' => 1850000, 'stock' => 45],
                    ['sku' => 'SKN-WASH-002', 'name' => 'Mesin Cuci Sanken TW-882', 'category' => 'Mesin Cuci', 'price' => 2450000, 'stock' => 28],
                ],
            ],
            'SAM' => [
                'branch' => 'Bandung',
                'or' => [
                    ['document_no' => 'OR/SAM/2026/001', 'branch_name' => 'Bandung', 'customer_code' => 'CUST-SAM-01', 'customer_name' => 'UD Sumber Rejeki Bandung', 'amount' => 27500000, 'status' => 'pending'],
                    ['document_no' => 'OR/SAM/2026/002', 'branch_name' => 'Cimahi', 'customer_code' => 'CUST-SAM-02', 'customer_name' => 'Toko Jaya Elektro', 'amount' => 12500000, 'status' => 'approved'],
                ],
                'products' => [
                    ['sku' => 'SKN-FRID-003', 'name' => 'Kulkas Sanken SK-G232', 'category' => 'Kulkas', 'price' => 3200000, 'stock' => 19],
                ],
            ],
            'RVG' => [
                'branch' => 'Surabaya',
                'or' => [
                    ['document_no' => 'OR/RVG/2026/001', 'branch_name' => 'Surabaya', 'customer_code' => 'CUST-RVG-01', 'customer_name' => 'PT Maju Sentosa Timur', 'amount' => 31000000, 'status' => 'pending'],
                ],
                'products' => [
                    ['sku' => 'SKN-AC-004', 'name' => 'Air Conditioner Sanken 1 PK', 'category' => 'AC', 'price' => 3650000, 'stock' => 32],
                ],
            ],
        ];

        $now = now();

        foreach (PtDatabaseService::ENTITIES as $code => $entity) {
            $db = DB::connection($entity['connection']);
            $data = $datasets[$code];

            foreach ($data['or'] as $or) {
                $db->table('or_requests')->updateOrInsert(
                    ['document_no' => $or['document_no']],
                    [
                        ...$or,
                        'due_date' => $now->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach ($data['products'] as $product) {
                $db->table('products')->updateOrInsert(
                    ['sku' => $product['sku']],
                    [
                        ...$product,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $db->table('activity_logs')->insert([
                'type' => 'stock',
                'title' => "Update Stok Cabang {$data['branch']}",
                'description' => "Sinkronisasi data operasional {$entity['name']}",
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedBanners(): void
    {
        if (Banner::count() > 0) {
            return;
        }

        Banner::insert([
            [
                'badge_text' => 'PRODUK SANKEN',
                'title' => 'CASHBACK 5%',
                'subtitle' => 'Faktur Dispenser & Mesin Cuci',
                'image_url' => null,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
