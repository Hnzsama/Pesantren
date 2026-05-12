# Dokumentasi dan Analisis Sistem Informasi Akademik Pesantren (SIAP)

## 1. Analisis Sistem Secara Keseluruhan
Sistem Informasi Akademik Pesantren (SIAP) adalah platform manajemen pesantren terpadu yang mencakup pengelolaan data santri, akademik, keuangan (cashless), dan operasional koperasi. Sistem ini menggunakan arsitektur **Multi-Tenancy** berbasis sekolah/lembaga di bawah satu naungan pusat.

---

## 2. Data Flow Diagram (DFD)

## 2. Data Flow Diagram (DFD)

### DFD Level 0 (Context Diagram)
Menggambarkan batasan sistem dan interaksi data antara sistem SIAP dengan entitas eksternal secara menyeluruh.

```mermaid
graph TD
    %% Entitas
    SA((Super Admin))
    AU((Admin Unit))
    BD((Bendahara))
    GR((Guru/Pengurus))
    KS((Kasir Koperasi))
    WS((Wali Santri))
    SN((Santri/Kartu))

    %% Sistem
    SIAP{{"Sistem Informasi\nAkademik Pesantren\n(SIAP)"}}

    %% Aliran Data Super Admin
    SA -- "Setup Unit, Verifikasi Penarikan,\nManajemen Hak Akses" --> SIAP
    SIAP -- "Laporan Global, Log Aktivitas,\nStatus Request Kartu" --> SA

    %% Aliran Data Admin Unit
    AU -- "Data Master Santri, Tahun Ajaran,\nStruktur Kelas, News/Berita" --> SIAP
    SIAP -- "Laporan Akademik, Statistik Unit,\nPreview Kartu Santri" --> AU

    %% Aliran Data Bendahara
    BD -- "Konfigurasi Tagihan (SPP),\nTop-up Manual, Validasi Withdrawal" --> SIAP
    SIAP -- "Laporan Kas, Riwayat Transaksi,\nRekapitulasi Tunggakan" --> BD

    %% Aliran Data Guru/Pengurus
    GR -- "Data Presensi, Input Rapor,\nCatatan Prestasi/Izin" --> SIAP
    SIAP -- "Daftar Santri, Agenda Kegiatan,\nStatus Kehadiran" --> GR

    %% Aliran Data Kasir
    KS -- "Data Produk, Transaksi POS,\nStok Opname" --> SIAP
    SIAP -- "Validasi Saldo, Struk Belanja,\nLaporan Penjualan" --> KS

    %% Aliran Data Wali Santri
    WS -- "Setoran Dana (Wali),\nMonitoring Via App/API" --> SIAP
    SIAP -- "Notifikasi Tagihan, Laporan Rapor,\nRiwayat Transaksi Anak" --> WS

    %% Aliran Data Santri
    SN -- "ID Barcode/QR, Input PIN" --> SIAP
    SIAP -- "Sisa Saldo, Status Kehadiran" --> SN
```

### DFD Level 1 (Diagram Proses Utama)
Memecah sistem menjadi 6 proses utama yang saling terintegrasi melalui berbagai penyimpanan data (*Data Stores*).

```mermaid
graph TD
    %% Entitas
    AU((Admin Unit))
    BD((Bendahara))
    KS((Kasir))
    GR((Guru))
    SN((Santri))

    %% Proses
    P1(("1.0\nManajemen\nIdentitas &\nMaster Data"))
    P2(("2.0\nManajemen\nAkademik &\nKesiswaan"))
    P3(("3.0\nSistem\nKeuangan &\nCashless"))
    P4(("4.0\nPOS &\nInventory\nKoperasi"))
    P5(("5.0\nPresensi &\nKedisiplinan"))
    P6(("6.0\nPelaporan &\nStatistik"))

    %% Data Stores
    DS1[("D1: Users & Schools")]
    DS2[("D2: Students & Guardians")]
    DS3[("D3: Academic (Years, Classes)")]
    DS4[("D4: Wallet & Transactions")]
    DS5[("D5: Bills & Payments")]
    DS6[("D6: Attendance Records")]
    DS7[("D7: Products & Stock")]

    %% Aliran Proses 1.0
    AU -- "Data Sekolah/User" --> P1
    P1 <--> DS1
    AU -- "Data Santri/Wali" --> P1
    P1 <--> DS2

    %% Aliran Proses 2.0
    AU -- "Struktur Kelas/Tahun" --> P2
    P2 <--> DS3
    GR -- "Input Nilai/Prestasi" --> P2
    P2 -- "Update Data" --> DS2

    %% Aliran Proses 3.0
    BD -- "Setup Tagihan/Topup" --> P3
    P3 -- "Cek Data Santri" --> DS2
    P3 <--> DS4
    P3 <--> DS5

    %% Aliran Proses 4.0
    KS -- "Update Produk" --> P4
    P4 <--> DS7
    SN -- "Scan Bayar" --> P4
    P4 -- "Debit Saldo" --> P3
    P4 -- "Create Sales" --> DS4

    %% Aliran Proses 5.0
    SN -- "Scan Kartu" --> P5
    P5 -- "Snapshot Data" --> DS2
    P5 -- "Simpan Log" --> DS6

    %% Aliran Proses 6.0
    P6 -- "Ambil Data" --> DS4
    P6 -- "Ambil Data" --> DS5
    P6 -- "Ambil Data" --> DS6
    P6 -- "Kirim Laporan" --> AU
    P6 -- "Kirim Laporan" --> BD
```

