<?php $profilePhotoUrl = photo_url($user['photo'] ?? null, 'users'); ?>
<div class="page-header">
    <div>
        <h1>Profil Saya</h1>
        <p class="subtitle">Kelola foto profil dan password Anda di sini.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card-sb">
            <div class="card-title">Informasi Akun</div>
            <div class="d-flex align-items-center gap-3 mb-3">
                <img src="<?= e(user_avatar_url($user['photo'] ?? null)) ?>" alt="Foto profil" style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:1px solid #E2E8F0;background:#fff;">
                <div class="min-w-0">
                    <div class="fw-semibold" style="word-break:break-word;"><?= e($user['name']) ?></div>
                    <div class="text-slate small"><?= e(role_label($user['role'])) ?></div>
                </div>
            </div>
            <table class="table table-sm mb-0" style="table-layout: fixed; width: 100%;">
                <tr><td class="text-slate" style="width:38%;">Email</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['email']) ?></td></tr>
                <tr><td class="text-slate">Unit Kerja</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['unit_kerja'] ?: '—') ?></td></tr>
                <tr><td class="text-slate">Telepon</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['phone'] ?: '—') ?></td></tr>
            </table>
        </div>

        <div class="card-sb mt-3">
            <div class="card-title">Foto Profil</div>
            <form method="POST" action="<?= BASE_PATH ?>/profile/photo" enctype="multipart/form-data" data-testid="profile-photo-form">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <div id="photoPreviewWrap" style="<?= $profilePhotoUrl ? '' : 'display:none;' ?>">
                        <img id="photoPreview" src="<?= e($profilePhotoUrl ?? '') ?>" alt="Pratinjau foto" style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #E2E8F0;">
                    </div>
                    <div class="flex-grow-1" style="min-width:220px;">
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/webp" data-testid="input-photo" style="max-width:260px;">
                            <button type="button" class="btn btn-outline-navy" id="btnOpenCamera" data-testid="btn-open-camera"><i class="fa-solid fa-camera"></i> Ambil dari Kamera</button>
                        </div>
                        <div class="form-text">JPG, PNG, atau WEBP. Maksimal 3MB.</div>
                        <?php if ($profilePhotoUrl): ?>
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
                <div class="mt-3"><button class="btn btn-primary" data-testid="btn-save-photo"><i class="fa-solid fa-floppy-disk"></i> Simpan Foto</button></div>
            </form>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card-sb">
            <div class="card-title">Ganti Password</div>
            <form method="POST" action="<?= BASE_PATH ?>/profile" data-testid="profile-form">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <div class="mb-3"><label class="form-label">Password Lama *</label><input type="password" name="old_password" class="form-control" required data-testid="input-old-password"></div>
                <div class="mb-3"><label class="form-label">Password Baru *</label><input type="password" name="new_password" class="form-control" required minlength="6" data-testid="input-new-password"></div>
                <button class="btn btn-primary" data-testid="btn-save-profile"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url("/assets/js/photo-capture.js") ?>"></script>
<script>
    initPhotoCapture({
        inputId: 'photoInput', previewWrapId: 'photoPreviewWrap', previewImgId: 'photoPreview',
        removeCheckId: 'removePhotoCheck', openBtnId: 'btnOpenCamera', captureBtnId: 'btnCapturePhoto',
        closeBtnId: 'btnCloseCamera', panelId: 'cameraPanel', videoId: 'cameraVideo',
        facingMode: 'user', // foto profil -> kamera depan
    });
</script>
