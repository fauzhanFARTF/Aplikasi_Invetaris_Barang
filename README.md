# SIMASSTA BMN
**Sistem Informasi Manajemen Aset Streaming — Diskominfo Kabupaten Tangerang**

Stack: **PHP 8.1+ native · Bootstrap 5.3 · MySQL/MariaDB · Vanilla JS + html5-qrcode**

Repository: <https://github.com/fauzhanFARTF/Aplikasi_Invetaris_Barang>

Aplikasi ini menggunakan **auto-detect BASE_PATH**, sehingga bisa dijalankan di **root domain** (mis. `http://simassta.go.id/`) maupun di **subdirektori** (mis. `http://localhost/simassta-bmn/public/`).

---

## 🚀 Instalasi di XAMPP (Windows)

> **⚠️ WAJIB**: Gunakan **XAMPP versi 8.1 atau lebih baru** (PHP 8.1+). XAMPP lama dengan PHP 5.6 / 7.0 **tidak didukung** — aplikasi akan menampilkan pesan upgrade.
> Download XAMPP terbaru: <https://www.apachefriends.org/download.html> (pilih versi PHP 8.2 atau 8.3).

### Langkah 1 — Ekstrak
Ekstrak file ZIP ke folder `htdocs` XAMPP:
```
C:\xampp\htdocs\simassta-bmn\
```
Struktur akhir:
```
C:\xampp\htdocs\simassta-bmn\
    ├── config\
    ├── database\
    ├── public\        ← web root
    ├── src\
    ├── views\
    ├── .env.example
    └── README.md
```

### Langkah 2 — Start XAMPP
Buka **XAMPP Control Panel** → **Start Apache** dan **Start MySQL**.

### Langkah 3 — Buat Database
Buka browser: <http://localhost/phpmyadmin>
1. Klik menu **Import**.
2. Pilih file `simassta-bmn/database/schema.sql`.
3. Klik **Go**. Database `bmn_streaming` akan otomatis dibuat.

Atau via terminal:
```cmd
cd C:\xampp\htdocs\simassta-bmn
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
```

