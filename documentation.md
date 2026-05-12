# Dokumentasi Sistem Informasi Akademik Pesantren (SIAP)

Selamat datang di dokumentasi teknis Sistem SIAP. Dokumentasi ini telah dipecah menjadi beberapa bagian untuk memudahkan navigasi dan pemeliharaan.

## Daftar Isi
1. [Analisis Fitur & Hak Akses (RBAC)](documentation/features_rbac.md)
2. [Arsitektur Aliran Data (DFD)](documentation/dfd.md)
3. [Diagram Urutan (Sequence Diagram)](documentation/sequence.md)
4. [Diagram Aktivitas & Flowchart](documentation/activity_flowchart.md)
5. [Skema Database (DBML)](documentation/database.md)
6. [Hasil Pengujian (Pest PHP)](documentation/testing.md)

---

## Ringkasan Sistem
SIAP adalah platform manajemen pesantren terpadu yang mencakup pengelolaan data santri, akademik, keuangan (cashless), dan operasional koperasi. Sistem ini menggunakan arsitektur **Multi-Tenancy** berbasis sekolah/lembaga di bawah satu naungan pusat.

### Teknologi Utama:
- **Backend**: Laravel 11
- **Frontend**: Livewire (Volt) & Flux UI
- **Database**: PostgreSQL/MySQL with ULID
- **Security**: Spatie Laravel Permission (RBAC)
