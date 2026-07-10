# Desain Sistem Informasi Inventaris Aset Barang Streaming (BMN)
**Dinas Komunikasi dan Informatika Kabupaten Tangerang — Smart Building**

Stack: **PHP Native 8.2, Bootstrap 5, MySQL/MariaDB, JavaScript (Vanilla + html5-qrcode)**

---

## 1. Daftar User Roles & Use Case

| Kode Role      | Nama Role              | Deskripsi Singkat                                                             | Use Case Utama                                                                                                                                                                                                                                                                     |
|----------------|------------------------|-------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `admin`        | Administrator Sistem   | Superuser: kelola user, kategori, konfigurasi                                 | UC-01 Kelola User & Role · UC-02 Kelola Kategori Alat · UC-03 Audit Log · UC-04 Laporan Global                                                                                                                                                                                       |
| `pemohon`      | Tim Liputan (Pemohon)  | Mengajukan peminjaman alat untuk kebutuhan liputan / streaming acara          | UC-05 Melihat Ketersediaan Alat & Kalender · UC-06 Mengajukan Peminjaman (Booking) · UC-07 Memilih Paket Alat · UC-08 Melihat Riwayat & Status Peminjaman · UC-09 Menerima Notifikasi Approval                                                                                       |
| `supervisor`   | Kepala Bagian          | Meninjau & memberi keputusan atas pengajuan peminjaman                        | UC-10 Melihat Daftar Pengajuan Menunggu · UC-11 Menyetujui / Menolak Pengajuan · UC-12 Melihat Riwayat Keputusan · UC-13 Melihat Laporan Pemakaian Alat                                                                                                                              |
| `admin_gudang` | Admin Gudang           | Mengeksekusi check-out / check-in fisik alat, mencetak SPK Perbaikan          | UC-14 Melihat Peminjaman Disetujui Hari Ini · UC-15 Scan Barcode & Check-out Alat · UC-16 Scan Barcode & Check-in Alat · UC-17 Mencatat Keluhan Kerusakan · UC-18 Mencetak Formulir Perbaikan (PDF) · UC-19 Menutup Perbaikan (Update Status Tersedia) · UC-20 Manajemen Master Alat |

> **Catatan:** Teknisi *tidak* memiliki akses ke sistem — mereka bekerja murni berbasis kertas *Formulir Perbaikan* yang dicetak Admin Gudang.

---

## 2. Kebutuhan Fitur Inti (Core Features)

### A. Autentikasi & Otorisasi
- Login JWT-based (HttpOnly Cookie) — email + password (bcrypt).
- Middleware role-based di setiap route.
- Logout, ganti password.

### B. Master Data
- CRUD **Kategori Alat** (Kamera, Lensa, Tripod, Mixer Audio, Mic, Lighting, dsb.).
- CRUD **Alat / Aset** dengan atribut: kode aset, **Nomor BMN**, nama, kategori, brand, model, serial number, kondisi awal, foto, **barcode otomatis**.
- CRUD **Paket Alat** (bundling beberapa aset untuk skenario umum, misal *"Paket Live Streaming Rapat Dinas"*).
- CRUD **User** (khusus admin).

### C. Booking / Peminjaman
- Form pengajuan: nama acara, lokasi, tanggal mulai & selesai, keperluan, pilih alat individual **atau** paket alat.
- Validasi bentrok tanggal — alat yang sudah `Booked` / `CheckedOut` di rentang tsb tidak bisa dipilih.
- Kalender ketersediaan alat.

### D. Approval Workflow
- Dashboard supervisor: antrian pengajuan.
- Aksi Approve / Reject + catatan supervisor.
- Notifikasi otomatis in-app + email ke Pemohon & Admin Gudang.

### E. Check-out / Check-in dengan Barcode Scanner
- Halaman scan (kamera browser via `html5-qrcode`) atau input barcode manual.
- Check-out: scan setiap alat dalam loan → status `CheckedOut`.
- Check-in: scan alat → pilih kondisi *(Baik / Rusak)*. Kondisi Rusak wajib mengisi catatan keluhan.

### F. Manajemen Perbaikan (SPK Fisik)
- Alat berstatus `Damaged` → tombol *Cetak Formulir Perbaikan* (halaman print-ready A4/PDF via browser print).
- Formulir berisi: kode BMN, nama alat, tanggal, pemohon terakhir, keluhan, kolom **Tindakan Perbaikan** & **Tanda Tangan Teknisi** (kosong untuk diisi manual).
- Setelah teknisi mengembalikan alat + kertas, Admin Gudang input catatan tindakan → status kembali `Available`.