### DFD Level 2 - Detil Proses 1.0 (Identitas & Master Data)
Menjelaskan aliran data saat onboarding santri dan setup unit sekolah.

```mermaid
graph TD
    %% Entitas & Data Store
    AU((Admin Unit))
    DS1[("D1: Users")]
    DS2[("D2: Students")]
    
    %% Sub-Proses
    P11(("1.1\nRegistrasi\nAkun User"))
    P12(("1.2\nImport/Input\nData Santri"))
    P13(("1.3\nMapping\nWali Santri"))

    %% Aliran
    AU -- "Data Staff/Guru" --> P11
    P11 -- "Insert" --> DS1
    AU -- "File Excel/Form" --> P12
    P12 -- "Bulk Insert" --> DS2
    AU -- "Relasi Orang Tua" --> P13
    P13 -- "Update Foreign Key" --> DS2
```

### DFD Level 2 - Detil Proses 2.0 (Manajemen Akademik)
Menjelaskan pengelolaan tahun ajaran, kelas, dan kenaikan kelas.

```mermaid
graph TD
    %% Entitas & Data Store
    AU((Admin Unit))
    GR((Guru))
    DS3[("D3: Academic")]
    DS2[("D2: Students")]

    %% Sub-Proses
    P21(("2.1\nSetup\nKurikulum"))
    P22(("2.2\nPlotting\nKelas"))
    P23(("2.3\nProses\nKenaikan Kelas"))

    %% Aliran
    AU -- "Tahun/Semester" --> P21
    P21 -- "Update Master" --> DS3
    AU -- "Daftar Siswa" --> P22
    P22 -- "Update ClassID" --> DS2
    GR -- "Input Kelulusan" --> P23
    P23 -- "Batch Update Class" --> DS2
```

### DFD Level 2 - Detil Proses 3.0 (Keuangan & Cashless)
Menjelaskan aliran data mendalam pada sirkulasi uang elektronik santri.

```mermaid
graph TD
    %% Entitas & Data Store
    BD((Bendahara))
    SN((Santri))
    DS4[("D4: Wallet Trans")]
    DS5[("D5: Bills")]
    DS2[("D2: Students")]

    %% Sub-Proses
    P31(("3.1\nTop-up\nManual"))
    P32(("3.2\nOtomasi\nPotong Tagihan"))
    P33(("3.3\nValidasi\nSaldo/PIN"))
    P34(("3.4\nPenarikan\nDana (WD)"))

    %% Aliran 3.1
    BD -- "Nominal & NIS" --> P31
    P31 -- "Cek Eksistensi" --> DS2
    P31 -- "Update Saldo" --> DS2
    P31 -- "Log Credit" --> DS4

    %% Aliran 3.2
    P32 -- "Baca Tagihan" --> DS5
    P32 -- "Trigger Potong" --> P33
    P33 -- "Cek Saldo" --> DS2
    P33 -- "Konfirmasi OK" --> P32
    P32 -- "Update Status" --> DS5
    P32 -- "Log Debit" --> DS4

    %% Aliran 3.4
    BD -- "Request WD" --> P34
    P34 -- "Kurangi Saldo" --> DS2
    P34 -- "Log WD" --> DS4
```

