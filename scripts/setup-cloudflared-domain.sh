#!/usr/bin/env bash
# setup-cloudflared-domain.sh — pasang cloudflared + jalankan NAMED TUNNEL
# (URL tetap memakai domain sendiri, mis. https://simantaptangerangkab.com)
# memakai TOKEN dari Cloudflare Zero Trust. HTTPS valid otomatis, tanpa port
# forwarding, tanpa A record ke IP privat.
#
# Pemakaian (jalankan sebagai root):
#   sudo bash scripts/setup-cloudflared-domain.sh <TOKEN>
#
# <TOKEN> didapat dari:
#   Cloudflare Dashboard > Zero Trust > Networks > Tunnels > Create a tunnel
#   > Cloudflared > (beri nama) > pilih tab Debian/Ubuntu. Token adalah string
#   panjang "eyJ..." pada perintah  `cloudflared service install eyJ...`.
#
# Idempoten: aman dijalankan berkali-kali (mis. saat token diganti).
set -euo pipefail

APPDIR="/var/www/simassta-bmn"
DOMAIN="simantaptangerangkab.com"

if [ "$(id -u)" -ne 0 ]; then
  echo "Harus dijalankan sebagai root: sudo bash scripts/setup-cloudflared-domain.sh <TOKEN>" >&2
  exit 1
fi
TOKEN="${1:-}"
if [ -z "$TOKEN" ]; then
  echo "ERROR: token belum diberikan." >&2
  echo "Pemakaian: sudo bash scripts/setup-cloudflared-domain.sh <TOKEN>" >&2
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

echo ">> 2/4 Bersihkan service lama agar tak bentrok"
systemctl disable --now cloudflared-quick >/dev/null 2>&1 || true   # quick-tunnel lama (bila ada)
cloudflared service uninstall >/dev/null 2>&1 || true                # named-tunnel lama (bila ada)

echo ">> 3/4 Pasang service tunnel dengan token"
cloudflared service install "$TOKEN"

echo ">> 4/4 Update APP_URL -> https://$DOMAIN"
if [ -f "$APPDIR/.env" ]; then
  sed -i 's#^APP_URL=.*#APP_URL="https://'"$DOMAIN"'"#' "$APPDIR/.env"
  echo "   ($(grep '^APP_URL=' "$APPDIR/.env"))"
fi

sleep 3
echo
echo "=== Status connector ==="
systemctl is-active cloudflared >/dev/null 2>&1 && echo "   cloudflared: AKTIF" || echo "   cloudflared: belum aktif (cek: journalctl -u cloudflared)"
echo
echo "-------------------------------------------------------------------"
echo "LANGKAH TERAKHIR (di dashboard Cloudflare, sekali saja):"
echo "  Zero Trust > Networks > Tunnels > (tunnel Anda) > tab Public Hostname"
echo "  > Add a public hostname:"
echo "     Subdomain    : (kosong)"
echo "     Domain       : $DOMAIN"
echo "     Service Type : HTTPS"
echo "     URL          : localhost:443"
echo "     Additional application settings > TLS > No TLS Verify: ON"
echo "  (Ulangi untuk 'www' bila ingin www.$DOMAIN juga aktif.)"
echo
echo "Catatan: origin localhost:443 = vhost HTTPS self-signed dari"
echo "setup-https.sh. Kalau 443 belum ada, jalankan dulu:"
echo "  sudo bash scripts/setup-https.sh"
echo "Alternatif: pakai Service Type HTTP + URL localhost:80 (hanya jika"
echo "vhost port 80 melayani aplikasi langsung TANPA redirect ke https)."
echo
echo "Setelah itu buka: https://$DOMAIN  (HTTPS valid, kamera aktif)."
echo "-------------------------------------------------------------------"
