<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiPtApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['dummy_iak', 'dummy_sam', 'dummy_rvg'] as $conn) {
            Config::set("database.connections.{$conn}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
            DB::purge($conn);

            $schema = Schema::connection($conn);
            $schema->create('tb_cab', function ($table) {
                $table->string('kd_cab', 10)->primary();
                $table->string('nm_cab');
            });
            $schema->create('or130a', function ($table) {
                $table->id();
                $table->string('so_noso')->unique();
                $table->string('so_nocust');
                $table->string('so_name');
                $table->string('so_kota')->nullable();
                $table->unsignedBigInteger('so_harga')->default(0);
                $table->date('so_hdate');
                $table->date('so_dtso');
                $table->string('so_cekstat', 10)->nullable()->default('');
                $table->string('so_status', 5)->default('@');
                $table->string('so_huser')->nullable();
                $table->date('so_appdate')->nullable();
                $table->time('so_apptime')->nullable();
                $table->string('so_ket1')->nullable();
            });
            $schema->create('or131a', function ($table) {
                $table->id();
                $table->string('so_noso');
                $table->string('so_kode')->nullable();
                $table->unsignedInteger('so_qty')->default(1);
                $table->unsignedBigInteger('so_harga')->default(0);
                $table->timestamps();
            });
            $schema->create('ar010a', function ($table) {
                $table->string('mp_nocust')->primary();
                $table->string('mp_custno')->nullable();
                $table->unsignedBigInteger('mp_t_cost')->default(0);
                $table->timestamps();
            });
            $schema->create('sa131a', function ($table) {
                $table->id();
                $table->string('do_grdo', 10);
                $table->string('do_nocust');
                $table->string('do_nofak')->nullable()->default('');
                $table->string('do_status', 5)->nullable()->default('');
                $table->string('do_statux', 5)->default('@');
                $table->unsignedInteger('do_qty')->default(0);
                $table->unsignedBigInteger('do_harga')->default(0);
                $table->timestamps();
            });
            $schema->create('ar020a', function ($table) {
                $table->id();
                $table->string('fa_grfak', 10);
                $table->string('fa_nocust');
                $table->string('fa_status', 5)->default('@');
                $table->unsignedBigInteger('fa_harga')->default(0);
                $table->unsignedBigInteger('fa_hgkas')->default(0);
                $table->timestamps();
            });
            $schema->create('products', function ($table) {
                $table->id();
                $table->string('sku')->unique();
                $table->string('name');
                $table->string('category');
                $table->unsignedBigInteger('price');
                $table->unsignedInteger('stock')->default(0);
                $table->timestamps();
            });
            $schema->create('activity_logs', function ($table) {
                $table->id();
                $table->string('type')->default('info');
                $table->string('title');
                $table->string('description');
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }

        $today = now()->toDateString();

        // Seed IAK: 1DIM02 (group 1DIM01, limit 20jt)
        DB::connection('dummy_iak')->table('tb_cab')->insert(['kd_cab' => '1', 'nm_cab' => 'Jakarta Pusat']);
        DB::connection('dummy_iak')->table('ar010a')->insert([
            ['mp_nocust' => '1DIM01', 'mp_custno' => '1DIM01', 'mp_t_cost' => 20000000],
            ['mp_nocust' => '1DIM02', 'mp_custno' => '1DIM01', 'mp_t_cost' => 20000000],
        ]);
        DB::connection('dummy_iak')->table('or130a')->insert([
            'so_noso' => 'SO/IAK/001',
            'so_nocust' => '1DIM02',
            'so_name' => 'Toko Dimensi 2',
            'so_kota' => 'Jakarta',
            'so_harga' => 10000000,
            'so_hdate' => $today,
            'so_dtso' => $today,
            'so_cekstat' => '',
            'so_status' => '@',
        ]);
        DB::connection('dummy_iak')->table('or131a')->insert(['so_noso' => 'SO/IAK/001', 'so_qty' => 1, 'so_harga' => 10000000]);
        DB::connection('dummy_iak')->table('sa131a')->insert([
            'do_grdo' => 'DO1',
            'do_nocust' => '1DIM01',
            'do_nofak' => '',
            'do_status' => '@',
            'do_statux' => '@',
            'do_qty' => 2,
            'do_harga' => 5000000,
        ]);

        // Seed SAM: 2SAM01 pending OR + 1DIM02 in ar010a & debt in ar020a
        DB::connection('dummy_sam')->table('tb_cab')->insert(['kd_cab' => '2', 'nm_cab' => 'Bandung']);
        DB::connection('dummy_sam')->table('ar010a')->insert([
            'mp_nocust' => '1DIM02',
            'mp_custno' => '1DIM01',
            'mp_t_cost' => 20000000,
        ]);
        DB::connection('dummy_sam')->table('or130a')->insert([
            'so_noso' => 'SO/SAM/001',
            'so_nocust' => '2SAM01',
            'so_name' => 'Toko SAM',
            'so_kota' => 'Bandung',
            'so_harga' => 2000000,
            'so_hdate' => $today,
            'so_dtso' => $today,
            'so_cekstat' => '',
            'so_status' => '@',
        ]);
        DB::connection('dummy_sam')->table('or131a')->insert(['so_noso' => 'SO/SAM/001', 'so_qty' => 1, 'so_harga' => 2000000]);
        DB::connection('dummy_sam')->table('ar020a')->insert([
            'fa_grfak' => 'FT',
            'fa_nocust' => '1DIM02',
            'fa_status' => '@',
            'fa_harga' => 10000000,
            'fa_hgkas' => 2000000,
        ]);

        // Seed RVG product
        DB::connection('dummy_rvg')->table('products')->insert([
            'sku' => 'SKN-RVG-1',
            'name' => 'AC Sanken',
            'category' => 'AC',
            'price' => 3000000,
            'stock' => 10,
        ]);
    }

    public function test_user_only_sees_permitted_pt_or_list_and_credit_check_aggregates_group_debt(): void
    {
        $permIak = Permission::create(['name' => 'view-db-iak']);
        $roleIak = Role::create(['name' => 'admin-iak']);
        $roleIak->permissions()->attach($permIak->id);

        $userIak = User::factory()->create(['is_superadmin' => false]);
        $userIak->roles()->attach($roleIak->id);

        Sanctum::actingAs($userIak);

        $this->getJson('/api/approve-or')
            ->assertOk()
            ->assertJsonPath('data.show_all_tab', false)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.pt_code', 'IAK')
            ->assertJsonPath('data.items.0.cabang', 'Jakarta Pusat')
            ->assertJsonPath('data.items.0.toko', '1DIM02')
            ->assertJsonPath('data.items.0.harga', 10000000);

        // Credit check: 5jt (belum jadi faktur / IAK DO) + 8jt (piutang faktur / SAM AR) = 13jt, + 10jt OR > 20jt limit
        $this->getJson('/api/approve-or/IAK/1DIM02')
            ->assertOk()
            ->assertJsonPath('data.group_code', '1DIM01')
            ->assertJsonPath('data.limit', 20000000)
            ->assertJsonPath('data.belum_jadi_faktur', 5000000)
            ->assertJsonPath('data.piutang_faktur', 8000000)
            ->assertJsonPath('data.total_hutang', 13000000)
            ->assertJsonPath('data.nilai_or', 10000000)
            ->assertJsonPath('data.is_over_limit', true);

        // Forbidden for SAM
        $this->getJson('/api/approve-or?pt=SAM')->assertForbidden();
    }

    public function test_approve_or_requires_permission_and_updates_so_cekstat(): void
    {
        $permIak = Permission::create(['name' => 'view-db-iak']);
        $permApprove = Permission::create(['name' => 'approve-or']);

        $viewerRole = Role::create(['name' => 'viewer-iak']);
        $viewerRole->permissions()->attach($permIak->id);

        $approverRole = Role::create(['name' => 'finance-iak']);
        $approverRole->permissions()->attach([$permIak->id, $permApprove->id]);

        $viewer = User::factory()->create(['is_superadmin' => false]);
        $viewer->roles()->attach($viewerRole->id);

        Sanctum::actingAs($viewer);
        $this->postJson('/api/approve-or/IAK/1DIM02/approve')->assertForbidden();

        $approver = User::factory()->create(['is_superadmin' => false]);
        $approver->roles()->attach($approverRole->id);

        Sanctum::actingAs($approver);
        $this->postJson('/api/approve-or/IAK/1DIM02/approve', ['notes' => 'Disetujui'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Y')
            ->assertJsonPath('data.updated_count', 1);

        // No longer appears in pending list
        $this->getJson('/api/approve-or')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_products_reports_dashboard_and_banners_endpoints(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $this->postJson('/api/banners', [
            'badge_text' => 'PROMO',
            'title' => 'CASHBACK 5%',
            'is_active' => true,
        ])->assertCreated();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'data.banners')
            ->assertJsonPath('data.summary.pending_or_count', 2);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products');

        $this->getJson('/api/reports/activities')
            ->assertOk()
            ->assertJsonPath('data.performance.total_stock', 10);
    }
}