### DFD Level 2 - Detil Proses 4.0 (POS & Inventory)
Menjelaskan mekanisme transaksi ritel di Koperasi.

```mermaid
graph TD
    %% Entitas & Data Store
    KS((Kasir))
    SN((Santri))
    DS7[("D7: Products")]
    DS4[("D4: Wallet/Sales")]

    %% Sub-Proses
    P41(("4.1\nScan &\nValidasi Barang"))
    P42(("4.2\nOtorisasi\nKartu Santri"))
    P43(("4.3\nSettlement\nTransaksi"))

    %% Aliran
    KS -- "Input Barcode" --> P41
    P41 -- "Cek Harga/Stok" --> DS7
    SN -- "Tempel Kartu" --> P42
    P42 -- "Validasi Saldo/PIN" --> P3(("3.3\nSistem Wallet"))
    P3 -- "Approval" --> P43
    P43 -- "Kurangi Stok" --> DS7
    P43 -- "Record Penjualan" --> DS4
    P43 -- "Cetak Struk" --> KS
```

### DFD Level 2 - Detil Proses 5.0 (Presensi & Kedisiplinan)
Menjelaskan pencatatan kehadiran menggunakan scan kartu.

```mermaid
graph TD
    %% Entitas & Data Store
    GR((Pengurus))
    SN((Santri))
    DS6[("D6: Attendance")]
    DS2[("D2: Students")]

    %% Sub-Proses
    P51(("5.1\nIdentifikasi\nKartu"))
    P52(("5.2\nVerifikasi\nStatus Santri"))
    P53(("5.3\nLogging\nKehadiran"))

    %% Aliran
    SN -- "QR/Barcode" --> P51
    P51 -- "Query NIS" --> DS2
    P51 -- "Data Santri" --> P52
    P52 -- "Cek Aktif/Non" --> P53
    P53 -- "Insert Record" --> DS6
    P53 -- "Feedback Visual" --> GR
```



---

## 3. Sequence Diagram

### 3.1 Transaksi Pembayaran di POS Koperasi
```mermaid
sequenceDiagram
    actor Kasir
    participant POS as UI POS
    participant WS as WalletService
    participant DB as Database
    
    Kasir->>POS: Pilih Produk & Scan Kartu Santri
    POS->>WS: Request Pembayaran (NIS, Total)
    WS->>DB: Cek Saldo & Pin Santri
    DB-->>WS: Saldo Mencukupi
    WS->>DB: Insert WalletTransaction (DEBIT)
    WS->>DB: Update Stock Product
    WS->>DB: Insert SalesItems
    DB-->>WS: Success
    WS-->>POS: Tampilkan Struk
    POS-->>Kasir: Transaksi Berhasil
```

### 3.2 Presensi Santri (Scan Mode)
```mermaid
sequenceDiagram
    actor Santri
    participant App as Scanner
    participant AS as AttendanceService
    participant DB as Database
    
    Santri->>App: Tempel Kartu (QR/Barcode)
    App->>AS: Kirim NIS & EventID
    AS->>DB: Cari Student (NIS)
    DB-->>AS: Data Student Found
    AS->>AS: Capture Snapshot (Name, NIS, Class)
    AS->>DB: Insert Attendance Record
    DB-->>AS: Saved
    AS-->>App: Tampilkan "Hadir: [Nama Santri]"
```

---

## 4. Activity Diagram

### Proses Pembayaran Tagihan via Saldo Dompet
```mermaid
flowchart TD
    A(Mulai) --> B[Admin Pilih Santri]
    B --> C[Pilih Daftar Tagihan Aktif]
    C --> D{Cek Saldo Dompet?}
    D -- "Kurang" --> E[Tampilkan Gagal: Saldo Tidak Cukup]
    D -- "Cukup" --> F[Input PIN/Konfirmasi]
    F --> G[Proses Transaksi Debit]
    G --> H[Simpan Snapshot Data Santri]
    H --> I[Update Status Tagihan: LUNAS]
    I --> J[Cetak Bukti Pembayaran]
    J --> K(Selesai)
    E --> K
```

---

## 5. Flowchart Per Role

### A. Admin / Bendahara (Keuangan)
```mermaid
flowchart TD
    Start([Mulai]) --> Login[Login Admin]
    Login --> Menu{Pilih Menu}
    Menu -->|Santri| ManageS[Kelola Data & Naik Kelas]
    Menu -->|Tagihan| GenerateB[Generate Tagihan SPP Bulanan]
    Menu -->|Topup| ManualT[Topup Saldo Tunai Santri]
    Menu -->|Laporan| FinRep[Cetak Laporan Kas & Bank]
    ManageS --> End([Selesai])
    GenerateB --> End
    ManualT --> End
    FinRep --> End
```

