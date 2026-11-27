# Checklist Perubahan & Fitur Baru Frontend (Multi-PT, RBAC, & Approve OR)

Dokumen ini merangkum seluruh hal yang perlu **diubah** dan **ditambahkan** di aplikasi Frontend/Mobile agar selaras dengan update terbaru Backend API serta revisi UI dari **Bapak Nano**.

---

## 1. Perubahan pada Autentikasi & State Global

1. **Simpan Data Role & Permission saat Login / Init App:**
   - Dari response `POST /api/login` dan `GET /api/me`, simpan:
     - `user.is_superadmin` (boolean)
     - `user.roles` (array)
     - `permissions` (array flat berisi nama permission, contoh: `["view-db-iak", "approve-or"]`)
2. **Helper Cek Akses (`can`):**
   ```js
   const can = (permissionName) => {
     if (user?.is_superadmin) return true;
     return permissions.includes(permissionName);
   };
   ```
3. **Proteksi Menu & Route:**
   - Menu **Pengaturan Sistem** (User, Role, Permission, CMS Banner) hanya ditampilkan jika `user.is_superadmin === true`.
   - Menu & tombol aksi lain dikontrol menggunakan `can('nama-permission')`.

---

## 2. Revisi Layar yang Sudah Ada

### A. Dashboard Home
- **Sumber Data:** `GET /api/dashboard`
- **Carousel Banner:** Render dari `data.banners` (tidak hardcoded lagi).
- **Card `TOTAL OMZET`:** Ambil nilai dari `data.summary.total_omset`.
- **Menu Layanan:**
  - Ubah label **`Penagihan AR`** menjadi **`Approve OR`**.

### B. Halaman `Monitoring Piutang (AR)` → Ubah Menjadi `Approve OR`
- **Sumber Data List:** `GET /api/approve-or?pt={kode}&search={keyword}`
- **Revisi Tampilan List:**
  1. **Judul AppBar:** Ubah `Monitoring Piutang (AR)` menjadi **`Approve OR`**.
  2. **Tab Filter PT (Dinamis):**
     - Jika `data.show_all_tab === true` (user punya akses > 1 PT): tampilkan chip **`Semua`** + chip PT dari `data.allowed_pts` (`IAK`, `SAM`, `RVG`).
     - Jika `data.show_all_tab === false` (user hanya punya akses 1 PT): tampilkan 1 chip PT itu saja (tanpa `Semua`).
  3. **Card Item OR (per Toko):**
     - Tampilkan badge kode PT (`pt_code`: `IAK`/`SAM`/`RVG`), **Cabang** (`cabang`), **Kode Toko** (`toko`), **Nama Toko** (`nm_toko`), **Jenis/Kota** (`jns`), dan **Total Nilai OR** (`harga`).
     - Saat card diklik, buka **Halaman / Modal Detail Cek Kredit OR** (jangan langsung approve dari list).
- **Halaman / Modal Detail Cek Kredit OR (Baru):**
  - **Sumber Data Detail:** `GET /api/approve-or/{pt_code}/{toko}`
  - **Field yang Wajib Ditampilkan:**
    - `group_code` & daftar `related_stores` (toko-toko dalam 1 grup)
    - `limit` (Plafon / Limit Kredit toko/grup)
    - `nilai_or` (Total nilai OR yang sedang diajukan)
    - `belum_jadi_faktur` (DO belum jadi faktur lintas PT)
    - `piutang_faktur` (Sisa tagihan faktur belum lunas lintas PT)
    - `total_hutang` (Total gabungan hutang, plus rincian per PT dari `debt_by_pt` jika perlu)
    - Banner peringatan merah jika `is_over_limit === true`
  - **Tombol Aksi (`Approve OR` & `Reject`):**
    - Tampilkan **hanya jika** `can('approve-or')`.
    - `Approve OR` → `POST /api/approve-or/{pt_code}/{toko}/approve` (opsional body `{"notes": "..."}`).
    - `Reject` → `POST /api/approve-or/{pt_code}/{toko}/reject`.
  - **Contoh Response Asli `GET /api/approve-or/IAK/1DIM02`:**
    ```json
    {
      "success": true,
      "data": {
        "pt_code": "IAK",
        "pt_name": "PT Istana Argo Kencana",
        "toko": "1DIM02",
        "group_code": "1DIM01",
        "related_stores": ["1DIM01", "1DIM02"],
        "limit": 0,
        "nilai_or": 81871380,
        "belum_jadi_faktur": 0,
        "piutang_faktur": 314838639,
        "total_hutang": 314838639,
        "debt_by_pt": {
          "IAK": { "belum_jadi_faktur": 0, "piutang_faktur": 314838639, "total": 314838639 },
          "SAM": { "belum_jadi_faktur": 0, "piutang_faktur": 0, "total": 0 },
          "RVG": { "belum_jadi_faktur": 0, "piutang_faktur": 0, "total": 0 }
        },
        "is_over_limit": false
      }
    }
    ```

### C. Halaman `Operasional Produk`
- **Sumber Data:** `GET /api/products?pt={kode}&category={kategori}&search={keyword}`
- Tampilkan filter PT (mengikuti `data.show_all_tab` & `data.allowed_pts`), chip kategori dari `data.categories`, dan daftar produk dari `data.products`.

### D. Halaman `Laporan & Aktivitas`
- **Sumber Data:** `GET /api/reports/activities?pt={kode}`
- Card **Performa Operasional:** gunakan `data.performance` (`approved_or_count`, `total_omset`, `total_stock`).
- Daftar **Aktivitas Terkini:** render dari `data.activities`.

---

## 3. Fitur Baru yang Perlu Ditambahkan (Menu `Pengaturan Sistem` — Khusus Superadmin)

### A. Permission Management
- **Endpoint:** `GET /api/permissions`, `POST /api/permissions`, `PUT /api/permissions/{id}`, `DELETE /api/permissions/{id}`
- **Catatan Penting:** Nama permission harus persis (*exact match*) dengan yang dicek backend/frontend:
  - `view-db-iak` (akses database PT Istana Argo Kencana)
  - `view-db-sam` (akses database PT Sambodja Lestari)
  - `view-db-rvg` (akses database PT Rovega Kirana Indonesia)
  - `approve-or` (hak eksekusi Approve/Reject OR)

### B. Lengkapi Role Management
- **Endpoint:** `GET/POST/PUT/DELETE /api/roles` dan `POST /api/roles/{id}/permissions`
- Tambahkan daftar checkbox permission (dari `GET /api/permissions`) saat membuat atau mengedit Role, lalu kirim array ID permission ke `POST /api/roles/{id}/permissions`.

### C. User Management
- **Endpoint:** `GET /api/users`, `POST /api/users`, `PUT /api/users/{id}`, `DELETE /api/users/{id}`, `POST /api/users/{id}/roles`
- **Fitur UI:**
  - Tabel/daftar user beserta badge role masing-masing.
  - Form tambah & edit user (`name`, `email`, `password`, toggle `is_superadmin`, multi-select `roles` dari `GET /api/roles`).
  - Tombol hapus user (disable untuk akun milik user yang sedang login).

### D. CMS Slide Banner
- **Endpoint:** `GET /api/banners`, `POST /api/banners`, `PUT /api/banners/{id}`, `DELETE /api/banners/{id}`
- **Field Form:** `badge_text`, `title`, `subtitle`, `image_url`, `is_active`, `sort_order`.
