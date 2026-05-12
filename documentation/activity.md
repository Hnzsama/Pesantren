# Diagram Aktivitas (Activity Diagram) - Alur Fitur Lengkap

## 1. Modul Keuangan (Admin & Bendahara)

### 1.1 Alur Top-up Saldo & Pembayaran Tagihan
```mermaid
activityDiagram
    start
    :Wali Santri setor uang tunai;
    :Bendahara buka menu Top-up Manual;
    :Input NIS & Nominal;
    :Sistem Update Saldo Wallet;
    :Sistem Cek Tagihan Aktif;
    if (Ada Tagihan?) then (Ya)
        if (Auto-Debet Aktif?) then (Ya)
            :Sistem Potong Saldo Otomatis;
            :Update Status Tagihan (Lunas);
        else (Tidak)
            :Bendahara Proses Bayar Manual;
        endif
    endif
    :Cetak Struk Transaksi;
    stop
```

### 1.2 Alur Penarikan Dana & Uang Saku
```mermaid
activityDiagram
    start
    :Santri Request Penarikan;
    :Bendahara Scan Kartu Santri;
    if (Saldo Mencukupi?) then (Ya)
        :Input Nominal Penarikan;
        :Santri Input PIN;
        if (PIN Valid?) then (Ya)
            :Sistem Debit Saldo;
            :Bendahara Serahkan Uang Tunai;
            :Catat Log Transaksi;
        else (Tidak)
            :Error: PIN Salah;
        endif
    else (Tidak)
        :Error: Saldo Kurang;
    endif
    stop
```

---

## 2. Modul Akademik & Kesiswaan (Admin & Guru)

### 2.1 Alur Registrasi hingga Penempatan Kelas
```mermaid
activityDiagram
    start
    :Admin Upload Data Santri (Excel);
    :Sistem Validasi & Simpan Data;
    :Admin Setup Tahun Ajaran & Semester;
    :Admin Buat Data Kelas & Jenjang;
    :Proses Plotting Santri ke Kelas;
    :Sistem Generate ID/Barcode Kartu;
    :Cetak Kartu Santri;
    stop
```

### 2.2 Alur Kenaikan Kelas & Rapor
```mermaid
activityDiagram
    start
    :Guru Input Nilai Akademik;
    :Guru Input Catatan Wali Kelas;
    :Sistem Generate Rapor Digital;
    :Admin Buka Panel Kenaikan Kelas;
    :Filter Santri Lulus/Naik;
    :Update Level & Kelas Tujuan;
    :Sistem Arsipkan Riwayat Akademik;
    stop
```

---

## 3. Modul Koperasi & POS (Kasir & Admin Koperasi)

### 3.1 Alur Transaksi Belanja (POS)
```mermaid
activityDiagram
    start
    :Kasir Scan Barcode Produk;
    :Kasir Scan Kartu Santri;
    :Sistem Verifikasi Saldo;
    if (Saldo > Total?) then (Ya)
        :Santri Input PIN;
        :Proses Pembayaran;
        :Sistem Kurangi Stok Barang;
        :Cetak Struk Penjualan;
    else (Tidak)
        :Tampilkan Gagal: Saldo Kurang;
    endif
    stop
```

### 3.2 Alur Stok Opname
```mermaid
activityDiagram
    start
    :Admin Koperasi pilih menu Stok Opname;
    :Sistem kunci transaksi sementara;
    :Admin hitung stok fisik di rak;
    :Input Jumlah Fisik ke Sistem;
    :Sistem bandingkan dengan Stok Data;
    if (Ada Selisih?) then (Ya)
        :Catat Alasan Penyesuaian;
        :Sistem Adjust Stok Produk;
    endif
    :Finalisasi & Simpan Riwayat;
    stop
```

---

## 4. Modul Presensi & Kedisiplinan (Guru & Pengurus)

### 4.1 Alur Presensi Harian/Kegiatan
```mermaid
activityDiagram
    start
    :Pengurus buka aplikasi Scanner;
    :Pilih Jenis Kegiatan;
    while (Ada Santri?) is (Ya)
        :Scan Kartu/QR Santri;
        if (Santri Aktif?) then (Ya)
            :Log Kehadiran (Hadir);
            :Tampilkan Nama & Foto;
        else (Tidak)
            :Peringatan: Santri Non-aktif;
        endif
    endwhile (Selesai)
    :Rekapitulasi Kehadiran;
    stop
```

### 4.2 Alur Perizinan Santri (Permissions)
```mermaid
activityDiagram
    start
    :Santri ajukan izin (Sakit/Pulang);
    :Admin/Guru Input Detail Izin;
    :Tentukan Estimasi Kembali;
    :Sistem Generate Surat Izin;
    :Sistem Update Status di Dashboard;
    :Petugas Keamanan Verifikasi saat Keluar;
    stop
```

---

## 5. Modul Super Admin (Pusat)

### 5.1 Alur Manajemen Multi-Tenancy & Withdrawal
```mermaid
activityDiagram
    start
    :Super Admin Daftarkan Unit Sekolah;
    :Setup Admin & Hak Akses Unit;
    :Tentukan Biaya Admin/Metode Bayar;
    :Sekolah jalankan operasional;
    :Admin Sekolah ajukan Penarikan Dana;
    :Super Admin Verifikasi Saldo Sekolah;
    :Proses Transfer/Pencairan;
    :Update Status Withdrawal;
    stop
```
