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

        $now = now();
        $today = $now->toDateString();

        $datasets = [
            'IAK' => [
                'cabang' => ['kd_cab' => '1', 'nm_cab' => 'Jakarta Pusat'],
                'ar010a' => [
                    ['mp_nocust' => '1DIM01', 'mp_custno' => '1DIM01', 'mp_t_cost' => 50000000],
                    ['mp_nocust' => '1DIM02', 'mp_custno' => '1DIM01', 'mp_t_cost' => 50000000],
                ],
                'or130a' => [
                    ['so_noso' => 'SO/IAK/2026/001', 'so_nocust' => '1DIM02', 'so_name' => 'Toko Dimensi Makmur 2', 'so_kota' => 'Jakarta', 'so_harga' => 15000000, 'so_cekstat' => ''],
                    ['so_noso' => 'SO/IAK/2026/002', 'so_nocust' => '1DIM01', 'so_name' => 'Toko Dimensi Pusat', 'so_kota' => 'Jakarta', 'so_harga' => 22000000, 'so_cekstat' => 'Y'],
                ],
                'sa131a' => [
                    ['do_grdo' => 'DO01', 'do_nocust' => '1DIM01', 'do_nofak' => '', 'do_status' => '@', 'do_statux' => '@', 'do_qty' => 5, 'do_harga' => 8000000],
                ],
                'ar020a' => [
                    ['fa_grfak' => 'FT', 'fa_nocust' => '1DIM02', 'fa_status' => '@', 'fa_harga' => 25000000, 'fa_hgkas' => 10000000],
                ],
                'products' => [
                    ['sku' => 'SKN-DISP-001', 'name' => 'Dispenser Sanken HWD-C520IC', 'category' => 'Dispenser', 'price' => 1850000, 'stock' => 45],
                    ['sku' => 'SKN-WASH-002', 'name' => 'Mesin Cuci Sanken TW-882', 'category' => 'Mesin Cuci', 'price' => 2450000, 'stock' => 28],
                ],
            ],
            'SAM' => [
                'cabang' => ['kd_cab' => '2', 'nm_cab' => 'Bandung'],
                'ar010a' => [
                    ['mp_nocust' => '2SAM01', 'mp_custno' => '2SAM01', 'mp_t_cost' => 40000000],
                ],
                'or130a' => [
                    ['so_noso' => 'SO/SAM/2026/001', 'so_nocust' => '2SAM01', 'so_name' => 'UD Sumber Rejeki Bandung', 'so_kota' => 'Bandung', 'so_harga' => 27500000, 'so_cekstat' => ''],
                ],
                'sa131a' => [],
                'ar020a' => [
                    ['fa_grfak' => 'FT', 'fa_nocust' => '1DIM01', 'fa_status' => '@', 'fa_harga' => 12000000, 'fa_hgkas' => 2000000],
                ],
                'products' => [
                    ['sku' => 'SKN-FRID-003', 'name' => 'Kulkas Sanken SK-G232', 'category' => 'Kulkas', 'price' => 3200000, 'stock' => 19],
                ],
            ],
            'RVG' => [
                'cabang' => ['kd_cab' => '3', 'nm_cab' => 'Surabaya'],
                'ar010a' => [
                    ['mp_nocust' => '3RVG01', 'mp_custno' => '', 'mp_t_cost' => 60000000],
                ],
                'or130a' => [
                    ['so_noso' => 'SO/RVG/2026/001', 'so_nocust' => '3RVG01', 'so_name' => 'PT Maju Sentosa Timur', 'so_kota' => 'Surabaya', 'so_harga' => 31000000, 'so_cekstat' => ''],
                ],
                'sa131a' => [],
                'ar020a' => [],
                'products' => [
                    ['sku' => 'SKN-AC-004', 'name' => 'Air Conditioner Sanken 1 PK', 'category' => 'AC', 'price' => 3650000, 'stock' => 32],
                ],
            ],
        ];

        foreach (PtDatabaseService::ENTITIES as $code => $entity) {
            $db = DB::connection($entity['connection']);
            $data = $datasets[$code];

            $db->table('tb_cab')->updateOrInsert(
                ['kd_cab' => $data['cabang']['kd_cab']],
                ['nm_cab' => $data['cabang']['nm_cab']]
            );

            foreach ($data['ar010a'] as $cust) {
                $db->table('ar010a')->updateOrInsert(
                    ['mp_nocust' => $cust['mp_nocust']],
                    [...$cust, 'created_at' => $now, 'updated_at' => $now]
                );
            }

            foreach ($data['or130a'] as $or) {
                $db->table('or130a')->updateOrInsert(
                    ['so_noso' => $or['so_noso']],
                    [
                        ...$or,
                        'so_hdate' => $today,
                        'so_dtso' => $today,
                        'so_status' => '@',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                if ($db->table('or131a')->where('so_noso', $or['so_noso'])->doesntExist()) {
                    $db->table('or131a')->insert([
                        'so_noso' => $or['so_noso'],
                        'so_kode' => 'ITEM-01',
                        'so_qty' => 2,
                        'so_harga' => $or['so_harga'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach ($data['sa131a'] as $do) {
                $db->table('sa131a')->insert([...$do, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach ($data['ar020a'] as $ar) {
                $db->table('ar020a')->insert([...$ar, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach ($data['products'] as $product) {
                $db->table('products')->updateOrInsert(
                    ['sku' => $product['sku']],
                    [...$product, 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $db->table('activity_logs')->insert([
                'type' => 'stock',
                'title' => "Update Stok Cabang {$data['cabang']['nm_cab']}",
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
