#!/usr/bin/env bash
# setup-cloudflared.sh — Cloudflare "Quick Tunnel" TANPA domain & TANPA akun.
# Memberi URL publik https://<acak>.trycloudflare.com untuk aplikasi lokal,
# lengkap dengan HTTPS valid (fitur kamera langsung aktif, tanpa warning).
#
# Pemakaian (jalankan sebagai root):
#   sudo bash scripts/setup-cloudflared.sh
#
# Catatan: URL trycloudflare BERSIFAT SEMENTARA & BERUBAH setiap tunnel
# restart/reboot. Untuk URL tetap, pakai named tunnel + domain (lihat README /
# panduan Cloudflare Tunnel). Idempoten: aman dijalankan berkali-kali.
set -euo pipefail

SERVICE="cloudflared-quick"

if [ "$(id -u)" -ne 0 ]; then
  echo "Harus dijalankan sebagai root: sudo bash scripts/setup-cloudflared.sh" >&2
  exit 1
fi

echo ">> 1/4 Pasang cloudflared (bila belum ada)"
if ! command -v cloudflared >/dev/null 2>&1; then
  ARCH="$(dpkg --print-architecture)"   # amd64 / arm64
  TMP="$(mktemp -d)"
  curl -fL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb" -o "$TMP/cloudflared.deb"
  apt install -y "$TMP/cloudflared.deb"
  rm -rf "$TMP"
else
  echo "   (sudah terpasang: $(cloudflared --version 2>/dev/null | head -1))"
fi

echo ">> 2/4 Tentukan origin lokal"
# Pakai HTTPS 443 kalau tersedia (bypass redirect http->https dari setup-https.sh);
# selain itu pakai HTTP 80 langsung.
if ss -ltn 2>/dev/null | grep -qE ':443( |$)' || [ -f /etc/ssl/certs/simassta-selfsigned.crt ]; then
  ORIGIN="https://localhost:443"
  EXTRA="--no-tls-verify"
else
  ORIGIN="http://localhost:80"
  EXTRA=""
fi
echo "   Origin: $ORIGIN $EXTRA"

echo ">> 3/4 Pasang & jalankan service quick tunnel"
cat > /etc/systemd/system/${SERVICE}.service <<UNIT
[Unit]
Description=Cloudflare Quick Tunnel (trycloudflare) untuk SIMANTAP BMN
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=/usr/bin/cloudflared tunnel --no-autoupdate --url ${ORIGIN} ${EXTRA}
Restart=on-failure
RestartSec=5
User=root

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable ${SERVICE} >/dev/null 2>&1 || true
systemctl restart ${SERVICE}

echo ">> 4/4 Menunggu URL publik dari Cloudflare..."
URL=""
for _ in $(seq 1 20); do
  sleep 2
  URL="$(journalctl -u ${SERVICE} --no-pager 2>/dev/null | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1)"
  [ -n "$URL" ] && break
done

echo
if [ -n "$URL" ]; then
  echo "=== TUNNEL AKTIF ==="
  echo "   URL publik : $URL"
  echo "   Buka di browser mana pun — HTTPS valid, kamera aktif."
else
  echo "URL belum terbaca. Cek manual:"
  echo "   sudo journalctl -u ${SERVICE} | grep trycloudflare"
fi

echo
echo "-------------------------------------------------------------------"
echo "URL trycloudflare BERUBAH tiap restart/reboot. Lihat URL saat ini:"
echo "   sudo journalctl -u ${SERVICE} | grep -oE 'https://[a-z0-9-]+\\.trycloudflare\\.com' | tail -1"
echo "Hentikan tunnel : sudo systemctl stop ${SERVICE}"
echo "Nonaktifkan     : sudo systemctl disable --now ${SERVICE}"
echo "-------------------------------------------------------------------"
