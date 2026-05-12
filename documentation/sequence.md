# Diagram Urutan (Sequence Diagram) - Detail Alur Fitur

## 1. Modul Keuangan (Bendahara & Admin)

### 1.1 Top-up Saldo Manual
Menjelaskan alur saat wali santri menyetor uang tunai ke Bendahara untuk mengisi saldo santri.
```mermaid
sequenceDiagram
    actor B as Bendahara
    participant S as Sistem (UI)
    participant WS as WalletService
    participant DB as Database
    
    B->>S: Input NIS & Nominal Setoran
    S->>DB: Validasi Eksistensi Santri
    DB-->>S: Santri Ditemukan
    B->>S: Klik Simpan Top-up
    S->>WS: ProcessTopup(amount)
    WS->>DB: Update wallet_balance (+)
    WS->>DB: Log wallet_transactions (CREDIT)
    DB-->>WS: Success
    WS-->>S: Return Success
    S-->>B: Cetak Bukti Top-up
```

### 1.2 Pembayaran Tagihan Manual (Bills)
Menjelaskan alur saat Bendahara memproses pelunasan tagihan santri menggunakan saldo yang tersedia.
```mermaid
sequenceDiagram
    actor B as Bendahara
    participant S as Sistem (UI)
    participant BS as BillService
    participant DB as Database
    
    B->>S: Pilih Tagihan Santri
    S->>DB: Ambil Data Tagihan & Saldo
    DB-->>S: Data Ready
    B->>S: Proses Bayar via Saldo
    S->>BS: PayWithWallet(bill_id)
    alt Saldo Cukup
        BS->>DB: Update wallet_balance (-)
        BS->>DB: Update bill.status (PAID)
        BS->>DB: Log Transaction (Snapshot Name)
        DB-->>BS: Saved
        BS-->>S: Success Message
        S-->>B: Tampilkan Bukti Bayar
    else Saldo Kurang
        BS-->>S: Error: Saldo Tidak Cukup
        S-->>B: Notifikasi Merah
    end
```

---

## 2. Modul Koperasi (Kasir & Admin Koperasi)

### 2.1 Transaksi POS (Point of Sale)
Alur belanja santri menggunakan kartu di Koperasi.
```mermaid
sequenceDiagram
    actor K as Kasir
    participant P as Sistem POS
    participant WS as WalletService
    participant DB as Database
    
    K->>P: Scan Barcode Barang
    P->>DB: Cek Harga & Stok
    DB-->>P: Data Produk OK
    K->>P: Scan Kartu Santri & Input PIN
    P->>WS: AuthorizePayment(NIS, total)
    WS->>DB: Validasi Saldo & PIN
    DB-->>WS: Authorized
    P->>DB: Kurangi Stok Barang
    P->>DB: Record Sales & Wallet Debit
    DB-->>P: Transaksi Berhasil
    P-->>K: Cetak Struk Belanja
```

### 2.2 Stok Opname (Admin Koperasi)
Alur rekonsiliasi stok fisik dengan stok sistem.
```mermaid
sequenceDiagram
    actor A as Admin Koperasi
    participant S as Sistem Koperasi
    participant DB as Database
    
    A->>S: Mulai Stok Opname
    S->>DB: Tarik Data Stok Sistem
    DB-->>S: Data Stok Saat Ini
    A->>S: Input Jumlah Fisik di Gudang
    S->>S: Hitung Selisih (Difference)
    A->>S: Finalisasi Opname
    S->>DB: Update stok Produk
    S->>DB: Log product_movements (ADJUSTMENT)
    DB-->>S: Saved
    S-->>A: Tampilkan Laporan Selisih
```

---

## 3. Modul Akademik & Presensi (Guru & Pengurus)

### 3.1 Presensi QR Santri
Alur pencatatan kehadiran harian atau kegiatan menggunakan scanner.
```mermaid
sequenceDiagram
    actor G as Pengurus/Guru
    participant S as Scanner App
    participant AS as AttendanceService
    participant DB as Database
    
    G->>S: Scan QR Kartu Santri
    S->>AS: RecordAttendance(NIS, EventID)
    AS->>DB: Verifikasi Status Santri Aktif
    DB-->>AS: Valid
    AS->>DB: Insert attendance record (Snapshot Name, Class)
    DB-->>AS: Saved
    AS-->>S: Success (Suara/Getar)
    S-->>G: Tampilkan Nama & Foto Santri
```

### 3.2 Penilaian Rapor & Kenaikan Kelas
Alur akhir semester untuk penginputan nilai dan pemindahan kelas.
```mermaid
sequenceDiagram
    actor G as Guru
    participant S as Panel Guru
    participant DB as Database
    
    G->>S: Input Nilai Per Santri
    S->>DB: Simpan student_reports
    G->>S: Buka Menu Kenaikan Kelas
    S->>DB: Ambil Daftar Santri Per Kelas
    G->>S: Pilih Santri & Klik Naik Kelas
    S->>DB: Update student.level_id (Target)
    S->>DB: Simpan Log History Kenaikan
    DB-->>S: Berhasil Diupdate
    S-->>G: Notifikasi Selesai
```

---

## 4. Modul Kesiswaan (Admin)

### 4.1 Pencatatan Izin Santri (Permissions)
Alur saat santri meminta izin pulang atau keluar pesantren.
```mermaid
sequenceDiagram
    actor A as Admin/Keamanan
    participant S as Sistem
    participant DB as Database
    
    A->>S: Input NIS & Alasan Izin
    S->>DB: Cek Status Keaktifan
    A->>S: Pilih Tanggal Kembali & Simpan
    S->>DB: Create student_permissions
    S->>DB: Simpan Snapshot Data Santri
    DB-->>S: Saved
    S-->>A: Cetak Surat Izin Jalan
```

---

## 5. Modul Super Admin (Global)

### 5.1 Onboarding Sekolah/Unit Baru
Alur pendaftaran lembaga/pesantren baru ke dalam sistem pusat.
```mermaid
sequenceDiagram
    actor SA as Super Admin
    participant S as Global Dashboard
    participant DB as Database
    
    SA->>S: Input Nama Sekolah, Slug, & Alamat
    S->>DB: Create school record
    S->>DB: Initialize Default Roles & Permissions
    S->>DB: Create Master Admin User for School
    DB-->>S: Setup Selesai
    S-->>SA: Kirim Kredensial Login ke Sekolah
```
