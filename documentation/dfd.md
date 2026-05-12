# Arsitektur Aliran Data (DFD) - Detail Fitur Lengkap

## 1. DFD Level 0 (Context Diagram)
Menggambarkan interaksi sistem SIAP dengan seluruh entitas pengguna berdasarkan fitur yang tersedia.

```mermaid
graph TD
    %% Entitas
    SA((Super Admin))
    AU((Admin Unit))
    BD((Bendahara))
    KS((Kasir/Koperasi))
    GR((Guru/Pengurus))
    SN((Santri/Wali))

    %% Sistem
    SIAP{{"Sistem Informasi\nAkademik Pesantren (SIAP)"}}

    %% SA Flows
    SA -- "Setup Sekolah, Desain Kartu,\nMetode Pembayaran, RBAC" --> SIAP
    SIAP -- "Laporan Global, Status Withdraw,\nLog Audit Sistem" --> SA

    %% AU Flows
    AU -- "Master Data, Akademik,\nNews, Cetak Kartu" --> SIAP
    SIAP -- "Statistik Unit, Preview Rapor,\nLog Aktivitas Pengguna" --> AU

    %% BD Flows
    BD -- "Topup Manual, Setup Tagihan,\nPenarikan Dana" --> SIAP
    SIAP -- "Laporan Kas, Riwayat Wallet,\nStatus Tunggakan" --> BD

    %% KS Flows
    KS -- "Data Produk, Transaksi POS,\nStok Opname" --> SIAP
    SIAP -- "Struk Belanja, Validasi Saldo,\nLaporan Laba Rugi Koperasi" --> KS

    %% GR Flows
    GR -- "Absensi QR, Input Rapor,\nData Prestasi" --> SIAP
    SIAP -- "Jadwal, Daftar Santri,\nStatus Kehadiran" --> GR

    %% SN Flows
    SN -- "Setoran Dana, Scan Kartu,\nFeedback" --> SIAP
    SIAP -- "Notifikasi Transaksi, Rapor,\nSisa Saldo" --> SN
```

---

## 2. DFD Level 1 (Diagram Proses Utama)
Sistem dipecah menjadi 7 modul utama untuk mencakup seluruh fitur yang disebutkan.

```mermaid
graph TD
    %% Proses
    P1(("1.0\nAdministrasi\nGlobal (SA)"))
    P2(("2.0\nManajemen\nMaster & Akademik"))
    P3(("3.0\nKesiswaan &\nKedisiplinan"))
    P4(("4.0\nSistem Keuangan\n& Wallet"))
    P5(("5.0\nPoint of Sale &\nKoperasi"))
    P6(("6.0\nPresensi &\nPelaporan"))
    P7(("7.0\nInformasi &\nPlatform"))

    %% Data Stores
    DS1[("D1: Schools & Config")]
    DS2[("D2: Users & Roles")]
    DS3[("D3: Students & Guardians")]
    DS4[("D4: Academic Records")]
    DS5[("D5: Wallet & Bills")]
    DS6[("D6: Products & Sales")]
    DS7[("D7: Attendance & Reports")]

    %% Aliran
    SA -- "Config" --> P1
    P1 <--> DS1
    P1 <--> DS2

    AU -- "Academic/Student" --> P2
    P2 <--> DS3
    P2 <--> DS4

    GR -- "Achievements" --> P3
    P3 <--> DS3
    P3 -- "Permissions" --> DS3

    BD -- "Finance" --> P4
    P4 <--> DS5
    P4 -- "Wallet Update" --> DS3

    KS -- "POS/Stok" --> P5
    P5 <--> DS6
    P5 -- "Potong Saldo" --> P4

    SN -- "Attendance" --> P6
    P6 <--> DS7

    AU -- "News" --> P7
    P7 <--> DS1
```

---

## 3. DFD Level 2 (Detil Proses Per Modul)

### 3.1 Detil Proses 2.0 (Akademik & Kesiswaan)
Mencakup fitur: Tahun Ajaran, Semester, Kelas, Santri, Wali, Prestasi, dsb.

