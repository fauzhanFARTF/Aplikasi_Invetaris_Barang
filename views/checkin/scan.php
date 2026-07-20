<div class="page-header">
    <div>
        <h1>Pengembalian <span class="text-mono text-slate" style="font-size:16px;">— <?= e($loan['loan_code']) ?></span></h1>
        <p class="subtitle">Pemohon: <strong><?= e($loan['requester_name']) ?></strong> · Acara: <?= e($loan['event_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/checkin" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        <form method="POST" action="<?= BASE_PATH ?>/checkin/<?= e($loan["uuid"]) ?>/finalize" data-confirm="Selesaikan pengembalian?">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn btn-primary" data-testid="btn-finalize-checkin"><i class="fa-solid fa-flag-checkered"></i> Selesai Pengembalian</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="scanner-wrap">
            <div class="scanner-video"><div id="reader"></div></div>
            <div>
                <div class="mb-3">
                    <label class="form-label text-slate small">Kondisi setelah kembali</label>
                    <div class="btn-group w-100">
                        <input type="radio" class="btn-check" name="cond" id="condGood" value="Good" checked data-testid="radio-good">
                        <label class="btn btn-outline-success" for="condGood"><i class="fa-solid fa-circle-check"></i> Baik</label>
                        <input type="radio" class="btn-check" name="cond" id="condDamaged" value="Damaged" data-testid="radio-damaged">
                        <label class="btn btn-outline-danger" for="condDamaged"><i class="fa-solid fa-triangle-exclamation"></i> Rusak</label>
                        <input type="radio" class="btn-check" name="cond" id="condLost" value="Lost" data-testid="radio-lost">
                        <label class="btn btn-outline-dark" for="condLost"><i class="fa-solid fa-circle-question"></i> Hilang</label>
                    </div>
                </div>
                <div class="mb-3" id="damageNoteWrap" style="display:none;">
                    <label class="form-label text-slate small" id="damageNoteLabel">Keluhan Kerusakan (wajib)</label>
                    <textarea id="damageNote" class="form-control" rows="2" placeholder="mis. Layar LCD retak, lensa berjamur..." data-testid="input-damage-note"></textarea>
                </div>
                <div class="mb-3" id="sisaWrap" style="display:none;" data-testid="sisa-wrap">
                    <label class="form-label text-slate small" id="sisaLabel">Sisa stok yang kembali</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" id="sisaInput" class="form-control" placeholder="mis. 250" data-testid="input-sisa">
                        <span class="input-group-text" id="sisaUnit">unit</span>
                    </div>
                    <div class="form-text" id="sisaHint">Alat berstok — isi berapa yang kembali. Kosong (0) berarti habis pakai.</div>
                </div>
                <div class="alert alert-dark d-flex align-items-center gap-2 py-2" id="lostValueHint" style="display:none;" data-testid="lost-value-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Nilai aset akan ditampilkan otomatis (harga dulu &amp; nilai sekarang) setelah QR di-scan, untuk acuan ganti rugi.</span>
                </div>
                <div class="hint-box no-print" style="margin-bottom:12px;">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <div>Buka halaman ini di <strong>HP</strong> untuk pindai pakai kamera, atau hubungkan <strong>alat pemindai QR (2D scanner USB/Bluetooth)</strong> ke komputer — cukup arahkan kursor ke kolom di bawah lalu tembak QR-nya.</div>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="manualBarcode" class="form-control" autofocus placeholder="Ketik atau tembak QR di sini..." data-testid="input-manual-barcode">
                    <button class="btn btn-amber" id="btnManualScan" type="button" data-testid="btn-manual-scan"><i class="fa-solid fa-check"></i></button>
                </div>
                <div class="scanner-log" id="scannerLog" data-testid="scanner-log">
                    <div class="line info">Pilih kondisi, lalu scan QR alat yang kembali.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-sb">
            <div class="card-title">Progress Alat (<?= count($items) ?>)</div>
            <div id="itemList" data-testid="item-progress-list">
                <?php foreach ($items as $it): 
                    $done = in_array($it['item_status'], ['ReturnedGood','ReturnedDamaged','ReturnedLost','InRepair','Restored']);
                    $bad  = in_array($it['item_status'], ['ReturnedDamaged','InRepair']);
                    $lost = $it['item_status'] === 'ReturnedLost';
                    ?>
                    <div class="item-progress <?= $done ? ($lost ? 'lost' : ($bad ? 'damaged' : 'done')) : '' ?>" data-barcode="<?= e($it['barcode']) ?>" data-purchase-price="<?= e($it['purchase_price'] ?? '') ?>" data-current-value="<?= e($it['current_value'] ?? '') ?>" data-testid="item-<?= (int)$it['id'] ?>">
                        <div>
                            <div class="fw-semibold"><?= e($it['asset_name']) ?></div>
                            <div class="text-slate small text-mono"><?= e($it['bmn_number']) ?></div>
                            <div class="text-slate small">Dulu: <?= fmt_rupiah($it['purchase_price'] ?? null) ?> · Sekarang: <?= fmt_rupiah($it['current_value'] ?? null) ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($it['item_status'] === 'CheckedOut'): ?>
                                <span class="badge bg-warning text-dark">Menunggu</span>
                            <?php elseif ($lost): ?>
                                <span class="badge bg-dark">Hilang</span>
                            <?php elseif ($bad): ?>
                                <span class="badge bg-danger">Rusak</span>
                            <?php elseif ($done): ?>
                                <span class="badge bg-success">Baik</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= e($it['item_status']) ?></span>
                            <?php endif; ?>
                            <?php if ($done && Auth::hasRole('superadmin')): ?>
                                <form method="POST" action="<?= BASE_PATH ?>/sa/checkin-item/<?= (int)$it['id'] ?>/undo"
                                      data-confirm="Batalkan pengembalian &quot;<?= e($it['asset_name']) ?>&quot;? Alat kembali berstatus Dipinjam dan tiket perbaikannya (bila ada) dihapus.">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan pengembalian (Super Admin)" data-testid="btn-undo-checkin-<?= (int)$it['id'] ?>"><i class="fa-solid fa-rotate-left"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <?php
        $borrowedTitle = 'Alat yang Masih Dipinjam Orang Ini';
        $borrowedSubtitle = 'Alat dari peminjaman LAIN yang masih dipegang penanggung jawab / personel peminjaman ini dan belum dikembalikan.';
        $borrowedEmpty = 'Tidak ada tanggungan alat lain dari penanggung jawab maupun personel peminjaman ini.';
        include APP_ROOT . '/views/partials/borrowed_items_card.php';
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"
    onerror="this.onerror=null;var s=document.createElement('script');s.src='https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';s.onerror=function(){var s2=document.createElement('script');s2.src='https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';document.head.appendChild(s2);};document.head.appendChild(s);"></script>
<script src="<?= asset_url("/assets/js/scanner.js") ?>"></script>
<script>
const loanId = <?= (int)$loan['id'] ?>;
const csrf = "<?= e(Auth::csrfToken()) ?>";
// Peta barcode -> info stok, untuk mendeteksi alat berstok saat di-scan.
const stockMap = {};
<?php foreach ($items as $it): if (!empty($it['unit'])): ?>
stockMap["<?= e(strtoupper((string)$it['barcode'])) ?>"] = { unit: "<?= e($it['unit']) ?>", qty: <?= (float)$it['qty_current'] ?> };
<?php endif; endforeach; ?>
// Barcode bisa berupa awalan BMN- atau BMD-; cek keduanya.
function stockInfo(code) {
    const c = (code || '').trim().toUpperCase();
    if (stockMap[c]) return stockMap[c];
    if (c.startsWith('BMN-')) return stockMap['BMD-' + c.slice(4)] || null;
    if (c.startsWith('BMD-')) return stockMap['BMN-' + c.slice(4)] || null;
    return null;
}
let pendingStock = null; // {code, info} menunggu input sisa
function showSisa(info) {
    document.getElementById('sisaUnit').textContent = info.unit;
    document.getElementById('sisaHint').textContent = 'Stok saat keluar: ' + info.qty + ' ' + info.unit + '. Isi berapa yang kembali — 0 berarti habis.';
    document.getElementById('sisaInput').max = info.qty;
    document.getElementById('sisaWrap').style.display = '';
    document.getElementById('sisaInput').focus();
}
function hideSisa() { document.getElementById('sisaWrap').style.display = 'none'; document.getElementById('sisaInput').value = ''; pendingStock = null; }
const logEl = document.getElementById('scannerLog');
function log(msg, type) { const d = document.createElement('div'); d.className = 'line ' + type; d.textContent = new Date().toLocaleTimeString() + ' · ' + msg; logEl.prepend(d); }

document.querySelectorAll('input[name=cond]').forEach(r => r.addEventListener('change', () => {
    const cond = document.querySelector('input[name=cond]:checked').value;
    document.getElementById('damageNoteWrap').style.display = (cond === 'Damaged' || cond === 'Lost') ? '' : 'none';
    document.getElementById('damageNoteLabel').textContent = cond === 'Lost' ? 'Keterangan Kehilangan (wajib)' : 'Keluhan Kerusakan (wajib)';
    document.getElementById('damageNote').placeholder = cond === 'Lost' ? 'mis. Tidak ditemukan saat acara selesai, tertinggal di lokasi...' : 'mis. Layar LCD retak, lensa berjamur...';
    document.getElementById('lostValueHint').style.display = (cond === 'Lost') ? '' : 'none';
}));

async function submitScan(code) {
    // Alat berstok: begitu di-scan, munculkan kolom Sisa dulu. Baru kirim setelah diisi.
    const info = stockInfo(code);
    const sisaVal = document.getElementById('sisaInput').value.trim();
    if (info && (!pendingStock || pendingStock.code !== code)) {
        pendingStock = { code: code, info: info };
        showSisa(info);
        log('Alat berstok: ' + code + ' — isi sisa ' + info.unit + ' lalu tekan simpan.', 'info');
        return;
    }
    if (info && sisaVal === '') { toast('Isi sisa ' + info.unit + ' lalu tekan simpan.', 'error'); document.getElementById('sisaInput').focus(); return; }

    const cond = document.querySelector('input[name=cond]:checked').value;
    const note = document.getElementById('damageNote').value.trim();
    if (!info && cond === 'Damaged' && !note) { toast('Keluhan kerusakan wajib diisi.', 'error'); return; }
    if (!info && cond === 'Lost' && !note) { toast('Keterangan kehilangan wajib diisi.', 'error'); return; }
    const f = new FormData();
    f.append('loan_id', loanId); f.append('barcode', code); f.append('condition', cond); f.append('damage_note', note); f.append('_csrf', csrf);
    if (info) f.append('sisa', sisaVal);
    try {
        const r = await fetch((window.BASE_PATH || '') + '/checkin/scan', { method: 'POST', body: f, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
            log('OK · ' + j.message, 'ok');
            const row = document.querySelector(`[data-barcode="${CSS.escape(code)}"]`);
            if (row) {
                row.classList.remove('done','damaged','lost');
                if (j.stock) {
                    row.classList.add(j.habis ? 'damaged' : 'done');
                    row.querySelector('div:last-child').innerHTML = j.habis
                        ? '<span class="badge bg-dark">Habis</span>'
                        : '<span class="badge bg-info text-dark">Stok diperbarui</span>';
                } else {
                    const cls = j.condition === 'Good' ? 'done' : (j.condition === 'Lost' ? 'lost' : 'damaged');
                    row.classList.add(cls);
                    const badge = j.condition === 'Good' ? '<span class="badge bg-success">Baik</span>' : (j.condition === 'Lost' ? '<span class="badge bg-dark">Hilang</span>' : '<span class="badge bg-danger">Rusak</span>');
                    row.querySelector('div:last-child').innerHTML = badge;
                }
            }
            if (j.stock) {
                toast(j.message, j.habis ? 'error' : 'success');
                hideSisa();
            } else if (j.condition === 'Lost') {
                toast('Hilang: ' + j.asset_name + ' — Harga dulu ' + j.purchase_price_fmt + ', nilai sekarang ' + j.current_value_fmt, 'error');
            } else {
                toast(j.condition === 'Good' ? 'Kembali baik: ' + j.asset_name : 'Rusak: ' + j.asset_name + ' — SPK akan dicetak', j.condition === 'Good' ? 'success' : 'error');
            }
            document.getElementById('damageNote').value = '';
        } else { log('GAGAL · ' + j.message, 'err'); toast(j.message, 'error'); }
    } catch (e) { log('ERROR · ' + e.message, 'err'); }
}

document.getElementById('btnManualScan').addEventListener('click', () => {
    const v = document.getElementById('manualBarcode').value.trim();
    if (v) { submitScan(v); document.getElementById('manualBarcode').value = ''; }
    // Kolom barcode kosong tapi ada alat berstok menunggu sisa -> kirim ulang.
    else if (pendingStock) { submitScan(pendingStock.code); }
    document.getElementById('manualBarcode').focus();
});
document.getElementById('manualBarcode').addEventListener('keydown', ev => {
    if (ev.key === 'Enter') { ev.preventDefault(); document.getElementById('btnManualScan').click(); }
});
// Enter di kolom Sisa -> kirim pengembalian alat berstok yang menunggu.
document.getElementById('sisaInput').addEventListener('keydown', ev => {
    if (ev.key === 'Enter') { ev.preventDefault(); if (pendingStock) submitScan(pendingStock.code); }
});
document.getElementById('manualBarcode').focus();

(async () => {
    try {
        const sc = new BarcodeScanner('reader', code => submitScan(code));
        await sc.start();
        log('Kamera aktif. Pilih kondisi lalu scan alat.', 'info');
    } catch (e) { log('Kamera tidak dapat diaktifkan: ' + e.message + '. Gunakan input manual.', 'err'); }
})();
</script>
