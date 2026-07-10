<?php $isEdit = !empty($user); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah User' : 'Tambah User' ?></h1>
    </div>
    <a href="<?= BASE_PATH ?>/users" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/users/'.(int)$user['id'].'/edit' : '/users/create' ?>" data-testid="user-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="card-sb">
        <div class="row g-3">
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
