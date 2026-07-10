<div class="page-header">
    <div>
        <h1>Pengembalian <span class="text-mono text-slate" style="font-size:16px;">— <?= e($loan['loan_code']) ?></span></h1>
        <p class="subtitle">Pemohon: <strong><?= e($loan['requester_name']) ?></strong> · Acara: <?= e($loan['event_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/checkin" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        <form method="POST" action="<?= BASE_PATH ?>/checkin/<?= (int)$loan['id'] ?>/finalize" data-confirm="Selesaikan pengembalian?">
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
                <div class="alert alert-dark d-flex align-items-center gap-2 py-2" id="lostValueHint" style="display:none;" data-testid="lost-value-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Nilai aset akan ditampilkan otomatis (harga dulu &amp; nilai sekarang) setelah barcode di-scan, untuk acuan ganti rugi.</span>
                </div>
                <div class="hint-box no-print" style="margin-bottom:12px;">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <div>Buka halaman ini di <strong>HP</strong> untuk pindai pakai kamera, atau hubungkan <strong>alat pemindai barcode (USB/Bluetooth)</strong> ke komputer — cukup arahkan kursor ke kolom di bawah lalu tembak barcode-nya.</div>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="manualBarcode" class="form-control" autofocus placeholder="Ketik atau tembak barcode di sini..." data-testid="input-manual-barcode">
                    <button class="btn btn-amber" id="btnManualScan" type="button" data-testid="btn-manual-scan"><i class="fa-solid fa-check"></i></button>
                </div>
                <div class="scanner-log" id="scannerLog" data-testid="scanner-log">
                    <div class="line info">Pilih kondisi, lalu scan barcode alat yang kembali.</div>
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
                        <div>
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"
    onerror="this.onerror=null;var s=document.createElement('script');s.src='https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';s.onerror=function(){var s2=document.createElement('script');s2.src='https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';document.head.appendChild(s2);};document.head.appendChild(s);"></script>
<script src="<?= ASSET_PREFIX ?>/assets/js/scanner.js"></script>
<script>
const loanId = <?= (int)$loan['id'] ?>;
const csrf = "<?= e(Auth::csrfToken()) ?>";
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
    const cond = document.querySelector('input[name=cond]:checked').value;
    const note = document.getElementById('damageNote').value.trim();
    if (cond === 'Damaged' && !note) { toast('Keluhan kerusakan wajib diisi.', 'error'); return; }
    if (cond === 'Lost' && !note) { toast('Keterangan kehilangan wajib diisi.', 'error'); return; }
    const f = new FormData();
    f.append('loan_id', loanId); f.append('barcode', code); f.append('condition', cond); f.append('damage_note', note); f.append('_csrf', csrf);
    try {
        const r = await fetch((window.BASE_PATH || '') + '/checkin/scan', { method: 'POST', body: f, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
            log('OK · ' + j.message, 'ok');
            const row = document.querySelector(`[data-barcode="${CSS.escape(code)}"]`);
            if (row) {
                row.classList.remove('done','damaged','lost');
                const cls = j.condition === 'Good' ? 'done' : (j.condition === 'Lost' ? 'lost' : 'damaged');
                row.classList.add(cls);
                const badge = j.condition === 'Good' ? '<span class="badge bg-success">Baik</span>' : (j.condition === 'Lost' ? '<span class="badge bg-dark">Hilang</span>' : '<span class="badge bg-danger">Rusak</span>');
                row.querySelector('div:last-child').innerHTML = badge;
            }
            if (j.condition === 'Lost') {
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
    document.getElementById('manualBarcode').focus();
});
document.getElementById('manualBarcode').addEventListener('keydown', ev => {
    if (ev.key === 'Enter') { ev.preventDefault(); document.getElementById('btnManualScan').click(); }
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