### Langkah 4 — Konfigurasi `.env`
Salin `.env.example` menjadi `.env`, lalu edit:
```env
APP_URL="http://localhost/simassta-bmn/public"
JWT_SECRET="ganti-dengan-32-karakter-random-anda-sendiri"
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_NAME="bmn_streaming"
DB_USER="root"
DB_PASS=""
DB_SOCKET=""
MAIL_MODE="log"
```
> **PENTING**: kosongkan `DB_SOCKET=""` — XAMPP tidak menggunakan unix socket.
> Ganti `JWT_SECRET` dengan string acak (bisa generate di <https://randomkeygen.com>).

### Langkah 5 — Isi Data Awal
Dari **XAMPP Shell** (klik tombol "Shell" di XAMPP Control Panel):
```cmd
cd C:\xampp\htdocs\simassta-bmn
php database\seed.php
```
Output yang diharapkan:
```
Seeding data...
  ✓ Users seeded
  ✓ Categories seeded
  ✓ Assets seeded
  ✓ Packages seeded
Done!
```

### Langkah 6 — Buka Aplikasi 🎉

**URL utama** (memerlukan `mod_rewrite` aktif — biasanya default di XAMPP baru):
**<http://localhost/simassta-bmn/public/login>**

**Kalau URL utama 404** (Apache lama / mod_rewrite belum aktif), gunakan URL fallback:
**<http://localhost/simassta-bmn/public/index.php/login>**

> ⚠️ Web root aplikasi ada di folder `public/`. URL harus include `/public/`.

Login dengan akun demo:

| Role           | Email                                             | Password       |
|----------------|---------------------------------------------------|----------------|
| Administrator  | admin@diskominfo.tangerangkab.go.id              | admin123       |
| Pemohon        | andi@diskominfo.tangerangkab.go.id               | pemohon123     |
| Supervisor     | budi@diskominfo.tangerangkab.go.id               | supervisor123  |
| Admin Gudang   | dewi@diskominfo.tangerangkab.go.id               | gudang123      |

---

## 🎯 Opsi Alternatif: URL yang lebih pendek

Kalau Anda ingin URL menjadi `http://localhost/simassta-bmn/` (tanpa `/public`), ada 2 pilihan:

### Opsi A — Virtual Host (recommended production)
Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerName simassta.local
    DocumentRoot "C:/xampp/htdocs/simassta-bmn/public"
    <Directory "C:/xampp/htdocs/simassta-bmn/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Tambahkan `127.0.0.1 simassta.local` ke `C:\Windows\System32\drivers\etc\hosts`.
Restart Apache. Akses: <http://simassta.local/login>

### Opsi B — Redirect via .htaccess di root subdirektori
Buat file `C:\xampp\htdocs\simassta-bmn\.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule ^((?!public/).*)$ public/$1 [L,NC]
</IfModule>
```
Akses: <http://localhost/simassta-bmn/login>

---

## 🐧 Instalasi di Linux (Apache/Nginx)

### Apache
```bash
# 1. Deploy
sudo cp -r simassta-bmn /var/www/
sudo chown -R www-data:www-data /var/www/simassta-bmn/storage
sudo chmod -R 775 /var/www/simassta-bmn/storage

# 2. Enable mod_rewrite
sudo a2enmod rewrite

# 3. Configure vhost pointing DocumentRoot ke .../public
sudo nano /etc/apache2/sites-available/simassta.conf
# Isi:
# <VirtualHost *:80>
#     ServerName simassta.example.go.id
#     DocumentRoot /var/www/simassta-bmn/public
#     <Directory /var/www/simassta-bmn/public>
#         AllowOverride All
#         Require all granted
#     </Directory>
# </VirtualHost>

sudo a2ensite simassta && sudo systemctl reload apache2

# 4. Setup DB
mysql -u root -p < /var/www/simassta-bmn/database/schema.sql
cd /var/www/simassta-bmn && cp .env.example .env  # edit as needed
php database/seed.php
```

### Nginx
```nginx
server {
    listen 80;
    server_name simassta.example.go.id;
    root /var/www/simassta-bmn/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

### PHP Built-in Server (development)
```bash
cd simassta-bmn
php -S 0.0.0.0:8080 -t public public/router.php
```
Akses: <http://localhost:8080/login>

---

## 📷 Barcode & QR — Generate dan Scan dari HP / Perangkat Lain

### Mencetak Barcode Aset
1. Buka menu **Alat / Aset** → centang satu atau beberapa alat → klik **"Cetak Barcode Terpilih"** (atau klik ikon barcode di satu baris untuk cetak satuan).
2. Halaman baru akan terbuka berisi label siap cetak: nama alat, No. BMN, **barcode Code128** (untuk alat pemindai genggam), dan **QR code** kecil (untuk kamera HP).
3. Klik **Cetak**, gunakan kertas label/sticker atau kertas biasa lalu gunting sesuai garis putus-putus, tempel di badan alat.

### Scan dari Kamera HP
Halaman **Check-out**, **Check-in**, dan formulir lain yang memakai kamera bisa dibuka langsung dari HP (browser Chrome/Safari) selama alamat yang diakses sama dengan yang dipakai di komputer (mis. `http://192.168.1.10/simassta-bmn/public/checkout/5`).

> ⚠️ **Penting**: browser modern (Chrome, Safari, dst.) **memblokir akses kamera** di alamat `http://` biasa kecuali `localhost`. Jika dibuka dari HP lewat IP jaringan lokal (bukan `localhost`), kamera mungkin tidak aktif. Solusinya salah satu dari berikut:
> - **Opsi termudah**: gunakan tool seperti [ngrok](https://ngrok.com/) atau [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) untuk membuat alamat `https://` sementara yang bisa diakses dari HP.
> - **Untuk jaringan kantor tetap**: pasang sertifikat SSL/HTTPS asli (mis. lewat reverse proxy Nginx + Let's Encrypt / self-signed dengan [mkcert](https://github.com/FiloSottile/mkcert)) di server tempat aplikasi berjalan.
> - **Alternatif tanpa kamera**: gunakan kolom **"Ketik atau tembak barcode di sini"** yang tersedia di setiap halaman scan — bisa diketik manual atau ditembak langsung memakai **alat pemindai barcode USB/Bluetooth** (perangkat ini berperilaku seperti keyboard, jadi selalu berfungsi tanpa perlu HTTPS maupun akses kamera).

### Kompatibilitas Alat Pemindai
- **Kamera HP/tablet/laptop**: mendukung QR code dan barcode 1D umum (Code128, Code39, EAN-13/8, UPC-A/E).
- **Scanner genggam USB/Bluetooth**: langsung "mengetik" hasil pindai ke kolom manual barcode yang otomatis fokus di setiap halaman scan — tidak perlu instalasi driver tambahan, karena perangkat ini umumnya bekerja sebagai keyboard (HID).

## 🐞 Troubleshooting XAMPP

| Gejala | Solusi |
|---|---|
| **"Perlu Upgrade PHP"** halaman muncul | XAMPP Anda memakai PHP <7.4. Download & install XAMPP terbaru dari <https://www.apachefriends.org/download.html> (PHP 8.2). |
| `ERR_CONNECTION_REFUSED` di `localhost:8080` | XAMPP Apache berjalan di **port 80**, bukan 8080. Coba `http://localhost/simassta-bmn/public/login` |
| **`Object not found / 404`** di `/public/login` | `mod_rewrite` Apache tidak aktif. Gunakan **URL fallback**: `http://localhost/simassta-bmn/public/index.php/login` — aplikasi tetap jalan penuh tanpa rewrite. Untuk mengaktifkan rewrite: edit `C:\xampp\apache\conf\httpd.conf`, cari `#LoadModule rewrite_module ...` dan hapus tanda `#`. Restart Apache. |
| Halaman kosong / 500 error | Cek `C:\xampp\apache\logs\error.log`. Pastikan ekstensi `pdo_mysql`, `mbstring`, `gd` aktif di `php.ini` |
| CSS/JS tidak muncul | Pastikan folder `public/assets/` ada. Untuk URL `/index.php/login`, CSS otomatis pakai URL statis (bypass index.php) — seharusnya bekerja. |
| "SQLSTATE[HY000] [2002]" saat login | Cek MySQL sudah start di XAMPP + `DB_SOCKET=""` (kosongkan) di `.env` |
| Redirect infinite loop | Pastikan tidak ada `.htaccess` konflik di parent directory `htdocs\` |
| `.htaccess` tidak terekstrak (file hidden) | Aktifkan "Show hidden files" di Windows Explorer sebelum ekstrak. Atau ekstrak pakai 7-Zip yang tidak menyembunyikan dotfiles. |

---

## 🗂️ Struktur Direktori

```
simassta-bmn/
├── config/              # config.php, database.php
├── database/
│   ├── schema.sql
│   └── seed.php
├── docs/DESAIN_SISTEM.md
├── public/              # === WEB ROOT ===
│   ├── index.php        # front controller
│   ├── router.php       # utk `php -S`
│   ├── .htaccess        # Apache rewrite
│   └── assets/{css,js}
├── src/
│   ├── Auth.php, JWT.php, Helpers.php, Mailer.php, Notification.php
│   └── Controllers/     # 8 controller procedural
├── views/               # 24 template Bootstrap 5
├── storage/{emails,logs}  # writable
├── .env.example
└── README.md
```

---

## 🔐 Keamanan Production Checklist
- [ ] Ganti semua password default akun demo.
- [ ] Ganti `JWT_SECRET` di `.env` (min 32 karakter acak).
- [ ] Set `APP_ENV=production`.
- [ ] Aktifkan HTTPS (Let's Encrypt).
- [ ] Set `MAIL_MODE=smtp` + kredensial email dinas.
- [ ] `chmod 775 storage/` (writable webserver saja).
- [ ] Backup database rutin.

---

## 📝 Lisensi
Internal use — Pemerintah Kabupaten Tangerang, Diskominfo Smart Building.