### B. Kasir Koperasi (POS)
```mermaid
flowchart TD
    S([Mulai]) --> L[Login Kasir]
    L --> P[Buka POS]
    P --> C{Scan Barang}
    C --> Pay{Pilih Metode Bayar}
    Pay -->|Tunai| T[Input Uang Tunai]
    Pay -->|Kartu| K[Scan Kartu Santri]
    K --> Val{Cek Saldo}
    Val -- Gagal --> K
    Val -- Sukses --> Save[Simpan Transaksi & Potong Stok]
    Save --> Print[Cetak Struk]
    Print --> End([Selesai])
```

---

## 6. DBML (Database Markup Language)

```dbml
// --- SISTEM PESANTREN DBML ---

Table schools {
  id ulid [pk]
  name varchar
  address text
  logo varchar
  created_at timestamp
}

Table students {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  nis varchar [unique]
  full_name varchar
  level_id ulid [ref: > levels.id]
  parent_user_id ulid [ref: > users.id]
  wallet_balance decimal
  wallet_pin varchar
  status enum
  created_at timestamp
}

Table levels {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar // Kelas (Misal: 1A, 2B)
  department_id ulid [ref: > departments.id]
}

Table wallet_transactions {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  student_id ulid [ref: > students.id]
  type enum // CREDIT, DEBIT
  category enum // TOPUP, PURCHASE, WITHDRAWAL, BILL_PAYMENT
  amount decimal
  student_name varchar // Snapshot
  student_nis varchar // Snapshot
  student_class varchar // Snapshot
  status enum
  created_at timestamp
}

Table bills {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  student_id ulid [ref: > students.id]
  bill_category_id ulid
  amount decimal
  status enum // UNPAID, PAID, PARTIAL
  student_name varchar // Snapshot
  created_at timestamp
}

Table attendances {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  student_id ulid [ref: > students.id]
  status enum // HADIR, IZIN, SAKIT, ALFA
  student_name varchar // Snapshot
  student_nis varchar // Snapshot
  created_at timestamp
}

Table products {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  sku varchar
  purchase_price decimal
  selling_price decimal
  stock integer
  created_at timestamp
}
```

---

## 7. Analisis Fitur Per Role

Sistem ini memiliki pembagian fitur yang sangat spesifik untuk mendukung operasional harian berbagai departemen di pesantren:

### A. Fitur Admin (Unit Sekolah)
- **Platform**: Dashboard Statistik Unit.
- **Informasi**: Berita & Pengumuman Pesantren.
- **Akademik**: Kelola Tahun Ajaran, Semester, Jenjang Pendidikan, Data Kelas, Kegiatan Harian, dan Agenda Kegiatan.
- **Kesiswaan**: Data Prestasi, Kajian Video, Master Santri, Wali Santri, Perizinan Santri, Jadwal Sowan, dan Feedback.
- **Kartu Santri**: Manajemen pencetakan dan monitoring kartu.
- **Presensi & Laporan**: Presensi Harian, Presensi Kegiatan, Rapor Santri, dan Kenaikan Kelas.
- **Administrasi**: Kelola Pengguna (dalam unit).
- **Keuangan**: Dashboard Keuangan, Top Up Manual, Bayar Tagihan Manual, Transaksi Dompet, Tagihan (SPP), Kategori Tagihan, Tagihan Kegiatan, Penarikan Dana (Withdrawal), dan Penarikan Uang Saku.

### B. Fitur Admin Koperasi
- **Platform**: Dashboard Koperasi.
- **Operasional**: Riwayat Pembelian, Produk & Jasa, Stok Opname, Riwayat Stok, dan Kelola Kasir.

### C. Fitur Bendahara
- **Keuangan**: Dashboard Keuangan, Top Up Manual, Bayar Tagihan Manual, Transaksi Dompet, Tagihan, Kategori Tagihan, Tagihan Kegiatan, Penarikan Dana, dan Penarikan Uang Saku.

### D. Fitur Kasir Koperasi
- **POS**: Point of Sale (POS) untuk transaksi santri dan Riwayat Pembelian.

