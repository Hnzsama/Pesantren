# Hasil Pengujian (Pest PHP)

Sistem telah diuji menggunakan framework **Pest PHP** dengan total **72 asersi** yang mencakup seluruh fungsionalitas kritis. Seluruh skenario pengujian dinyatakan **Lulus (PASS)**.

## 1. Skenario Pengujian Utama
| ID | Requirement | Test Case | Status |
|---|---|---|---|
| FR-01 | Autentikasi | Login per role & Redirection Dashboard | ✅ PASS |
| FR-02 | Akademik | Kelola Tahun Ajaran, Semester, & Kelas | ✅ PASS |
| FR-03 | Kesiswaan | Import Santri & Manajemen Kartu | ✅ PASS |
| FR-04 | Keuangan | Top-up Manual & Pembayaran Tagihan | ✅ PASS |
| FR-05 | Koperasi | Transaksi POS & Stok Opname | ✅ PASS |
| FR-06 | Presensi | Scan Kartu & Laporan Kehadiran | ✅ PASS |

## 2. Kondisi Pengujian (Test Conditions)
| Condition ID | Test Scenario | Test Condition | Status |
|---|---|---|---|
| TCOND-01 | RBAC | Role Guru dilarang akses menu Keuangan | ✅ PASS |
| TCOND-02 | RBAC | Role Kasir dilarang akses menu Akademik | ✅ PASS |
| TCOND-03 | Wallet | Pembayaran gagal jika saldo tidak mencukupi | ✅ PASS |
| TCOND-04 | Audit | Transaksi mencatat snapshot nama santri saat itu | ✅ PASS |
| TCOND-05 | POS | Stok produk berkurang otomatis setelah transaksi | ✅ PASS |
| TCOND-06 | Akademik | Guru dapat melakukan input rapor & kenaikan kelas | ✅ PASS |
