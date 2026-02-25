# ⚓ Ship Management System (SMS)

Sistem manajemen inventaris sparepart kapal berbasis web yang dirancang untuk membantu tim teknis dalam memantau dan mengelola ketersediaan sparepart di seluruh armada kapal secara terpusat.

## 📋 Tentang Proyek

SMS (Ship Management System) adalah aplikasi web internal yang dibangun menggunakan **PHP** dan **MySQL**, dengan antarmuka modern berbasis **Bootstrap 5**. Sistem ini memungkinkan pengelolaan inventaris sparepart kapal secara terstruktur — mulai dari pencatatan komponen, pengaturan stok, hingga pembuatan laporan.

Setiap kapal dalam armada memiliki struktur inventaris berjenjang:

```
Kapal (Vessel)
└── Main Induk (Komponen Utama, misal: MAIN ENGINE)
    └── Sub Induk (Sub-Komponen, misal: FUEL SYSTEM)
        └── Item / Sparepart (misal: Fuel Filter, O-Ring, etc.)
```

## ✨ Fitur Utama

- **Dashboard Fleet** — Ringkasan statistik seluruh armada: total jenis part, total stok fisik, estimasi nilai aset, dan jumlah kapal. Dilengkapi grafik rasio stok sehat vs kritis.
- **Manajemen Inventaris** — CRUD lengkap untuk Main Induk, Sub Induk, dan item sparepart per kapal.
- **Stock Adjustment** — Pencatatan mutasi stok masuk/keluar dengan keterangan, beserta riwayat log perubahan per item.
- **Alert Stok Kritis** — Item dengan stok di bawah 5 unit otomatis ditandai merah sebagai peringatan.
- **Import Data** — Support import massal via file **CSV** dan input **Bulk Table** (kompatibel dengan paste dari Excel).
- **Export PDF** — Generate laporan inventaris dalam format PDF berdasarkan rentang tanggal yang dipilih, menggunakan library **Dompdf**.
- **Filter & Pencarian** — Filter inventaris berdasarkan nama part, part number, Main Induk, atau Sub Induk.
- **Multi-Vessel** — Satu sistem untuk semua kapal dalam armada, dengan tampilan per-kapal maupun global.

## 🛠️ Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP (Native / Procedural) |
| Database | MySQL (via MySQLi) |
| Frontend | HTML, CSS, Bootstrap 5 |
| Icons | Lucide Icons |
| Charts | Chart.js |
| PDF Export | Dompdf |

## 📁 Struktur File

| File | Keterangan |
|---|---|
| `index.php` | Dashboard utama & overview armada |
| `inventory.php` | Halaman manajemen inventaris per kapal |
| `history.php` | Riwayat adjustment stok per item |
| `tambah_vessel.php` | Form registrasi kapal baru |
| `export_pdf.php` | Generator laporan PDF |
| `import.php` | Handler import CSV |
| `adjustment.php` | Handler adjustment stok |
| `koneksi.php` | Konfigurasi koneksi database _(tidak di-track oleh Git)_ |

---

> Dibangun untuk kebutuhan internal pengelolaan sparepart armada kapal.
