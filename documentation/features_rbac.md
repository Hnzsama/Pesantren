# Analisis Fitur & Hak Akses (RBAC)

## 1. Analisis Fitur Per Role

### A. Super Admin (Pusat/Yayasan)
- **Global Management**: Kelola Sekolah/Unit, Kelola Pengguna Global.
- **Kartu Santri**: Permintaan Cetak Kartu, Desain Kartu Global.
- **Keuangan Global**: Dashboard Keuangan Global, Metode Pembayaran, Kelola Penarikan Dana Sekolah (Withdrawals).
- **Hak Akses**: Kelola Roles & Permissions secara sistem.

### B. Admin Unit (Sekolah)
- **Akademik**: Tahun Ajaran, Semester, Jenjang Pendidikan, Data Kelas, Kegiatan Harian, Agenda Kegiatan.
- **Kesiswaan**: Master Santri, Wali Santri, Prestasi, Perizinan, Jadwal Sowan, Feedback.
- **Monitoring**: Dashboard Statistik Unit, Cetak Kartu Santri.
- **Administrasi**: Kelola Pengguna (dalam unit).

### C. Bendahara Unit
- **Keuangan Harian**: Top Up Manual, Bayar Tagihan Manual, Transaksi Dompet.
- **Manajemen Tagihan**: Kategori Tagihan (SPP/Kegiatan), Generate Tagihan Bulanan.
- **Settlement**: Penarikan Dana (Withdrawal), Penarikan Uang Saku Santri.

### D. Guru & Pengurus
- **Guru**: Input Rapor, Kenaikan Kelas, Berita & Pengumuman.
- **Pengurus**: Fokus pada Presensi Harian (Subuh/Maghrib) dan Presensi Kegiatan.
- **Bersama**: Scan Presensi QR, Monitoring Kedisiplinan Santri.

### E. Kasir & Admin Koperasi
- **Kasir**: Point of Sale (POS), Riwayat Penjualan.
- **Admin Koperasi**: Produk & Jasa, Stok Opname, Riwayat Stok, Kelola Kasir.

---

## 2. Pemetaan Rute dan Hak Akses (RBAC)

Aplikasi menggunakan **Spatie Laravel Permission** untuk mengisolasi akses berdasarkan role:

| Modul | Route Prefix | Middleware Role |
|---|---|---|
| **Pusat (Global)** | `/super-admin/*` | `role:super_admin` |
| **Administrasi Unit** | `/admin/*` | `role:admin` |
| **Keuangan** | `/admin/keuangan/*` | `role:admin|bendahara` |
| **Koperasi (Manajemen)** | `/koperasi/*` | `role:admin_koperasi` |
| **Koperasi (Kasir)** | `/koperasi/pos` | `role:kasir|admin_koperasi` |
| **Akademik (Guru)** | `/guru/*` | `role:guru` |
| **Presensi Scanner** | `/presensi/*` | `role:guru|pengurus|admin` |