```mermaid
graph TD
    AU((Admin))
    P21(("2.1\nSetup\nStruktur Sekolah"))
    P22(("2.2\nManajemen\nData Santri"))
    P23(("2.3\nKesiswaan &\nPrestasi"))

    DS3[("D3: Students")]
    DS4[("D4: Academic")]

    AU -- "Tahun/Semester/Kelas" --> P21
    P21 --> DS4
    AU -- "NIS/Nama/Wali" --> P22
    P22 --> DS3
    AU -- "Data Prestasi/Kajian" --> P23
    P23 --> DS3
```

### 3.2 Detil Proses 4.0 (Sistem Keuangan)
Mencakup fitur: Topup, Tagihan, Dompet, Penarikan, Uang Saku.

```mermaid
graph TD
    BD((Bendahara))
    SN((Santri))
    P41(("4.1\nPengelolaan\nTagihan (Bills)"))
    P42(("4.3\nTransaksi\nWallet (Topup/WD)"))
    P43(("4.3\nPenarikan\nUang Saku"))

    DS5[("D5: Finance")]
    DS3[("D3: Students")]

    BD -- "Kategori & Nominal" --> P41
    P41 -- "Generate" --> DS5
    BD -- "Topup/Withdraw" --> P42
    P42 -- "Update Saldo" --> DS3
    P42 -- "Log Transaction" --> DS5
    SN -- "Request Pocket Money" --> P43
    P43 -- "Validate & Debit" --> P42
```

### 3.3 Detil Proses 5.0 (Koperasi & POS)
Mencakup fitur: Produk, Stok Opname, POS, Riwayat Stok.

```mermaid
graph TD
    KS((Kasir/Admin))
    P51(("5.1\nManajemen\nInventori"))
    P52(("5.2\nPoint of Sale\n(POS)"))
    P53(("5.3\nStok Opname\n& Riwayat"))

    DS6[("D6: Products")]
    DS5[("D5: Wallet")]

    KS -- "Entry Produk" --> P51
    P51 --> DS6
    KS -- "Scan Barang/Kartu" --> P52
    P52 -- "Check Price/Stock" --> DS6
    P52 -- "Verify Wallet" --> DS5
    P52 -- "Success Checkout" --> P53
    KS -- "Input Fisik" --> P53
    P53 -- "Update/Log Stock" --> DS6
```

### 3.4 Detil Proses 6.0 (Presensi & Pelaporan)
Mencakup fitur: Presensi Harian/Kegiatan, Rapor, Kenaikan Kelas.

```mermaid
graph TD
    GR((Guru/Pengurus))
    P61(("6.1\nScanning\nPresensi"))
    P62(("6.2\nPenilaian\n& Rapor"))
    P63(("6.3\nKenaikan\nKelas"))

    DS7[("D7: Records")]
    DS3[("D3: Students")]

    GR -- "QR Kartu" --> P61
    P61 -- "Create Log" --> DS7
    GR -- "Input Nilai" --> P62
    P62 -- "Generate Report" --> DS7
    GR -- "Konfirmasi Naik" --> P63
    P63 -- "Migrasi Level" --> DS3
```

### 3.5 Detil Proses 1.0 (Super Admin - Global)
Mencakup fitur: Kelola Sekolah, Metode Pembayaran, Withdraw Sekolah, RBAC.

```mermaid
graph TD
    SA((Super Admin))
    P11(("1.1\nTenancy\nManagement"))
    P12(("1.2\nGlobal\nFinance Config"))
    P13(("1.3\nSecurity &\nRBAC"))

    DS1[("D1: Config")]
    DS2[("D2: Roles")]

    SA -- "Tambah Sekolah/Unit" --> P11
    P11 --> DS1
    SA -- "Settlement Withdraw" --> P12
    P12 --> DS1
    SA -- "Assign Permissions" --> P13
    P13 --> DS2
```
