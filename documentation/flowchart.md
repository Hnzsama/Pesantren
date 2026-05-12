# Diagram Alir (Flowchart) Per Role - Struktur Menu Lengkap

## 1. Flowchart Admin (Lengkap)
Bagan alir navigasi dan operasional untuk role Admin Unit.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Admin]
    Login --> Dash[Dashboard Utama]
    
    %% Cabang Platform
    Dash --> Platform{Platform}
    Platform --> News[Berita & Pengumuman]
    
    %% Cabang Akademik
    Dash --> Acad{Akademik}
    Acad --> AY[Tahun Ajaran]
    Acad --> SEM[Semester]
    Acad --> EDU[Jenjang Pendidikan]
    Acad --> CLS[Data Kelas]
    Acad --> DA[Kegiatan Harian]
    Acad --> AGE[Agenda Kegiatan]
    
    %% Cabang Kesiswaan
    Dash --> Stud{Kesiswaan}
    Stud --> PR[Prestasi]
    Stud --> KJ[Kajian]
    Stud --> SN[Santri]
    Stud --> WS[Wali Santri]
    Stud --> IZ[Perizinan Santri]
    Stud --> SW[Jadwal Sowan]
    Stud --> FB[Feedback]
    Stud --> CRD[Kartu Santri]
    
    %% Cabang Presensi
    Dash --> Pres{Presensi & Laporan}
    Pres --> PH[Presensi Harian]
    Pres --> PK[Presensi Kegiatan]
    Pres --> RP[Rapor Santri]
    Pres --> KK[Kenaikan Kelas]
    
    %% Cabang Administrasi
    Dash --> Adm{Administrasi}
    Adm --> USR[Kelola Pengguna]
    
    %% Cabang Keuangan
    Dash --> Fin{Keuangan}
    Fin --> FD[Dashboard Keuangan]
    Fin --> TM[Top Up Manual]
    Fin --> BM[Bayar Tagihan Manual]
    Fin --> TD[Transaksi Dompet]
    Fin --> TG[Tagihan]
    Fin --> KT[Kategori Tagihan]
    Fin --> TK[Tagihan Kegiatan]
    Fin --> PD[Penarikan Dana]
    Fin --> US[Penarikan Uang Saku]

    %% Terminal
    News & AGE & CRD & KK & USR & US --> End([Selesai])
```

---

## 2. Flowchart Admin Koperasi
Bagan alir navigasi untuk role Manajemen Koperasi.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Admin Koperasi]
    Login --> Dash[Dashboard Koperasi]
    Dash --> Hist[Riwayat Pembelian]
    Dash --> Prod[Produk & Jasa]
    Dash --> Opn[Stok Opname]
    Dash --> RH[Riwayat Stok]
    Dash --> USR[Kelola Kasir]
    Hist & Prod & Opn & RH & USR --> End([Selesai])
```

---

## 3. Flowchart Bendahara
Bagan alir navigasi untuk role Bendahara Unit.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Bendahara]
    Login --> Dash[Dashboard Keuangan]
    Dash --> TM[Top Up Manual]
    Dash --> BM[Bayar Tagihan Manual]
    Dash --> TD[Transaksi Dompet]
    Dash --> TG[Tagihan]
    Dash --> KT[Kategori Tagihan]
    Dash --> TK[Tagihan Kegiatan]
    Dash --> PD[Penarikan Dana]
    Dash --> US[Penarikan Uang Saku]
    TM & BM & TD & TG & KT & TK & PD & US --> End([Selesai])
```

---

## 4. Flowchart Kasir
Bagan alir navigasi untuk role Kasir Koperasi.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Kasir]
    Login --> Dash[Dashboard Kasir]
    Dash --> POS[Point of Sale - POS]
    Dash --> Hist[Riwayat Pembelian]
    POS & Hist --> End([Selesai])
```

---

## 5. Flowchart Guru
Bagan alir navigasi untuk role Guru.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Guru]
    Login --> Dash[Dashboard Guru]
    Dash --> News[Berita & Pengumuman]
    Dash --> Pres{Presensi & Laporan}
    Pres --> PH[Presensi Harian]
    Pres --> PK[Presensi Kegiatan]
    Pres --> RP[Rapor Santri]
    Pres --> KK[Kenaikan Kelas]
    News & KK --> End([Selesai])
```

---

## 6. Flowchart Pengurus
Bagan alir navigasi untuk role Pengurus.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Pengurus]
    Login --> Dash[Dashboard Pengurus]
    Dash --> Pres{Presensi & Laporan}
    Pres --> PH[Presensi Harian]
    Pres --> PK[Presensi Kegiatan]
    PH & PK --> End([Selesai])
```

---

## 7. Flowchart Super Admin
Bagan alir navigasi untuk role Super Admin Pusat.

```mermaid
flowchart TD
    S([Mulai]) --> Login[Login Super Admin]
    Login --> Dash[Dashboard Global]
    Dash --> News[Berita & Pengumuman]
    
    Dash --> Adm{Administrasi}
    Adm --> PC[Permintaan Cetak]
    Adm --> DK[Desain Kartu]
    Adm --> SCH[Sekolah/Unit]
    Adm --> USR[Kelola Pengguna]
    Adm --> RBAC[Hak Akses]
    
    Dash --> Fin{Keuangan}
    Fin --> FD[Dashboard Keuangan]
    Fin --> MP[Metode Pembayaran]
    Fin --> WD[Kelola Penarikan]
    
    News & RBAC & WD --> End([Selesai])
```
