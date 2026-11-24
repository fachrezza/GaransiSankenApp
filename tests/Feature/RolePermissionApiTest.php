<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_endpoints_require_authentication(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->postJson('/api/roles', ['name' => 'teknisi'])->assertUnauthorized();
        $this->getJson('/api/permissions')->assertUnauthorized();
        $this->postJson('/api/permissions', ['name' => 'view-warranty'])->assertUnauthorized();
    }

    public function test_non_superadmin_is_forbidden_from_rbac_endpoints(): void
    {
        $user = User::factory()->create(['is_superadmin' => false]);
        $targetUser = User::factory()->create();
        $role = Role::create(['name' => 'teknisi']);

        Sanctum::actingAs($user);

        $this->getJson('/api/roles')
            ->assertForbidden()
            ->assertJson(['success' => false]);

        $this->postJson('/api/roles', ['name' => 'cs'])->assertForbidden();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'cs'])->assertForbidden();
        $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
        $this->postJson("/api/roles/{$role->id}/permissions", ['permissions' => []])->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
        $this->postJson('/api/permissions', ['name' => 'view-warranty'])->assertForbidden();
        $this->postJson("/api/users/{$targetUser->id}/roles", ['roles' => []])->assertForbidden();
    }

    public function test_superadmin_can_crud_roles(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $createResponse = $this->postJson('/api/roles', [
            'name' => 'teknisi',
            'description' => 'Petugas servis',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'teknisi');

        $roleId = $createResponse->json('data.role.id');

        // Duplicate name fails validation
        $this->postJson('/api/roles', ['name' => 'teknisi'])->assertUnprocessable();

        // Update name only (partial update)
        $this->putJson("/api/roles/{$roleId}", [
            'name' => 'teknisi-lapangan',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'teknisi-lapangan')
            ->assertJsonPath('data.role.description', 'Petugas servis');

        // Index returns roles with permissions relation
        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonStructure(['data' => ['roles' => [['id', 'name', 'description', 'permissions']]]]);

        // Delete removes role
        $this->deleteJson("/api/roles/{$roleId}")
            ->assertOk()
            ->assertJson(['success' => true, 'data' => null]);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_superadmin_can_list_and_create_permissions(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $this->postJson('/api/permissions', [
            'name' => 'approve-claim',
            'description' => 'Menyetujui klaim',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permission.name', 'approve-claim');

        $this->postJson('/api/permissions', ['name' => 'approve-claim'])
            ->assertUnprocessable();

        $this->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.permissions');
    }

    public function test_superadmin_can_sync_and_clear_role_permissions(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $role = Role::create(['name' => 'cs']);
        $p1 = Permission::create(['name' => 'view-warranty']);
        $p2 = Permission::create(['name' => 'create-claim']);

        $this->postJson("/api/roles/{$role->id}/permissions", [
            'permissions' => [$p1->id, $p2->id],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.role.permissions');

        // Empty array clears all permissions
        $this->postJson("/api/roles/{$role->id}/permissions", [
            'permissions' => [],
        ])->assertOk()
            ->assertJsonCount(0, 'data.role.permissions');

        // Non-existent permission fails validation
        $this->postJson("/api/roles/{$role->id}/permissions", [
            'permissions' => [9999],
        ])->assertUnprocessable();
    }

    public function test_superadmin_can_sync_and_clear_user_roles(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $targetUser = User::factory()->create();
        $r1 = Role::create(['name' => 'teknisi']);
        $r2 = Role::create(['name' => 'cs']);

        $this->postJson("/api/users/{$targetUser->id}/roles", [
            'roles' => [$r1->id, $r2->id],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.user.roles');

        // Empty array clears all user roles
        $this->postJson("/api/users/{$targetUser->id}/roles", [
            'roles' => [],
        ])->assertOk()
            ->assertJsonCount(0, 'data.user.roles');

        $this->postJson("/api/users/{$targetUser->id}/roles", [
            'roles' => [9999],
        ])->assertUnprocessable();
    }

    public function test_permission_middleware_enforces_user_permissions_and_superadmin_bypass(): void
    {
        Route::middleware(['auth:sanctum', 'permission:view-warranty'])
            ->get('/api/_test/warranties', fn () => response()->json(['success' => true]));

        $permission = Permission::create(['name' => 'view-warranty']);
        $role = Role::create(['name' => 'cs']);
        $role->permissions()->attach($permission->id);

        $userWithoutRole = User::factory()->create(['is_superadmin' => false]);
        Sanctum::actingAs($userWithoutRole);
        $this->getJson('/api/_test/warranties')
            ->assertForbidden()
            ->assertJson(['success' => false]);

        $userWithRole = User::factory()->create(['is_superadmin' => false]);
        $userWithRole->roles()->attach($role->id);
        Sanctum::actingAs($userWithRole);
        $this->getJson('/api/_test/warranties')
            ->assertOk()
            ->assertJson(['success' => true]);

        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);
        $this->getJson('/api/_test/warranties')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_superadmin_can_update_and_delete_permissions(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        Sanctum::actingAs($superadmin);

        $permission = Permission::create(['name' => 'view-omset', 'description' => 'Lama']);

        $this->putJson("/api/permissions/{$permission->id}", [
            'name' => 'view-laporan-omset',
            'description' => 'Lihat laporan omset',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permission.name', 'view-laporan-omset');

        $this->deleteJson("/api/permissions/{$permission->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'data' => null]);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_superadmin_can_crud_users_and_cannot_delete_self(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $role = Role::create(['name' => 'finance']);
        Sanctum::actingAs($superadmin);

        $createResponse = $this->postJson('/api/users', [
            'name' => 'Staff Finance',
            'email' => 'finance@sanken.test',
            'password' => 'password123',
            'roles' => [$role->id],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'finance@sanken.test')
            ->assertJsonCount(1, 'data.user.roles');

        $userId = $createResponse->json('data.user.id');

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.users');

        $this->putJson("/api/users/{$userId}", [
            'name' => 'Senior Finance',
            'roles' => [],
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Senior Finance')
            ->assertJsonCount(0, 'data.user.roles');

        // Cannot delete self
        $this->deleteJson("/api/users/{$superadmin->id}")
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->deleteJson("/api/users/{$userId}")
            ->assertOk()
            ->assertJson(['success' => true, 'data' => null]);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_login_and_me_return_roles_and_flat_permissions(): void
    {
        $permission = Permission::create(['name' => 'view-laporan-omset']);
        $role = Role::create(['name' => 'finance']);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'email' => 'staff@sanken.test',
            'password' => bcrypt('password123'),
            'is_superadmin' => false,
        ]);
        $user->roles()->attach($role->id);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'staff@sanken.test',
            'password' => 'password123',
            'device_name' => 'postman',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('permissions', ['view-laporan-omset'])
            ->assertJsonCount(1, 'user.roles');

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('permissions', ['view-laporan-omset'])
            ->assertJsonCount(1, 'roles');
    }
}
