<?php $isEdit = !empty($user); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah User' : 'Tambah User' ?></h1>
        <?php if ($isEdit): ?><?= audit_trail_info($user) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_PATH ?>/users" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/users/'.(int)$user['id'].'/edit' : '/users/create' ?>" enctype="multipart/form-data" data-testid="user-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="card-sb">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Foto User</label>
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <?php $currentPhotoUrl = photo_url($user['photo'] ?? null, 'users'); ?>
                    <div id="photoPreviewWrap" style="<?= $currentPhotoUrl ? '' : 'display:none;' ?>">
                        <img id="photoPreview" src="<?= e($currentPhotoUrl ?? '') ?>" alt="Foto user" style="width:140px;height:140px;object-fit:cover;border-radius:12px;border:1px solid #E2E8F0;">
                    </div>
                    <div class="flex-grow-1" style="min-width:220px;">
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/webp" data-testid="input-photo" style="max-width:280px;">
                            <button type="button" class="btn btn-outline-navy" id="btnOpenCamera" data-testid="btn-open-camera"><i class="fa-solid fa-camera"></i> Ambil dari Kamera</button>
                        </div>
                        <div class="form-text">JPG, PNG, atau WEBP. Maksimal 3MB.</div>
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
            <div class="col-md-6"><label class="form-label">Nama Lengkap *</label><input type="text" name="name" required class="form-control" value="<?= e($user['name'] ?? '') ?>" data-testid="input-name"></div>
            <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" required class="form-control" value="<?= e($user['email'] ?? '') ?>" data-testid="input-email"></div>
            <div class="col-md-4">
                <label class="form-label">Role *</label>
                <select name="role" class="form-select" required data-testid="input-role">
                    <option value="">— Pilih —</option>
                    <?php foreach (['admin','pemohon','supervisor','admin_gudang'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($user['role'] ?? '') === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Unit Kerja</label><input type="text" name="unit_kerja" class="form-control" value="<?= e($user['unit_kerja'] ?? '') ?>"></div>
            <div class="col-md-6">
                <label class="form-label">Password <?= $isEdit ? '(kosongkan jika tidak diubah)' : '*' ?></label>
                <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?> data-testid="input-password">
            </div>
        </div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary" data-testid="btn-save-user"><i class="fa-solid fa-floppy-disk"></i> Simpan</button></div>
</form>

<script src="<?= ASSET_PREFIX ?>/assets/js/photo-capture.js"></script>
<script>
    initPhotoCapture({
        inputId: 'photoInput', previewWrapId: 'photoPreviewWrap', previewImgId: 'photoPreview',
        removeCheckId: 'removePhotoCheck', openBtnId: 'btnOpenCamera', captureBtnId: 'btnCapturePhoto',
        closeBtnId: 'btnCloseCamera', panelId: 'cameraPanel', videoId: 'cameraVideo',
        facingMode: 'user', // foto orang -> kamera depan
    });
</script>