### G. Notifikasi
- In-app (bell icon + badge unread count).
- Email (mocked/log-based di MVP; dapat di-swap ke SMTP/Resend).

### H. Dashboard & Reporting
- Ringkasan: total alat, alat dipinjam, alat rusak, pengajuan menunggu.
- Riwayat peminjaman & perbaikan.
- Export CSV (opsional).

### I. Keamanan
- Password hashing bcrypt.
- JWT signed HS256.
- CSRF token pada form state-changing.
- Audit log untuk operasi kritis.

---

## 3. Desain Skema Database Relasional

**RDBMS:** MariaDB 10.11 (kompatibel MySQL 8). Engine `InnoDB`, charset `utf8mb4`.

### 3.1 Tabel `users`
| Kolom          | Tipe             | Constraint                                       | Keterangan                                       |
|----------------|------------------|--------------------------------------------------|--------------------------------------------------|
| id             | BIGINT UNSIGNED  | PK, AUTO_INCREMENT                               |                                                  |
| name           | VARCHAR(120)     | NOT NULL                                         |                                                  |
| email          | VARCHAR(150)     | UNIQUE, NOT NULL                                 |                                                  |
| password_hash  | VARCHAR(255)     | NOT NULL                                         | bcrypt                                           |
| role           | ENUM             | ('admin','pemohon','supervisor','admin_gudang')  |                                                  |
| phone          | VARCHAR(30)      | NULL                                             |                                                  |
| unit_kerja     | VARCHAR(150)     | NULL                                             |                                                  |
| is_active      | TINYINT(1)       | DEFAULT 1                                        |                                                  |
| created_at     | DATETIME         | DEFAULT CURRENT_TIMESTAMP                        |                                                  |
| updated_at     | DATETIME         | ON UPDATE CURRENT_TIMESTAMP                      |                                                  |

### 3.2 Tabel `categories`
| Kolom       | Tipe            | Constraint          |
|-------------|-----------------|---------------------|
| id          | INT UNSIGNED    | PK, AI              |
| name        | VARCHAR(100)    | UNIQUE, NOT NULL    |
| description | VARCHAR(255)    | NULL                |

### 3.3 Tabel `assets`
| Kolom              | Tipe                                                     | Constraint                     | Keterangan                        |
|--------------------|----------------------------------------------------------|--------------------------------|-----------------------------------|
| id                 | BIGINT UNSIGNED                                          | PK, AI                         |                                   |
| asset_code         | VARCHAR(50)                                              | UNIQUE, NOT NULL               | Kode internal, mis. `CAM-001`     |
| bmn_number         | VARCHAR(80)                                              | UNIQUE, NOT NULL               | Nomor BMN resmi                   |
| name               | VARCHAR(150)                                             | NOT NULL                       |                                   |
| category_id        | INT UNSIGNED                                             | FK → categories.id             |                                   |
| brand              | VARCHAR(80)                                              | NULL                           |                                   |
| model              | VARCHAR(80)                                              | NULL                           |                                   |
| serial_number      | VARCHAR(120)                                             | NULL                           |                                   |
| barcode            | VARCHAR(120)                                             | UNIQUE, NOT NULL               | Value untuk QR/Barcode            |
| condition_note     | VARCHAR(255)                                             | NULL                           | Kondisi awal / catatan            |
| photo              | VARCHAR(255)                                             | NULL                           | path file                         |
| status             | ENUM('Available','Booked','CheckedOut','Damaged','Retired') | DEFAULT 'Available'         | Status hidup aset                 |
| created_at         | DATETIME                                                 | DEFAULT CURRENT_TIMESTAMP      |                                   |
| updated_at         | DATETIME                                                 | ON UPDATE CURRENT_TIMESTAMP    |                                   |

### 3.4 Tabel `packages`
| Kolom       | Tipe            | Constraint       |
|-------------|-----------------|------------------|
| id          | INT UNSIGNED    | PK, AI           |
| name        | VARCHAR(150)    | UNIQUE, NOT NULL |
| description | TEXT            | NULL             |
| is_active   | TINYINT(1)      | DEFAULT 1        |

### 3.5 Tabel `package_items` (junction)
| Kolom       | Tipe             | Constraint                                   |
|-------------|------------------|----------------------------------------------|
| package_id  | INT UNSIGNED     | FK → packages.id, ON DELETE CASCADE          |
| asset_id    | BIGINT UNSIGNED  | FK → assets.id                               |
| PK          | (package_id, asset_id)                                          |

