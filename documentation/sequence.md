# Diagram Urutan (Sequence Diagram)

## 1. Fitur: Onboarding & Registrasi Santri (Admin)
```mermaid
sequenceDiagram
    actor A as Admin Unit
    participant S as Sistem (UI)
    participant DS as StudentService
    participant DB as Database
    
    A->>S: Upload Excel / Form Data Santri
    S->>DS: Validate & ProcessData()
    DS->>DB: Insert into Students Table
    DS->>DB: Assign default Wallet (Balance 0)
    DS->>DB: Generate QR Code Identity
    DB-->>S: Success
    S-->>A: Tampilkan Daftar Santri Baru
```

## 2. Fitur: Top-up Saldo Manual (Bendahara)
```mermaid
sequenceDiagram
    actor B as Bendahara
    participant S as Panel Keuangan
    participant WS as WalletService
    participant DB as Database
    
    B->>S: Masukkan NIS & Nominal Setoran
    S->>DB: Verifikasi Data Santri
    DB-->>S: Valid
    B->>S: Konfirmasi Simpan
    S->>WS: CreditWallet(amount)
    WS->>DB: Update Balance & Log Transaction
    DB-->>S: Saved
    S-->>B: Cetak Bukti Top-up
```

## 3. Fitur: Transaksi POS Koperasi (Kasir)
```mermaid
sequenceDiagram
    actor K as Kasir
    participant P as Sistem POS
    participant WS as WalletService
    participant DB as Database
    
    K->>P: Scan Barang & Scan Kartu Santri
    P->>DB: Cek Harga & Saldo Santri
    alt Saldo Cukup
        P->>WS: Request Payment
        WS->>DB: Potong Saldo & Update Stok
        WS->>DB: Simpan Snapshot Data Santri
        DB-->>P: Transaksi Berhasil
        P-->>K: Cetak Struk
    else Saldo Kurang
        P-->>K: Pesan Error: Saldo Tidak Cukup
    end
```

## 4. Fitur: Presensi QR Santri (Pengurus)
```mermaid
sequenceDiagram
    actor G as Pengurus
    participant S as Scanner App
    participant AS as AttendanceService
    participant DB as Database
    
    G->>S: Arahkan Kamera ke Kartu Santri
    S->>AS: RecordAttendance(NIS, EventID)
    AS->>DB: Cek Validitas NIS
    DB-->>AS: OK
    AS->>DB: Insert Attendance (Snapshot Name/Class)
    DB-->>S: Berhasil Absen
    S-->>G: Visual Feedback: "Hadir - [Nama Santri]"
```
