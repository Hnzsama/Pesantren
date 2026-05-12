# 📘 Dokumentasi Sistem SIAP
### *Sistem Informasi Akademik Pesantren Terpadu*

---

[![System Status](https://img.shields.io/badge/System-Stable-success?style=for-the-badge)](./documentation/testing.md)
[![Documentation](https://img.shields.io/badge/Documentation-Complete-blue?style=for-the-badge)](./documentation.md)
[![Architecture](https://img.shields.io/badge/Architecture-Multi--Tenancy-orange?style=for-the-badge)](./documentation/dfd.md)

Selamat datang di pusat dokumentasi teknis **Sistem SIAP**. Dokumen ini dirancang untuk memberikan pemahaman mendalam mengenai arsitektur, alur fitur, dan integritas data sistem.

---

## 🗺️ Navigasi Dokumentasi

Silakan pilih modul dokumentasi di bawah ini untuk mempelajari lebih lanjut:

| Modul | Deskripsi | Tautan |
| :--- | :--- | :---: |
| **🛡️ Fitur & RBAC** | Analisis fitur mendalam dan matriks hak akses setiap role. | [Buka Dokumen](./documentation/features_rbac.md) |
| **🔄 Arsitektur Data** | Diagram Aliran Data (DFD) Level 0, 1, dan 2. | [Buka Dokumen](./documentation/dfd.md) |
| **🎬 Sequence Diagram** | Gambaran interaksi antar objek dalam skenario fitur utama. | [Buka Dokumen](./documentation/sequence.md) |
| **⚡ Activity Diagram** | Alur aktivitas pengguna (logic flow) untuk setiap modul. | [Buka Dokumen](./documentation/activity.md) |
| **📉 Flowchart** | Diagram alir sistem untuk proses bisnis teknis. | [Buka Dokumen](./documentation/flowchart.md) |
| **🗄️ Database Schema** | Struktur tabel, relasi, dan skema database (DBML). | [Buka Dokumen](./documentation/database.md) |
| **🧪 Hasil Pengujian** | Laporan pengujian fungsional menggunakan Pest PHP. | [Buka Dokumen](./documentation/testing.md) |

---

## 🏗️ Ringkasan Arsitektur

SIAP adalah platform manajemen pesantren terpadu yang menggunakan arsitektur **Multi-Tenancy** berbasis *School Scoping*. Setiap lembaga/pesantren memiliki isolasi data yang ketat namun tetap di bawah koordinasi pusat.

### 🛠️ Stack Teknologi:
- **Core Engine**: `Laravel 11.x`
- **Frontend Engine**: `Livewire 3.x` & `Alpine.js`
- **UI System**: `Flux UI` & `Tailwind CSS`
- **Security Logic**: `Spatie Permission` (RBAC)
- **Data Integrity**: `ULID` as Primary Keys & `HasSchoolScope` Traits
- **Testing Engine**: `Pest PHP` with 100% Success Rate

---
*Dokumentasi ini adalah bagian dari standar pengembangan teknis Pesantren. Silakan hubungi tim pengembang jika terdapat ambiguitas.*