### E. Fitur Guru
- **Platform**: Dashboard Guru.
- **Informasi**: Berita & Pengumuman.
- **Presensi & Laporan**: Presensi Harian, Presensi Kegiatan, Rapor Santri, dan Kenaikan Kelas.

### F. Fitur Pengurus (Asrama/Keamanan)
- **Platform**: Dashboard Pengurus.
- **Presensi**: Presensi Harian (Subuh/Maghrib) dan Presensi Kegiatan.

### G. Fitur Super Admin (Pusat/Yayasan)
- **Platform**: Dashboard Global & Berita.
- **Administrasi**: Permintaan Cetak Kartu, Desain Kartu, Kelola Sekolah/Unit, Kelola Pengguna Global, dan Hak Akses (Roles/Permissions).
- **Keuangan**: Dashboard Keuangan Global, Metode Pembayaran, dan Kelola Penarikan Dana Sekolah.

---

## 8. Pemetaan Rute dan Hak Akses (Route Analysis)

Setiap *route* URL pada aplikasi dilindungi menggunakan Spatie Permission untuk memastikan isolasi data dan wewenang.

| Modul | Route Prefix | Role Utama |
|---|---|---|
| **Pusat (Global)** | `/super-admin/*` | `super_admin` |
| **Administrasi Unit** | `/admin/*` | `admin` |
| **Keuangan** | `/keuangan/*` (atau `/admin/keuangan`) | `admin, bendahara` |
| **Koperasi (Manajemen)** | `/koperasi/*` | `admin_koperasi` |
| **Koperasi (Kasir)** | `/koperasi/pos` | `kasir` |
| **Akademik (Guru)** | `/guru/*` | `guru` |
| **Presensi Harian** | `/presensi-kegiatan` | `guru, pengurus` |
| **Monitoring (Wali)** | `/api/*` | `wali_santri` |

---

## 9. Analisis End-to-End Workflow (Cara Kerja Sistem Secara Keseluruhan)

Sistem Informasi Akademik Pesantren ini didesain agar data mengalir saling terkait, menciptakan *loop* tertutup (closed-loop) antara administrasi, operasional asrama, dan keuangan. 

### Tahap 1: Onboarding & Setup
1. **Super Admin** membuat data **School** (Pesantren/Unit).
2. **Admin Sekolah** menginisiasi tahun ajaran, semester, dan struktur level/jenjang kelas.
3. **Admin Sekolah** mendaftarkan atau mengimpor data **Student** dan menghubungkannya dengan akun **Wali Santri**.
4. Kartu Santri (Barcode/QR) digenerate secara otomatis melalui menu *Student Cards*.

### Tahap 2: Siklus Keuangan & Operasional Koperasi
1. Wali Santri menyetorkan sejumlah uang ke pesantren (via Transfer Bank/Gateway atau Tunai ke Admin). Admin mencatat ini sebagai **Top-up Saldo** pada akun santri tersebut. Saldo bertambah.
2. Kasir Koperasi melayani santri yang ingin berbelanja kebutuhan atau jajan.
3. Kasir menggunakan **POS Koperasi** untuk memindai barang, lalu memindai Kartu Santri.
4. Sistem memverifikasi PIN dan Saldo Santri. Jika sukses, Saldo santri dikurangi, Stok barang dikurangi, dan riwayat transaksi tercatat.

### Tahap 3: Siklus Akademik & Pendampingan
1. Setiap hari, **Pengurus** atau **Guru** mendata **Absensi Kegiatan** (contoh: Jamaah Subuh, Ngaji Kitab). Santri memindai kartunya ke *scanner*, lalu data *Hadir/Alfa* tersimpan.
2. Di akhir semester, **Guru** menyusun Rapor Elektronik lalu mengunggahnya ke dalam sistem (Menu *Laporan Santri*).
3. Admin atau Guru konseling juga mencatat rekam jejak **Prestasi** dan **Perizinan** (sakit/pulang).

### Tahap 4: Pelaporan & Settlement
1. Uang yang mengendap dalam dompet digital santri sejatinya dipegang oleh Pesantren/Sekolah.
2. Jika Wali Santri meminta penarikan sisa saldo (atau santri lulus), Admin melakukan proses **Withdrawal (Penarikan Dana)**, mengembalikan uang tunai, dan saldo dompet dinolkan.
3. Bendahara menggunakan dashboard *Financial* untuk merekonsiliasi total kas masuk, keluar, serta membagi porsi biaya admin sistem sesuai persentase yang disepakati dengan pengembang aplikasi.
4. Fitur **Data Snapshot** (Denormalisasi Nama dan NIS pada tabel Transaksi/Absensi) memastikan bahwa meskipun akun santri kelak dihapus, pelaporan keuangan sekolah akan tetap valid tanpa mengalami insiden "Data Tidak Ditemukan".

