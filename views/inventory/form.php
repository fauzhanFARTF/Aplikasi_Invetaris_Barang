<?php $isEdit = !empty($asset); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah Alat' : 'Tambah Alat Baru' ?></h1>
        <p class="subtitle">Registrasi aset streaming BMN.</p>
        <?php if ($isEdit): ?><?= audit_trail_info($asset) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_PATH ?>/inventory" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/inventory/'.e($asset['uuid']).'/edit' : '/inventory/create' ?>" enctype="multipart/form-data" data-testid="asset-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="card-sb">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Foto Alat <?= $isEdit ? '' : '*' ?></label>
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <?php $currentPhotoUrl = photo_url($asset['photo'] ?? null); ?>
                    <div id="photoPreviewWrap" style="<?= $currentPhotoUrl ? '' : 'display:none;' ?>">
                        <img id="photoPreview" src="<?= e($currentPhotoUrl ?? '') ?>" alt="Foto alat" style="width:140px;height:140px;object-fit:cover;border-radius:12px;border:1px solid #E2E8F0;">
                    </div>
                    <div class="flex-grow-1" style="min-width:220px;">
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/webp" data-testid="input-photo" style="max-width:280px;">
                            <button type="button" class="btn btn-outline-navy" id="btnOpenCamera" data-testid="btn-open-camera"><i class="fa-solid fa-camera"></i> Ambil dari Kamera</button>
                        </div>
                        <div class="input-group mt-2" style="max-width:420px;">
                            <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                            <input type="url" name="photo_url" id="photoUrl" class="form-control" placeholder="atau tempel link foto / Google Drive" data-testid="input-photo-url">
                        </div>
                        <div class="form-text">JPG, PNG, atau WEBP, maks 3MB. Bisa unggah file, ambil dari kamera, <strong>atau</strong> tempel link foto (mis. Google Drive yang dibagikan publik). Opsional — jika kosong, dipakai logo Diskominfo.</div>
                        <?php if ($isEdit && $currentPhotoUrl): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhotoCheck" data-testid="input-remove-photo">
                                <label class="form-check-label" for="removePhotoCheck">Hapus foto saat ini</label>
                            </div>
                        <?php endif; ?>

                        <div id="cameraPanel" style="display:none;" class="mt-3 p-2 border rounded-3" data-testid="camera-panel">
                            <video id="cameraVideo" autoplay playsinline muted style="width:100%;max-width:320px;border-radius:8px;background:#000;"></video>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-primary btn-sm" id="btnCapturePhoto" data-testid="btn-capture-photo"><i class="fa-solid fa-circle-dot"></i> Ambil Foto</button>
                                <button type="button" class="btn btn-outline-navy btn-sm" id="btnCloseCamera" data-testid="btn-close-camera">Batal</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Kategori <?= $isEdit ? '' : '*' ?></label>
                <select name="category_id" id="categorySelect" class="form-select" <?= $isEdit ? '' : 'required' ?> data-testid="input-category">
                    <option value="">— Pilih —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-prefix="<?= e($c['code_prefix'] ?? '') ?>" <?= ($asset['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= !empty($c['code_prefix']) ? ' ('.e($c['code_prefix']).')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$isEdit): ?><div class="form-text">Kode Aset & No. BMD dibuat otomatis dari kode singkatan kategori.</div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Kode Aset <?= $isEdit ? '*' : '' ?></label>
                <input type="text" name="asset_code" id="assetCodeField" class="form-control<?= $isEdit ? '' : ' bg-light' ?>"
                       <?= $isEdit ? 'required' : 'readonly' ?> value="<?= e($asset['asset_code'] ?? '') ?>"
                       placeholder="<?= $isEdit ? 'mis. CAM-004' : 'otomatis dari kategori' ?>" data-testid="input-code">
                <div class="form-text"><?= $isEdit ? 'Kode internal, unik.' : 'Dibuat otomatis, tidak perlu diisi.' ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nomor BMD <?= $isEdit ? '*' : '' ?></label>
                <input type="text" name="bmn_number" id="bmnField" class="form-control<?= $isEdit ? '' : ' bg-light' ?>"
                       <?= $isEdit ? 'required' : 'readonly' ?> value="<?= e($asset['bmn_number'] ?? '') ?>"
                       placeholder="<?= $isEdit ? 'mis. BMD-2024-KMR-004' : 'otomatis dari kategori' ?>" data-testid="input-bmn">
            </div>
            <?php if ($isEdit): ?>
            <div class="col-md-4">
                <label class="form-label">Nilai QR Code</label>
                <input type="text" name="barcode" class="form-control" value="<?= e($asset['barcode'] ?? '') ?>" placeholder="Kosongkan → sama dengan No. BMD" data-testid="input-barcode">
            </div>
            <?php endif; ?>
            <div class="col-md-<?= $isEdit ? '8' : '12' ?>">
                <label class="form-label">Nama Alat *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($asset['name'] ?? '') ?>" data-testid="input-name">
            </div>
            <div class="col-md-4">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control" value="<?= e($asset['brand'] ?? '') ?>" data-testid="input-brand">
            </div>
            <div class="col-md-4">
                <label class="form-label">Model</label>
                <input type="text" name="model" class="form-control" value="<?= e($asset['model'] ?? '') ?>" data-testid="input-model">
            </div>
            <div class="col-md-4">
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_number" class="form-control" value="<?= e($asset['serial_number'] ?? '') ?>" data-testid="input-serial">
            </div>
        </div>
    </div>

    <div class="card-sb mt-3">
        <h6 class="mb-3"><i class="fa-solid fa-money-bill-wave"></i> Informasi Harga</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Harga Perolehan (Harga Dulu)</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="<?= e($asset['purchase_price'] ?? '') ?>" placeholder="mis. 15000000" data-testid="input-purchase-price">
                </div>
                <div class="form-text">Harga saat aset pertama kali dibeli/diperoleh.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tanggal Perolehan</label>
                <input type="date" name="purchase_date" class="form-control" value="<?= e($asset['purchase_date'] ?? '') ?>" data-testid="input-purchase-date">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nilai Sekarang</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" step="0.01" min="0" name="current_value" class="form-control" value="<?= e($asset['current_value'] ?? '') ?>" placeholder="mis. 9000000" data-testid="input-current-value">
                </div>
                <div class="form-text">Estimasi nilai buku / nilai wajar aset saat ini (setelah penyusutan).</div>
            </div>
            <div class="col-md-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_consumable" value="1" id="isConsumable" <?= !empty($asset['is_consumable']) ? 'checked' : '' ?> data-testid="input-consumable">
                    <label class="form-check-label" for="isConsumable"><strong>Barang habis pakai</strong></label>
                </div>
                <div class="form-text">Centang bila alat ini bersifat habis pakai (mis. kabel, konektor). Saat diserahkan ke OPD, barang habis pakai dianggap tuntas dan <strong>tidak ditunggu kembali</strong>.</div>
            </div>
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary" data-testid="btn-save-asset"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
    </div>
</form>

<script src="<?= asset_url("/assets/js/photo-capture.js") ?>"></script>
<script>
    initPhotoCapture({
        inputId: 'photoInput', previewWrapId: 'photoPreviewWrap', previewImgId: 'photoPreview',
        removeCheckId: 'removePhotoCheck', openBtnId: 'btnOpenCamera', captureBtnId: 'btnCapturePhoto',
        closeBtnId: 'btnCloseCamera', panelId: 'cameraPanel', videoId: 'cameraVideo',
        facingMode: 'environment', // foto alat -> kamera belakang
    });
</script>
<?php if (!$isEdit): ?>
<script>
    // Isi otomatis Kode Aset & No. BMD dari kode singkatan kategori terpilih.
    (function () {
        var sel = document.getElementById('categorySelect');
        var codeF = document.getElementById('assetCodeField');
        var bmnF = document.getElementById('bmnField');
        if (!sel || !codeF || !bmnF) return;
        function refresh() {
            var cid = sel.value;
            if (!cid) { codeF.value = ''; bmnF.value = ''; return; }
            codeF.value = 'memuat…'; bmnF.value = 'memuat…';
            fetch('<?= BASE_PATH ?>/ajax/next-asset-code?category_id=' + encodeURIComponent(cid), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) { codeF.value = d.asset_code; bmnF.value = d.bmn_number; }
                    else { codeF.value = ''; bmnF.value = ''; alert(d && d.message ? d.message : 'Gagal membuat kode aset.'); }
                })
                .catch(function () { codeF.value = ''; bmnF.value = ''; });
        }
        sel.addEventListener('change', refresh);
        if (sel.value) refresh();
    })();
</script>
<?php endif; ?>
