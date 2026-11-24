# Panduan Integrasi Frontend & Update Backend (RBAC & User Management)

Dokumen ini berisi ringkasan **update terbaru pada Backend API** serta **daftar fitur/penyesuaian yang perlu ditambahkan di Frontend**.

---

## Bagian 1: Update Terbaru dari Backend

Seluruh endpoint di bawah ini berada di bawah prefix `/api` dan menggunakan autentikasi **Bearer Token (Sanctum)**.

### 1. Auth (`login` & `me`) Kini Menyertakan `roles` dan `permissions`
Saat user berhasil login atau memanggil `/api/me`, backend kini langsung mengirimkan daftar `roles` beserta array flat `permissions` agar frontend bisa langsung memfilter menu dan tombol.

- **`POST /api/login`**
  - **Body:** `{ "email": "...", "password": "...", "device_name": "web" }`
  - **Response (200):**
    ```json
    {
      "message": "Login successful",
      "user": {
        "id": 2,
        "name": "Staff Finance",
        "email": "finance@sanken.test",
        "is_superadmin": false,
        "roles": [
          {
            "id": 1,
            "name": "finance",
            "permissions": [
              { "id": 1, "name": "view-laporan-omset" }
            ]
          }
        ]
      },
      "permissions": ["view-laporan-omset"],
      "token": "1|abcdef..."
    }
    ```
- **`GET /api/me`**
  - Mengembalikan objek user lengkap dengan `roles` dan array `permissions` di root JSON.

---

### 2. Permission Management (Khusus `is_superadmin = true`)
| Method | Endpoint | Deskripsi | Request Body |
|---|---|---|---|
| `GET` | `/api/permissions` | Ambil semua daftar permission | - |
| `POST` | `/api/permissions` | Buat permission baru | `{"name": "view-laporan-omset", "description": "..."}` |
| `PUT` | `/api/permissions/{id}` | Edit nama/deskripsi permission | `{"name": "...", "description": "..."}` |
| `DELETE` | `/api/permissions/{id}` | Hapus permission | - |

---

### 3. Role Management (Khusus `is_superadmin = true`)
| Method | Endpoint | Deskripsi | Request Body |
|---|---|---|---|
| `GET` | `/api/roles` | Ambil daftar role beserta `permissions` | - |
| `POST` | `/api/roles` | Buat role baru | `{"name": "finance", "description": "..."}` |
| `PUT` | `/api/roles/{id}` | Edit role | `{"name": "...", "description": "..."}` |
| `DELETE` | `/api/roles/{id}` | Hapus role | - |
| `POST` | `/api/roles/{id}/permissions` | Pasang/sync daftar permission ID ke role | `{"permissions": [1, 2, 3]}` *(kirim `[]` untuk mengosongkan)* |

---

### 4. User Management (Khusus `is_superadmin = true`)
| Method | Endpoint | Deskripsi | Request Body |
|---|---|---|---|
| `GET` | `/api/users` | Ambil semua user beserta `roles` | - |
| `POST` | `/api/users` | Buat akun user baru (opsional langsung pasang role) | `{"name": "...", "email": "...", "password": "min 8 char", "is_superadmin": false, "roles": [1]}` |
| `PUT` | `/api/users/{id}` | Edit data user, password (opsional), dan `roles` | `{"name": "...", "email": "...", "password": "...", "roles": [1, 2]}` |
| `DELETE` | `/api/users/{id}` | Hapus user *(tidak bisa menghapus akun sendiri -> error 422)* | - |
| `POST` | `/api/users/{id}/roles` | Khusus update/sync role user | `{"roles": [1, 2]}` |

---

## Bagian 2: Apa Saja yang Perlu Ditambahkan di Frontend

### 1. Simpan State Auth (`user`, `is_superadmin`, dan `permissions`)
Saat `POST /api/login` sukses (dan saat inisialisasi app memanggil `GET /api/me`):
- Simpan `user.is_superadmin` dan array `permissions` ke state global (Context / Pinia / Redux / Zustand).
- Buat helper pengecekan akses di frontend:
  ```js
  const can = (permissionName) => {
    if (user?.is_superadmin) return true;
    return permissions.includes(permissionName);
  };
  ```

### 2. Proteksi Menu Sidebar & Route Halaman
- **Menu System Setting (CMS, User & Role Management):** Tampilkan hanya jika `user.is_superadmin === true` (atau sesuai permission jika nanti dibuka untuk non-superadmin).
- **Menu/Halaman Lain (Dashboard, Laporan Omset, Pengajuan Limit):** Gunakan `can('nama-permission')` untuk menyembunyikan menu di sidebar dan mengarahkan ke halaman *403 / Forbidden* jika URL diakses paksa.
- **Aksi/Tombol di Dalam Halaman:** Gunakan `can('create-pengajuan-limit')`, `can('approve-pengajuan-limit')`, dll. untuk menampilkan/menyembunyikan tombol aksi.

### 3. Halaman/Tab Permission Management (di System Setting)
Karena permission diinput langsung oleh Superadmin (tanpa seeder), sediakan UI sederhana untuk CRUD Permission (`GET`, `POST`, `PUT`, `DELETE /api/permissions`) agar daftar permission tersedia sebelum membuat/mengatur Role.
- **Penting:** Pastikan string `name` permission yang diinput Superadmin **sama persis** dengan string yang dicek oleh fungsi `can('...')` di frontend.
- Contoh penamaan standar untuk halaman saat ini:
  - `view-dashboard`
  - `view-cms`, `edit-cms`
  - `view-laporan-omset`, `export-laporan-omset`
  - `view-pengajuan-limit`, `create-pengajuan-limit`, `approve-pengajuan-limit`

### 4. Lengkapi Halaman Role Management
- Saat membuat atau mengedit Role, tampilkan daftar checkbox dari `GET /api/permissions`.
- Setelah menyimpan role (`POST /api/roles` atau `PUT /api/roles/{id}`), kirim daftar ID permission yang dicentang ke `POST /api/roles/{id}/permissions`.

### 5. Buat Halaman User Management (di System Setting)
- **Tabel Daftar User:** Ambil data dari `GET /api/users` (menampilkan nama, email, status superadmin, dan badge role yang dimiliki).
- **Modal/Form Tambah & Edit User:**
  - Input: `name`, `email`, `password` (wajib saat tambah, opsional saat edit), checkbox `is_superadmin`, dan multi-select/checkbox `roles` (ambil pilihannya dari `GET /api/roles`).
  - Submit ke `POST /api/users` atau `PUT /api/users/{id}`.
- **Tombol Hapus User:** Panggil `DELETE /api/users/{id}` (sembunyikan/disable tombol hapus untuk akun milik user yang sedang login).
