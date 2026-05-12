# Pesantren Management System

[![View Documentation](https://img.shields.io/badge/View-Documentation-blue?style=for-the-badge&logo=markdown)](./documentation.md)

Sistem Manajemen Pesantren terpadu yang dirancang untuk mengotomatisasi administrasi akademik, keuangan, kesiswaan, hingga operasional koperasi pesantren dalam satu platform terintegrasi.

## 🚀 Teknologi Utama
Aplikasi ini dibangun dengan *modern web stack* untuk memastikan performa tinggi dan pengalaman pengguna yang reaktif:
- **Backend**: Laravel 11 (PHP 8.2+)
- **UI Framework**: Livewire 3 (Reaktif tanpa full page reload)
- **Styling**: Tailwind CSS & Flux UI Components
- **Auth & Security**: Laravel Fortify & Spatie Permission (RBAC)
- **Database**: ULID for Primary Keys (Scalability)
- **Testing**: Pest PHP (High coverage functional testing)
- **Architecture**: Domain-Driven Design approach for modular features

---

## 🏛️ Gambaran Besar Fitur (Role-Based)

### 1. 🛡️ Super Admin
Mengelola infrastruktur sistem secara keseluruhan:
- **Platform**: Dashboard Global & Berita.
- **Administrasi**: Permintaan Cetak Kartu, Desain Kartu Santri, Kelola Sekolah, Kelola Pengguna & Hak Akses (RBAC).
- **Keuangan**: Dashboard Keuangan Global, Metode Pembayaran, & Kelola Penarikan Dana.

### 2. 👨‍💼 Admin Pesantren
Fokus pada manajemen operasional harian:
- **Akademik**: Tahun Ajaran, Semester, Jenjang Pendidikan, Kelas, & Kegiatan.
- **Kesiswaan**: Data Santri, Wali Santri, Prestasi, Perizinan, Jadwal Sowan, & Feedback.
- **Kartu Santri**: Cetak & Manajemen Kartu Digital.
- **Administrasi**: Kelola Pengguna (Staff/Guru).

### 3. 💰 Bendahara
Manajemen sirkulasi uang di ekosistem pesantren:
- **Keuangan**: Dashboard Keuangan, Top Up Manual (Wallet), & Pembayaran Tagihan Manual.
- **Transaksi**: Riwayat Transaksi Dompet Digital Santri.
- **Tagihan**: Kelola Kategori Tagihan & Tagihan Kegiatan.
- **Dana**: Penarikan Dana & Penarikan Uang Saku Santri.

### 4. 🛒 Admin & Kasir Koperasi
Operasional unit bisnis pesantren:
- **Koperasi**: Manajemen Produk, Stok Opname, & Riwayat Stok.
- **Point of Sale (POS)**: Transaksi penjualan menggunakan Kartu Santri (Wallet).
- **Kasir**: Manajemen akun kasir dan riwayat pembelian.

### 5. 👨‍🏫 Guru
Fokus pada perkembangan santri:
- **Berita & Pengumuman**.
- **Presensi**: Melakukan Presensi Harian & Kegiatan.
- **Laporan**: Input Rapor Santri & Rekomendasi Kenaikan Kelas.

### 6. 👮‍♂️ Pengurus (Security/Staff)
Fokus pada mobilitas dan kehadiran:
- **Scanner**: Melakukan scan Kartu Santri untuk Presensi Harian & Kegiatan.

---

## 🛠️ Instalasi & Pengembangan

1. **Clone Repository**
   ```bash
   git clone https://github.com/Hnzsama/Pesantren.git
   cd Pesantren
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install && npm run dev
   ```

3. **Setup Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Migration & Seeding**
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Running Tests**
   ```bash
   php artisan test
   ```

---

## 📈 Recent Updates
- **Attendance Workflow**: Stabilisasi sistem presensi berbasis QR-Scan dengan validasi scope level dan activity.
- **RBAC Refinement**: Pengetatan akses rute antara Admin, Guru, dan Bendahara.
- **Financial Module**: Implementasi *profit sharing* sistem dan fee administrasi pada top-up manual.
- **Testing Suite**: Peningkatan coverage test hingga 76 skenario fungsional dengan status 100% PASS.

---
*Developed with ❤️ by Deepmind Advanced Agentic Coding Team.*