---

## 10. Arsitektur Aliran Data (DFD)

### DFD Level 0 (Context Diagram)
Menggambarkan interaksi global antara sistem SIAP dengan entitas luar.

```mermaid
graph TD
    S[Santri] -- Info Identitas/Barcode --> SIAP[SIAP System]
    SIAP -- Status Saldo/Struk --> S
    W[Wali Santri] -- Dana Topup --> SIAP
    SIAP -- Laporan Akademik/Keuangan --> W
    A[Admin Sekolah] -- Konfigurasi/Master Data --> SIAP
    SIAP -- Laporan Operasional --> A
    K[Kasir Koperasi] -- Data Penjualan --> SIAP
    SIAP -- Info Stok/Validasi Saldo --> K
    G[Guru/Pengurus] -- Data Presensi/Nilai --> SIAP
    SA[Super Admin] -- Global Setup/Unit --> SIAP
```

### DFD Level 1 (Proses Utama)
Pembagian sistem menjadi proses-proses inti.

```mermaid
graph TD
    U[User] --> P1[1.0 Autentikasi & Otorisasi]
    P1 --> DB[(Database)]
    U --> P2[2.0 Manajemen Akademik & Santri]
    P2 --> DB
    U --> P3[3.0 Pengelolaan Keuangan & Wallet]
    P3 --> DB
    U --> P4[4.0 Point of Sale & Inventory]
    P4 --> DB
    U --> P5[5.0 Presensi & Kedisiplinan]
    P5 --> DB
```

### DFD Level 2 (Modul Keuangan & POS)
Pendalaman aliran data pada transaksi non-tunai.

```mermaid
graph TD
    Santri -- Scan Kartu --> P41[4.1 Validasi Saldo]
    P41 -- Query Balance --> DB[(Wallet)]
    P41 -- Saldo OK --> P42[4.2 Proses Belanja]
    P42 -- Update Stok --> DB[(Products)]
    P42 -- Create Transaksi --> DB[(Transactions)]
    Admin -- Input Dana --> P31[3.1 Topup Manual]
    P31 -- Update Balance --> DB
```

---

## 11. Diagram Urutan (Sequence Diagram) Per Modul

### Modul Keuangan: Pembayaran Tagihan (Bills)
```mermaid
sequenceDiagram
    participant W as Wali/Admin
    participant S as Sistem Keuangan
    participant D as Database
    
    W->>S: Pilih Tagihan Santri
    S->>D: Ambil Data Tagihan
    D-->>S: Daftar Tagihan Aktif
    W->>S: Bayar via Saldo Wallet
    S->>D: Cek Saldo Santri
    alt Saldo Cukup
        S->>D: Kurangi Saldo & Update Status Lunas
        S->>D: Create WalletTransaction (Snapshotted)
        D-->>S: Success
        S-->>W: Cetak Bukti Bayar
    else Saldo Kurang
        S-->>W: Tampilkan Error (Saldo Tidak Cukup)
    end
```

### Modul Koperasi: Point of Sale (POS)
```mermaid
sequenceDiagram
    participant K as Kasir
    participant S as Sistem POS
    participant D as Database
    
    K->>S: Scan Barcode Kartu
    S->>D: Verifikasi Santri & PIN
    D-->>S: Valid
    K->>S: Input Produk & Jumlah
    S->>D: Cek Stok Produk
    D-->>S: Stok OK
    K->>S: Checkout
    S->>D: Kurangi Stok & Potong Saldo
    S->>D: Simpan Snapshot Student Data
    D-->>S: Transaksi Berhasil
    S-->>K: Cetak Struk
```

---

## 12. Diagram Aktivitas (Activity Diagram)

### Fitur: Presensi Harian (QR Scan)
```mermaid
activityDiagram
    start
    :Guru buka menu Scan Presensi;
    :Aktifkan Scanner/Kamera;
    while (Ada Santri?) is (Ya)
        :Scan Kartu Santri;
        :Sistem Cari Data Santri;
        if (Data Ketemu?) then (Ya)
            :Simpan Record Presensi;
            :Tampilkan Nama & Foto;
            :Suara "Berhasil";
        else (Tidak)
            :Tampilkan Pesan Error;
        endif
    endwhile (Selesai)
    :Rekapitulasi Kehadiran;
    stop
```

