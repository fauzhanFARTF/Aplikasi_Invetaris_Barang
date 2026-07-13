#!/usr/bin/env bash
# Setup HTTPS (sertifikat self-signed) + redirect http->https untuk SIMASSTA BMN
# di server Apache/Ubuntu. Diperlukan agar fitur kamera (getUserMedia) aktif —
# browser hanya mengizinkan kamera di https:// atau localhost.
#
# Pemakaian (jalankan sebagai root):
#   sudo bash scripts/setup-https.sh                 # pakai IP default
#   sudo bash scripts/setup-https.sh 172.16.64.250   # atau tentukan IP/host sendiri
#
# Idempoten: aman dijalankan berkali-kali.
set -euo pipefail

IP="${1:-172.16.64.250}"
APPDIR="/var/www/simassta-bmn"
CRT="/etc/ssl/certs/simassta-selfsigned.crt"
KEY="/etc/ssl/private/simassta-selfsigned.key"

if [ "$(id -u)" -ne 0 ]; then
  echo "Harus dijalankan sebagai root: sudo bash scripts/setup-https.sh" >&2
  exit 1
fi

echo ">> 1/8 Aktifkan modul ssl & rewrite"
a2enmod ssl rewrite >/dev/null

echo ">> 2/8 Buat sertifikat self-signed (SAN=IP:$IP) bila belum ada"
if [ ! -f "$CRT" ] || [ ! -f "$KEY" ]; then
  openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
    -keyout "$KEY" -out "$CRT" \
    -subj "/C=ID/ST=Banten/L=Tangerang/O=Diskominfo/CN=$IP" \
    -addext "subjectAltName=IP:$IP"
else
  echo "   (sertifikat sudah ada, dilewati)"
fi

echo ">> 3/8 Tulis vhost HTTPS (port 443)"
cat > /etc/apache2/sites-available/simassta-ssl.conf <<CONF
<VirtualHost *:443>
    ServerName $IP
    DocumentRoot $APPDIR/public
    <Directory $APPDIR/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile    $CRT
    SSLCertificateKeyFile $KEY
    ErrorLog  \${APACHE_LOG_DIR}/simassta-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/simassta-ssl-access.log combined
</VirtualHost>
CONF

echo ">> 4/8 Tulis vhost HTTP (port 80) -> redirect ke https"
cat > /etc/apache2/sites-available/simassta.conf <<CONF
<VirtualHost *:80>
    ServerName $IP
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
    ErrorLog  \${APACHE_LOG_DIR}/simassta-error.log
    CustomLog \${APACHE_LOG_DIR}/simassta-access.log combined
</VirtualHost>
CONF

echo ">> 5/8 Aktifkan site & nonaktifkan default"
a2ensite simassta simassta-ssl >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true

echo ">> 6/8 Buka firewall 443 (bila ufw aktif)"
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
  ufw allow 443/tcp >/dev/null || true
  echo "   (ufw: 443/tcp diizinkan)"
else
  echo "   (ufw tidak aktif, dilewati)"
fi

echo ">> 7/8 Update APP_URL di .env -> https"
if [ -f "$APPDIR/.env" ]; then
  sed -i 's#^APP_URL=.*#APP_URL="https://'"$IP"'"#' "$APPDIR/.env"
  echo "   ($(grep '^APP_URL=' "$APPDIR/.env"))"
fi

echo ">> 8/8 Cek konfigurasi & reload Apache"
apache2ctl configtest
systemctl reload apache2

echo
echo "=== SELESAI. Verifikasi: ==="
curl -sk -o /dev/null -w "  https -> %{http_code} (200 = OK)\n" "https://$IP/login" || true
echo "  redirect http:"
curl -s -o /dev/null -D - "http://$IP/login" | grep -iE "HTTP/|Location" | sed 's/^/    /'
echo
echo "Buka di browser: https://$IP/login  (terima peringatan sertifikat sekali)."
echo "Setelah itu tombol 'Ambil dari Kamera' di form Alat/User akan aktif."
