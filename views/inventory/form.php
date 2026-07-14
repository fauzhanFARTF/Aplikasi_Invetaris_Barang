<?php $isEdit = !empty($asset); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah Alat' : 'Tambah Alat Baru' ?></h1>
        <p class="subtitle">Registrasi aset streaming BMN.</p>
        <?php if ($isEdit): ?><?= audit_trail_info($asset) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_PATH ?>/inventory" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/inventory/'.(int)$asset['id'].'/edit' : '/inventory/create' ?>" enctype="multipart/form-data" data-testid="asset-form">
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
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/webp" data-testid="input-photo" style="max-width:280px;" <?= $isEdit ? '' : 'required' ?>>
                            <button type="button" class="btn btn-outline-navy" id="btnOpenCamera" data-testid="btn-open-camera"><i class="fa-solid fa-camera"></i> Ambil dari Kamera</button>
                        </div>
                        <div class="form-text">JPG, PNG, atau WEBP. Maksimal 3MB.<?= $isEdit ? '' : ' <span class="text-danger">Wajib diisi.</span>' ?></div>
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
                <label class="form-label">Kode Aset *</label>
                <input type="text" name="asset_code" class="form-control" required value="<?= e($asset['asset_code'] ?? '') ?>" placeholder="mis. CAM-004" data-testid="input-code">
                <div class="form-text">Kode internal, unik.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nomor BMN *</label>
                <input type="text" name="bmn_number" class="form-control" required value="<?= e($asset['bmn_number'] ?? '') ?>" placeholder="mis. BMN-2024-KMR-004" data-testid="input-bmn">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nilai QR Code</label>
                <input type="text" name="barcode" class="form-control" value="<?= e($asset['barcode'] ?? '') ?>" placeholder="Kosongkan → sama dengan No. BMN" data-testid="input-barcode">
            </div>
            <div class="col-md-8">
                <label class="form-label">Nama Alat *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($asset['name'] ?? '') ?>" data-testid="input-name">
            </div>
            <div class="col-md-4">
                <label class="form-label">Kategori</label>
                <select name="category_id" class="form-select" data-testid="input-category">
                    <option value="">— Pilih —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($asset['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
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
            <div class="col-12">
                <label class="form-label">Catatan Kondisi</label>
                <textarea name="condition_note" class="form-control" rows="3" data-testid="input-note"><?= e($asset['condition_note'] ?? '') ?></textarea>
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
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary" data-testid="btn-save-asset"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
    </div>
</form>

<script src="<?= ASSET_PREFIX ?>/assets/js/photo-capture.js"></script>
<script>
    initPhotoCapture({
        inputId: 'photoInput', previewWrapId: 'photoPreviewWrap', previewImgId: 'photoPreview',
        removeCheckId: 'removePhotoCheck', openBtnId: 'btnOpenCamera', captureBtnId: 'btnCapturePhoto',
        closeBtnId: 'btnCloseCamera', panelId: 'cameraPanel', videoId: 'cameraVideo',
        facingMode: 'environment', // foto alat -> kamera belakang
    });
</script>