---

## 13. Diagram Alir (Flowchart)

### Overview Flowchart Sistem SIAP
```mermaid
flowchart TD
    Start([Mulai]) --> Master[Input Data Master: Sekolah, Santri, Kelas]
    Master --> Keuangan[Setup Tagihan & Topup Saldo]
    Keuangan --> Ops[Operasional: POS Koperasi, Presensi, Perizinan]
    Ops --> Report[Laporan: Rapor, Keuangan, Statistik]
    Report --> End([Selesai])
```

### Flowchart Per Role: Admin Koperasi & Kasir
```mermaid
flowchart TD
    Start([Mulai]) --> Login[Login Kasir/Koperasi]
    Login --> POS{Menu Utama}
    POS -- Transaksi --> Scan[Scan Kartu Santri]
    Scan --> Cart[Input Barang Belanja]
    Cart --> Pay[Proses Potong Saldo]
    Pay --> Struk[Cetak Struk]
    POS -- Manajemen --> Stok[Update Stok Opname]
    Stok --> Hist[Cek Riwayat Barang]
    Hist --> End([Selesai])
```

---

## 14. Skema Database Lengkap (DBML)

```dbml
// ARSITEKTUR CORE
Table schools {
  id ulid [pk]
  name varchar
  slug varchar
  address text
  created_at timestamp
}

Table users {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  email varchar
  password varchar
  created_at timestamp
}

// MANAJEMEN SANTRI
Table students {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  nis varchar [unique]
  wallet_balance decimal [default: 0]
  class_id ulid [ref: > levels.id]
}

// KEUANGAN (WALLET & BILLS)
Table bills {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  student_name_snapshot varchar // Denormalisasi
  amount decimal
  status enum // UNPAID, PAID, PARTIAL
}

Table wallet_transactions {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  student_name_snapshot varchar
  amount decimal
  type enum // DEBIT, CREDIT
  reference_id ulid
}

// AKADEMIK & PRESENSI
Table attendances {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  status enum // HADIR, IZIN, SAKIT, ALPA
  recorded_at timestamp
}

// KOPERASI & POS
Table products {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  price decimal
  stock int
}

Table pos_transactions {
  id ulid [pk]
  cashier_id ulid [ref: > users.id]
  student_id ulid [ref: > students.id]
  total_amount decimal
  created_at timestamp
}
```

---

## 9. Hasil Pengujian (Pest PHP)

Sistem telah diuji menggunakan framework **Pest PHP** dengan total **72 asersi** yang mencakup seluruh fungsionalitas kritis. Seluruh skenario pengujian dinyatakan **Lulus (PASS)**.

### 9.1. Skenario Pengujian Utama
| ID | Requirement | Test Case | Status |
|---|---|---|---|
| FR-01 | Autentikasi | Login per role & Redirection Dashboard | ✅ PASS |
| FR-02 | Akademik | Kelola Tahun Ajaran, Semester, & Kelas | ✅ PASS |
| FR-03 | Kesiswaan | Import Santri & Manajemen Kartu | ✅ PASS |
| FR-04 | Keuangan | Top-up Manual & Pembayaran Tagihan | ✅ PASS |
| FR-05 | Koperasi | Transaksi POS & Stok Opname | ✅ PASS |
| FR-06 | Presensi | Scan Kartu & Laporan Kehadiran | ✅ PASS |

### 9.2. Kondisi Pengujian (Test Conditions)
| Condition ID | Test Scenario | Test Condition | Status |
|---|---|---|---|
| TCOND-01 | RBAC | Role Guru dilarang akses menu Keuangan | ✅ PASS |
| TCOND-02 | RBAC | Role Kasir dilarang akses menu Akademik | ✅ PASS |
| TCOND-03 | Wallet | Pembayaran gagal jika saldo tidak mencukupi | ✅ PASS |
| TCOND-04 | Audit | Transaksi mencatat snapshot nama santri saat itu | ✅ PASS |
| TCOND-05 | POS | Stok produk berkurang otomatis setelah transaksi | ✅ PASS |
| TCOND-06 | Akademik | Guru dapat melakukan input rapor & kenaikan kelas | ✅ PASS |

