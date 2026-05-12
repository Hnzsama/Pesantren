# Diagram Aktivitas & Flowchart

## 1. Activity Diagram: Kenaikan Kelas (Guru)
```mermaid
activityDiagram
    start
    :Guru buka menu Kenaikan Kelas;
    :Pilih Kelas Asal;
    :Tampilkan Daftar Santri;
    :Centang Santri yang Naik Kelas;
    :Pilih Kelas Tujuan;
    if (Konfirmasi?) then (Ya)
        :Sistem Update class_id Santri;
        :Sistem Simpan Log Histori;
        :Tampilkan Pesan Sukses;
    else (Tidak)
        :Batal;
    endif
    stop
```

---

## 2. Flowchart: Bendahara (Siklus Keuangan)
```mermaid
flowchart TD
    Start([Mulai]) --> Topup[Terima Uang Tunai]
    Topup --> Input[Input Topup Manual]
    Input --> Bill[Generate Tagihan SPP]
    Bill --> AutoPay{Proses Auto-Debet}
    AutoPay -- Berhasil --> Log[Cetak Laporan Harian]
    AutoPay -- Saldo Kurang --> Arrears[Catat Tunggakan]
    Log --> End([Selesai])
```

---

## 3. Flowchart: Kasir Koperasi (POS)
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
