# Panduan Revisi UI & Integrasi API Multi-PT (IAK, SAM, RVG)

Dokumen ini memetakan revisi UI dari catatan **Bapak Nano** serta integrasi endpoint backend terbaru ke layar aplikasi Frontend/Mobile.

---

## 1. Daftar Permission Baku yang Harus Dibuat Superadmin

| Nama Permission | Fungsi |
|---|---|
| `view-db-iak` | Akses data **PT Istana Argo Kencana (IAK)** |
| `view-db-sam` | Akses data **PT Sambodja Lestari (SAM)** |
| `view-db-rvg` | Akses data **PT Rovega Kirana Indonesia (RVG)** |
| `approve-or` | Hak mengeksekusi tombol **Approve / Reject OR** |

---

## 2. Revisi Layar per Screenshot

### A. Dashboard Home
- **Sumber API:** `GET /api/dashboard`
- **Carousel Banner:** Render dari `data.banners` (dikelola Superadmin lewat CMS Banner `/api/banners`).
- **Card `TOTAL OMZET`:** Render dari `data.summary.total_omset` (otomatis menghitung total omset dari PT yang diizinkan oleh role user).
- **Menu Layanan:**
  - Ubah label **`Penagihan AR`** menjadi **`Approve OR`**.
  - `Pengaturan Sistem` hanya tampil jika `user.is_superadmin === true`.

### B. Halaman `Approve OR` (Revisi dari `Monitoring Piutang (AR)`)
- **Sumber API:** `GET /api/approve-or?pt={kode}&status={status}&search={keyword}`
- **Perubahan UI Sesuai Coretan Revisi:**
  1. **AppBar Title:** Ubah `Monitoring Piutang (AR)` menjadi **`Approve OR`**.
  2. **Tab Filter Bar (Dinamis dari `data.allowed_pts` & `data.show_all_tab`):**
     - Jika `data.show_all_tab === true` (user punya >1 PT): tampilkan chip **`Semua`** + chip per PT (`IAK`, `SAM`, `RVG`).
     - Jika `data.show_all_tab === false` (user hanya punya 1 PT, misal `SAM`): tampilkan chip **`SAM`** saja.
  3. **Card Item OR:**
     - Header: tampilkan `document_no` + badge `pt_code` (`IAK`/`SAM`/`RVG`) + badge `status`.
     - **Informasi `CABANG`:** Tampilkan `branch_name` tepat di bawah nomor dokumen / nama toko.
     - **Nominal:** Tampilkan `amount` (Nilai OR).
     - **Tombol Aksi Merah:** Ubah `Catat Pembayaran` menjadi **`Approve OR`** (`POST /api/approve-or/{pt_code}/{id}/approve`), dan tampilkan tombol ini **hanya** jika `can('approve-or')` dan `status === 'pending'`.

### C. Halaman `Operasional Produk`
- **Sumber API:** `GET /api/products?pt={kode}&category={kategori}&search={keyword}`
- Tampilkan filter PT (jika `show_all_tab === true`), chip kategori dari `data.categories`, dan daftar produk dari `data.products`.

### D. Halaman `Laporan & Aktivitas`
- **Sumber API:** `GET /api/reports/activities?pt={kode}`
- Card **Performa Operasional** mengambil dari `data.performance` (`approved_or_count`, `total_omset`, `total_stock`).
- Daftar **Aktivitas Terkini** mengambil dari `data.activities` (sudah diurutkan terbaru lintas PT yang diizinkan).

### E. Halaman `Pengaturan Sistem -> CMS Banner` (Khusus Superadmin)
- **Sumber API:** `GET /api/banners`, `POST /api/banners`, `PUT /api/banners/{id}`, `DELETE /api/banners/{id}`.
