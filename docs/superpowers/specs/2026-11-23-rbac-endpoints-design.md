# Desain Implementasi RBAC API Endpoints (Bagian 3 & 4)

## Intent & Why
Menyediakan backend controller, endpoint, dan middleware otorisasi RBAC sesuai Bagian 3 dan Bagian 4 pada `docs/SYSTEM_DESIGN_ROLE_PERMISSION.md` agar aplikasi mobile (Superadmin) dapat mengelola Role, Permission, serta penetapan Role ke User, dan endpoint fitur berikutnya dapat langsung memakai pengecekan permission.

## Goals, Non-Goals, Anti-Goals
- **Goals**:
  - Mengimplementasikan 8 endpoint persis seperti daftar di Bagian 3 `docs/SYSTEM_DESIGN_ROLE_PERMISSION.md`, dilindungi `auth:sanctum` + `superadmin`.
  - Format response JSON seragam `{success, message, data}` mengikuti `DashboardController`.
  - Menyediakan middleware `permission:<name>` (Bagian 4) yang memakai `$request->user()->hasPermission(...)`.
  - Menyertakan Feature test PHPUnit untuk seluruh endpoint dan middleware.
- **Non-Goals**:
  - Tidak mengubah `AuthController` (`/login`, `/register`, `/me`), `DashboardController`, maupun `DatabaseSeeder`.
  - Tidak menambah endpoint di luar Bagian 3 (mis. `GET /api/users`, `PUT/DELETE /api/permissions`).
  - Tidak membangun fitur bisnis garansi/klaim.
- **Anti-Goals**:
  - Endpoint RBAC bisa diakses user non-superadmin (`is_superadmin = false`).
  - Menambah boilerplate `FormRequest`, `JsonResource`, atau `Service` layer yang tidak dibutuhkan.

## Constraints
- Laravel 13 (`^13.17`) dengan Sanctum (`auth:sanctum`).
- Tabel dan model `User`, `Role`, `Permission` beserta `hasRole()` dan `hasPermission()` sudah tersedia.
- `bootstrap/app.php` sudah me-render JSON untuk semua exception pada `api/*`.

## Approaches
1. **Pendekatan A — 3 Controller per resource + 2 Middleware tipis (Dipilih)**
   - `RoleController`, `PermissionController`, `UserRoleController` di `app/Http/Controllers/Api/`, plus `EnsureSuperadmin` dan `CheckPermission` di `app/Http/Middleware/`.
   - *Trade-off*: Ringkas, konsisten dengan pola `AuthController` dan `DashboardController`, tanpa boilerplate ekstra.
2. **Pendekatan B — 1 `RbacController` terpadu + 2 Middleware**
   - *Trade-off*: Lebih sedikit 2 file, tetapi mencampur 3 resource (`roles`, `permissions`, `users`) dalam satu kelas.
3. **Pendekatan C — `FormRequest` + `JsonResource` + `Service` layer**
   - *Trade-off*: Over-engineering untuk 8 endpoint CRUD/sync sederhana.

## Decisions Made
- **Routes (`routes/api.php`)**: Dalam grup `middleware(['auth:sanctum', 'superadmin'])`:
  - `GET /api/roles` -> `RoleController@index` (eager load `permissions`)
  - `POST /api/roles` -> `RoleController@store` (`201`)
  - `PUT /api/roles/{role}` -> `RoleController@update` (mendukung update parsial `name`/`description` dengan `sometimes|required` pada `name` dan unique ignore `{role}`)
  - `DELETE /api/roles/{role}` -> `RoleController@destroy`
  - `POST /api/roles/{role}/permissions` -> `RoleController@syncPermissions` (`permissions: present|array`, `permissions.*: integer|exists:permissions,id`, memanggil `$role->permissions()->sync(...)`)
  - `GET /api/permissions` -> `PermissionController@index`
  - `POST /api/permissions` -> `PermissionController@store` (`201`)
  - `POST /api/users/{user}/roles` -> `UserRoleController@syncRoles` (`roles: present|array`, `roles.*: integer|exists:roles,id`, memanggil `$user->roles()->sync(...)`)
- **Middleware (`app/Http/Middleware/` & `bootstrap/app.php`)**:
  - `EnsureSuperadmin` (alias `superadmin`): tolak dengan `403` JSON `{success: false, message: ...}` jika `!$request->user()?->is_superadmin`.
  - `CheckPermission` (alias `permission`): menerima parameter `$permission`, tolak dengan `403` JSON `{success: false, message: ...}` jika `!$request->user()?->hasPermission($permission)`.
- **Data & Seeder**: `DatabaseSeeder.php` tidak diubah; verifikasi melalui PHPUnit Feature tests (termasuk test sementara/route uji untuk `permission` middleware).

## Left to the Builder
- Penulisan pesan `message` JSON yang jelas dan konsisten.
- Penulisan test cases di `tests/Feature/` untuk menguji 401, 403, 422, happy paths, empty array `[]` pada sync, dan bypass `is_superadmin` pada `CheckPermission`.
