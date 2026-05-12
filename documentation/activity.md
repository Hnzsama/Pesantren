# Diagram Aktivitas (Activity Diagram) - Alur Fitur Lengkap

Berikut adalah diagram alir aktivitas untuk setiap modul utama dalam sistem manajemen Pesantren.

---

## 1. Modul Keuangan (Admin & Bendahara)

### 1.1 Alur Top-up Saldo & Pembayaran Tagihan
```mermaid
flowchart TD
    A([Start]) --> B[Wali Santri setor uang tunai]
    B --> C[Bendahara buka menu Top-up Manual]
    C --> D[Input NIS & Nominal]
    D --> E[Sistem Update Saldo Wallet]
    E --> F[Sistem Cek Tagihan Aktif]
    F --> G{Ada Tagihan?}
    G -- Ya --> H{Auto-Debet Aktif?}
    G -- Tidak --> K[Cetak Struk Transaksi]
    H -- Ya --> I[Sistem Potong Saldo Otomatis]
    I --> J[Update Status Tagihan - Lunas]
    J --> K
    H -- Tidak --> L[Bendahara Proses Bayar Manual]
    L --> K
    K --> M([Stop])
```

### 1.2 Alur Penarikan Dana & Uang Saku
```mermaid
flowchart TD
    A([Start]) --> B[Santri Request Penarikan]
    B --> C[Bendahara Scan Kartu Santri]
    C --> D{Saldo Mencukupi?}
    D -- Ya --> E[Input Nominal Penarikan]
    E --> F[Santri Input PIN]
    F --> G{PIN Valid?}
    G -- Ya --> H[Sistem Debit Saldo]
    H --> I[Bendahara Serahkan Uang Tunai]
    I --> J[Catat Log Transaksi]
    J --> K([Stop])
    G -- Tidak --> L[Error: PIN Salah]
    L --> K
    D -- Tidak --> M[Error: Saldo Kurang]
    M --> K
```

---

## 2. Modul Akademik & Kesiswaan (Admin & Guru)

### 2.1 Alur Registrasi hingga Penempatan Kelas
```mermaid
flowchart TD
    A([Start]) --> B[Admin Upload Data Santri - Excel]
    B --> C[Sistem Validasi & Simpan Data]
    C --> D[Admin Setup Tahun Ajaran & Semester]
    D --> E[Admin Buat Data Kelas & Jenjang]
    E --> F[Proses Plotting Santri ke Kelas]
    F --> G[Sistem Generate ID/Barcode Kartu]
    G --> H[Cetak Kartu Santri]
    H --> I([Stop])
```

### 2.2 Alur Kenaikan Kelas & Rapor
```mermaid
flowchart TD
    A([Start]) --> B[Guru Input Nilai Akademik]
    B --> C[Guru Input Catatan Wali Kelas]
    C --> D[Sistem Generate Rapor Digital]
    D --> E[Admin Buka Panel Kenaikan Kelas]
    E --> F[Filter Santri Lulus/Naik]
    F --> G[Update Level & Kelas Tujuan]
    G --> H[Sistem Arsipkan Riwayat Akademik]
    H --> I([Stop])
```

---

## 3. Modul Koperasi & POS (Kasir & Admin Koperasi)

### 3.1 Alur Transaksi Belanja (POS)
```mermaid
flowchart TD
    A([Start]) --> B[Kasir Scan Barcode Produk]
    B --> C[Kasir Scan Kartu Santri]
    C --> D[Sistem Verifikasi Saldo]
    D --> E{Saldo > Total?}
    E -- Ya --> F[Santri Input PIN]
    F --> G[Proses Pembayaran]
    G --> H[Sistem Kurangi Stok Barang]
    H --> I[Cetak Struk Penjualan]
    I --> J([Stop])
    E -- Tidak --> K[Tampilkan Gagal: Saldo Kurang]
    K --> J
```

### 3.2 Alur Stok Opname
```mermaid
flowchart TD
    A([Start]) --> B[Admin Koperasi pilih menu Stok Opname]
    B --> C[Sistem kunci transaksi sementara]
    C --> D[Admin hitung stok fisik di rak]
    D --> E[Input Jumlah Fisik ke Sistem]
    E --> F[Sistem bandingkan dengan Stok Data]
    F --> G{Ada Selisih?}
    G -- Ya --> H[Catat Alasan Penyesuaian]
    H --> I[Sistem Adjust Stok Produk]
    I --> J[Finalisasi & Simpan Riwayat]
    G -- Tidak --> J
    J --> K([Stop])
```

---

## 4. Modul Presensi & Kedisiplinan (Guru & Pengurus)

### 4.1 Alur Presensi Harian/Kegiatan
```mermaid
flowchart TD
    A([Start]) --> B[Pengurus buka aplikasi Scanner]
    B --> C[Pilih Jenis Kegiatan]
    C --> D[Scan Kartu/QR Santri]
    D --> E{Santri Aktif?}
    E -- Ya --> F[Log Kehadiran - Hadir]
    F --> G[Tampilkan Nama & Foto]
    G --> H{Ada Santri Lagi?}
    E -- Tidak --> I[Peringatan: Santri Non-aktif]
    I --> H
    H -- Ya --> D
    H -- Tidak --> J[Rekapitulasi Kehadiran]
    J --> K([Stop])
```

### 4.2 Alur Perizinan Santri (Permissions)
```mermaid
flowchart TD
    A([Start]) --> B[Santri ajukan izin - Sakit/Pulang]
    B --> C[Admin/Guru Input Detail Izin]
    C --> D[Tentukan Estimasi Kembali]
    D --> E[Sistem Generate Surat Izin]
    E --> F[Sistem Update Status di Dashboard]
    F --> G[Petugas Keamanan Verifikasi saat Keluar]
    G --> H([Stop])
```

---

## 5. Modul Super Admin (Pusat)

### 5.1 Alur Manajemen Multi-Tenancy & Withdrawal
```mermaid
flowchart TD
    A([Start]) --> B[Super Admin Daftarkan Unit Sekolah]
    B --> C[Setup Admin & Hak Akses Unit]
    C --> D[Tentukan Biaya Admin/Metode Bayar]
    D --> E[Sekolah jalankan operasional]
    E --> F[Admin Sekolah ajukan Penarikan Dana]
    F --> G[Super Admin Verifikasi Saldo Sekolah]
    G --> H[Proses Transfer/Pencairan]
    H --> I[Update Status Withdrawal]
    I --> J([Stop])
```
