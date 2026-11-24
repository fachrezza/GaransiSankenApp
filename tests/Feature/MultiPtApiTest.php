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
            $schema->create('or_requests', function ($table) {
                $table->id();
                $table->string('document_no')->unique();
                $table->string('branch_name');
                $table->string('customer_code')->nullable();
                $table->string('customer_name');
                $table->unsignedBigInteger('amount');
                $table->string('status')->default('pending');
                $table->date('due_date')->nullable();
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('notes')->nullable();
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

        DB::connection('dummy_iak')->table('or_requests')->insert([
            'document_no' => 'OR/IAK/001',
            'branch_name' => 'Jakarta Pusat',
            'customer_name' => 'Toko IAK',
            'amount' => 1000000,
            'status' => 'pending',
        ]);

        DB::connection('dummy_sam')->table('or_requests')->insert([
            'document_no' => 'OR/SAM/001',
            'branch_name' => 'Bandung',
            'customer_name' => 'Toko SAM',
            'amount' => 2000000,
            'status' => 'approved',
        ]);

        DB::connection('dummy_rvg')->table('products')->insert([
            'sku' => 'SKN-RVG-1',
            'name' => 'AC Sanken',
            'category' => 'AC',
            'price' => 3000000,
            'stock' => 10,
        ]);
    }

    public function test_user_only_sees_permitted_pt_data_and_superadmin_sees_all(): void
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
            ->assertJsonCount(1, 'data.allowed_pts')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.pt_code', 'IAK');

        // Cannot query SAM
        $this->getJson('/api/approve-or?pt=SAM')->assertForbidden();

        // Superadmin sees all 3 PTs
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $this->getJson('/api/approve-or')
            ->assertOk()
            ->assertJsonPath('data.show_all_tab', true)
            ->assertJsonCount(3, 'data.allowed_pts')
            ->assertJsonCount(2, 'data.items');
    }

    public function test_approve_or_requires_permission_and_pt_access(): void
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
        $this->postJson('/api/approve-or/IAK/1/approve')->assertForbidden();

        $approver = User::factory()->create(['is_superadmin' => false]);
        $approver->roles()->attach($approverRole->id);

        Sanctum::actingAs($approver);
        $this->postJson('/api/approve-or/IAK/1/approve', ['notes' => 'OK'])
            ->assertOk()
            ->assertJsonPath('data.item.status', 'approved');
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
            ->assertJsonPath('data.summary.total_omset', 2000000);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products');

        $this->getJson('/api/reports/activities')
            ->assertOk()
            ->assertJsonPath('data.performance.total_stock', 10);
    }
}