### 3.6 Tabel `loans`
| Kolom          | Tipe                                                                                       | Constraint                                | Keterangan                    |
|----------------|--------------------------------------------------------------------------------------------|-------------------------------------------|-------------------------------|
| id             | BIGINT UNSIGNED                                                                            | PK, AI                                    |                               |
| loan_code      | VARCHAR(30)                                                                                | UNIQUE, NOT NULL                          | mis. `LN-2026-000123`         |
| requester_id   | BIGINT UNSIGNED                                                                            | FK → users.id                             | pemohon                       |
| event_name     | VARCHAR(200)                                                                               | NOT NULL                                  |                               |
| event_location | VARCHAR(200)                                                                               | NULL                                      |                               |
| start_date     | DATE                                                                                       | NOT NULL                                  |                               |
| end_date       | DATE                                                                                       | NOT NULL                                  |                               |
| purpose        | TEXT                                                                                       | NULL                                      |                               |
| status         | ENUM('Pending','Approved','Rejected','CheckedOut','Returned','Completed','Cancelled')      | DEFAULT 'Pending'                         | Status header loan            |
| supervisor_id  | BIGINT UNSIGNED                                                                            | FK → users.id, NULL                       | pemberi keputusan             |
| approval_note  | TEXT                                                                                       | NULL                                      |                               |
| approved_at    | DATETIME                                                                                   | NULL                                      |                               |
| checkout_at    | DATETIME                                                                                   | NULL                                      |                               |
| checkin_at     | DATETIME                                                                                   | NULL                                      |                               |
| created_at     | DATETIME                                                                                   | DEFAULT CURRENT_TIMESTAMP                 |                               |

### 3.7 Tabel `loan_items`
Merepresentasikan tiap fisik alat dalam sebuah loan.

| Kolom              | Tipe                                                                             | Constraint                                     |
|--------------------|----------------------------------------------------------------------------------|------------------------------------------------|
| id                 | BIGINT UNSIGNED                                                                  | PK, AI                                         |
| loan_id            | BIGINT UNSIGNED                                                                  | FK → loans.id, ON DELETE CASCADE               |
| asset_id           | BIGINT UNSIGNED                                                                  | FK → assets.id                                 |
| package_id         | INT UNSIGNED                                                                     | FK → packages.id, NULL                         |
| checkout_by        | BIGINT UNSIGNED                                                                  | FK → users.id, NULL                            |
| checkout_at        | DATETIME                                                                         | NULL                                           |
| checkin_by         | BIGINT UNSIGNED                                                                  | FK → users.id, NULL                            |
| checkin_at         | DATETIME                                                                         | NULL                                           |
| return_condition   | ENUM('Good','Damaged')                                                           | NULL                                           |
| damage_note        | TEXT                                                                             | NULL                                           |
| item_status        | ENUM('Reserved','CheckedOut','ReturnedGood','ReturnedDamaged','InRepair','Restored') | DEFAULT 'Reserved'                          |

### 3.8 Tabel `repairs`
| Kolom              | Tipe             | Constraint                          | Keterangan                          |
|--------------------|------------------|-------------------------------------|-------------------------------------|
| id                 | BIGINT UNSIGNED  | PK, AI                              |                                     |
| repair_code        | VARCHAR(30)      | UNIQUE, NOT NULL                    | mis. `RP-2026-000045`               |
| asset_id           | BIGINT UNSIGNED  | FK → assets.id                      |                                     |
| loan_item_id       | BIGINT UNSIGNED  | FK → loan_items.id, NULL            | sumber kerusakan                    |
| complaint          | TEXT             | NOT NULL                            | keluhan awal dari Admin Gudang      |
| form_printed_at    | DATETIME         | NULL                                | timestamp cetak SPK                 |
| technician_name    | VARCHAR(120)     | NULL                                | diisi saat penutupan (dari kertas)  |
| action_taken       | TEXT             | NULL                                | tindakan teknisi (dari kertas)      |
| completed_by       | BIGINT UNSIGNED  | FK → users.id, NULL                 | admin gudang yang menutup           |
| completed_at       | DATETIME         | NULL                                |                                     |
| status             | ENUM('Open','FormPrinted','InRepair','Completed') | DEFAULT 'Open' |                                     |
| created_at         | DATETIME         | DEFAULT CURRENT_TIMESTAMP           |                                     |

