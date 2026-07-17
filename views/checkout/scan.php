<div class="page-header">
    <div>
        <h1>Scan Penyerahan <span class="text-mono text-slate" style="font-size:16px;">— <?= e($loan['loan_code']) ?></span></h1>
        <p class="subtitle">Pemohon: <strong><?= e($loan['requester_name']) ?></strong> · Acara: <?= e($loan['event_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/checkout" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        <form method="POST" action="<?= BASE_PATH ?>/checkout/<?= e($loan["uuid"]) ?>/finalize" data-confirm="Selesaikan penyerahan untuk peminjaman ini?">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn btn-primary" data-testid="btn-finalize-checkout"><i class="fa-solid fa-flag-checkered"></i> Selesai Penyerahan</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="scanner-wrap">
            <div class="scanner-video"><div id="reader"></div></div>
            <div>
                <div class="hint-box no-print" style="margin-bottom:12px;">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <div>Buka halaman ini di <strong>HP</strong> untuk pindai pakai kamera, atau hubungkan <strong>alat pemindai QR (2D scanner USB/Bluetooth)</strong> ke komputer — cukup arahkan kursor ke kolom di bawah lalu tembak QR-nya.</div>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="manualBarcode" class="form-control" autofocus placeholder="Ketik atau tembak QR di sini..." data-testid="input-manual-barcode">
                    <button class="btn btn-amber" id="btnManualScan" type="button" data-testid="btn-manual-scan"><i class="fa-solid fa-check"></i></button>
                </div>
                <div class="scanner-log" id="scannerLog" data-testid="scanner-log">
                    <div class="line info">Arahkan kamera ke QR code pada alat. Sistem otomatis memproses penyerahan setiap alat yang terdaftar dalam peminjaman ini.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-sb">
            <div class="card-title">Progress Alat (<?= count($items) ?>)</div>
            <div id="itemList" data-testid="item-progress-list">
                <?php foreach ($items as $it): $itPhoto = asset_photo_url($it['photo'] ?? null); ?>
                    <div class="item-progress <?= $it['item_status'] === 'CheckedOut' ? 'done' : '' ?>" data-barcode="<?= e($it['barcode']) ?>" data-testid="item-<?= (int)$it['id'] ?>">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e($itPhoto) ?>" alt="Foto <?= e($it['asset_name']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0;background:#fff;">
                            <div>
                                <div class="fw-semibold"><?= e($it['asset_name']) ?></div>
                                <div class="text-slate small text-mono"><?= e($it['bmn_number']) ?></div>
                            </div>
                        </div>
                        <div>
                            <?php if ($it['item_status'] === 'CheckedOut'): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-check"></i> Sudah</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Belum</span>
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
<script src="<?= asset_url("/assets/js/scanner.js") ?>"></script>
<script>
const loanId = <?= (int)$loan['id'] ?>;
const csrf = "<?= e(Auth::csrfToken()) ?>";
const logEl = document.getElementById('scannerLog');
function log(msg, type) {
    const d = document.createElement('div');
    d.className = 'line ' + type;
    d.textContent = new Date().toLocaleTimeString() + ' · ' + msg;
    logEl.prepend(d);
}

async function submitScan(code) {
    try {
        const f = new FormData();
        f.append('loan_id', loanId);
        f.append('barcode', code);
        f.append('_csrf', csrf);
        const r = await fetch((window.BASE_PATH || '') + '/checkout/scan', { method: 'POST', body: f, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
            log('OK · ' + j.message, 'ok');
            const row = document.querySelector(`[data-barcode="${CSS.escape(code)}"]`);
            if (row) {
                row.classList.add('done');
                row.querySelector('div:last-child').innerHTML = '<span class="badge bg-success"><i class="fa-solid fa-check"></i> Sudah</span>';
            }
            toast('Penyerahan: ' + j.asset_name, 'success');
        } else {
            log('GAGAL · ' + j.message, 'err');
            toast(j.message, 'error');
        }
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
        log('Kamera aktif. Siap memindai.', 'info');
    } catch (e) { log('Kamera tidak dapat diaktifkan: ' + e.message + '. Silakan gunakan input manual.', 'err'); }
})();
</script>
