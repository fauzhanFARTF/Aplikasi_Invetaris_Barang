<?php $isEdit = !empty($category); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah Kategori' : 'Tambah Kategori' ?></h1>
        <p class="subtitle">Grouping alat berdasarkan jenis untuk memudahkan pencarian.</p>
        <?php if ($isEdit): ?><?= audit_trail_info($category) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_PATH ?>/categories" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/categories/'.(int)$category['id'].'/edit' : '/categories/create' ?>" data-testid="category-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="card-sb">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nama Kategori *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($category['name'] ?? '') ?>" placeholder="mis. Kamera Video" data-testid="input-cat-name">
                <div class="form-text">Nama harus unik.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Deskripsi</label>
                <input type="text" name="description" class="form-control" value="<?= e($category['description'] ?? '') ?>" placeholder="mis. Kamera video / camcorder / DSLR" data-testid="input-cat-desc">
            </div>
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary" data-testid="btn-save-category"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
    </div>
</form>