### 3.9 Tabel `notifications`
| Kolom      | Tipe             | Constraint                            |
|------------|------------------|---------------------------------------|
| id         | BIGINT UNSIGNED  | PK, AI                                |
| user_id    | BIGINT UNSIGNED  | FK → users.id, ON DELETE CASCADE      |
| title      | VARCHAR(200)     | NOT NULL                              |
| body       | TEXT             | NULL                                  |
| link       | VARCHAR(255)     | NULL                                  |
| is_read    | TINYINT(1)       | DEFAULT 0                             |
| created_at | DATETIME         | DEFAULT CURRENT_TIMESTAMP             |

### 3.10 Tabel `audit_logs`
| Kolom       | Tipe             | Constraint                           |
|-------------|------------------|--------------------------------------|
| id          | BIGINT UNSIGNED  | PK, AI                               |
| user_id     | BIGINT UNSIGNED  | FK → users.id, NULL                  |
| action      | VARCHAR(100)     | NOT NULL                             |
| entity_type | VARCHAR(60)      | NULL                                 |
| entity_id   | VARCHAR(60)      | NULL                                 |
| details     | TEXT             | NULL                                 |
| created_at  | DATETIME         | DEFAULT CURRENT_TIMESTAMP            |

### 3.11 Relasi (ER Ringkas)
```
users ─┬─< loans (requester_id, supervisor_id)
       └─< loan_items.checkout_by / checkin_by
       └─< repairs.completed_by
       └─< notifications
       └─< audit_logs

categories ─< assets ─┬─< loan_items ─── loans
                      ├─< package_items >─ packages
                      └─< repairs
loan_items ─< repairs (loan_item_id)
```

---

## 4. Status State Machine — Entitas Aset

```
                   ┌───────────────────────────────────────────────────────┐
                   │                                                       │
      (init)       ▼                                                       │
     ┌────────► Available ──── Booking (loan Approved) ──► Booked ─────────┤
     │            ▲                                          │             │
     │            │ Repair.Completed                         │ Check-out   │
     │            │                                          ▼             │
     │        Damaged  ◄── Check-in (Rusak) ── CheckedOut ◄──┘             │
     │            ▲                                │                       │
     │            │                                │ Check-in (Baik)       │
     │            │                                ▼                       │
     │            │                             Available ─────────────────┘
     │            │
     │       (SPK Cetak → InRepair → Restored)
     │
    Retired  (jika tidak bisa diperbaiki / dihapus dari inventaris)
```

### Tabel Transisi Aset

| Dari            | Event / Trigger                                          | Ke              | Aktor            |
|-----------------|----------------------------------------------------------|-----------------|------------------|
| — (init)        | CREATE asset                                             | `Available`     | admin/admin_gudang |
| `Available`     | Loan status → **Approved** oleh supervisor               | `Booked`        | supervisor       |
| `Booked`        | Loan **CheckedOut** (scan barcode) oleh Admin Gudang     | `CheckedOut`    | admin_gudang     |
| `Booked`        | Loan **Cancelled** / **Rejected**                        | `Available`     | supervisor/system|
| `CheckedOut`    | Check-in dengan kondisi **Good**                         | `Available`     | admin_gudang     |
| `CheckedOut`    | Check-in dengan kondisi **Damaged** + keluhan            | `Damaged`       | admin_gudang     |
| `Damaged`       | Cetak Formulir Perbaikan (SPK) — repair `FormPrinted`    | `Damaged`*      | admin_gudang     |
| `Damaged`       | Serah terima ke Teknisi — repair `InRepair`              | `Damaged`*      | admin_gudang     |
| `Damaged`       | Perbaikan Selesai (input catatan teknisi) — repair `Completed` | `Available` | admin_gudang     |
| `Damaged`       | Tidak dapat diperbaiki → Retire                          | `Retired`       | admin            |
| `Available/Damaged` | Retire manual                                        | `Retired`       | admin            |

`*` = status aset tetap `Damaged` selama proses fisik; state repair-nya bergerak `Open → FormPrinted → InRepair → Completed`.

### State Machine Loan (pelengkap)
```
Pending ──► Approved ──► CheckedOut ──► Returned ──► Completed
   │           │
   └► Rejected └► Cancelled
```

### State Machine Repair (pelengkap)
```
Open ──► FormPrinted ──► InRepair ──► Completed
```

---

**End of Design Document.**
