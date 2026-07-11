<?php $isEdit = !empty($package); ?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Ubah Paket' : 'Tambah Paket' ?></h1>
        <p class="subtitle">Kumpulan alat yang bisa dipinjam sekaligus.</p>
        <?php if ($isEdit): ?><?= audit_trail_info($package) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_PATH ?>/packages" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= $isEdit ? '/packages/'.(int)$package['id'].'/edit' : '/packages/create' ?>" data-testid="package-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card-sb">
                <div class="mb-3"><label class="form-label">Nama Paket *</label><input type="text" name="name" class="form-control" required value="<?= e($package['name'] ?? '') ?>" data-testid="input-name"></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="4" data-testid="input-desc"><?= e($package['description'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card-sb">
                <div class="card-title">Pilih Alat</div>
                <div class="d-flex gap-2 mb-2">
                    <select id="pkgCategoryFilter" class="form-select form-select-sm" style="max-width:180px;">
                        <option value="">— Semua Kategori —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="search" id="pkgSearch" class="form-control form-control-sm" placeholder="Cari alat...">
                </div>
                <div style="max-height:420px;overflow-y:auto;">
                    <?php foreach ($assets as $a): ?>
                        <label class="d-flex gap-2 align-items-center p-2 border-bottom asset-row" data-name="<?= e(strtolower($a['name'].' '.$a['bmn_number'].' '.($a['category_name'] ?? ''))) ?>" data-category="<?= (int)($a['category_id'] ?? 0) ?>">
                            <input type="checkbox" name="asset_ids[]" value="<?= (int)$a['id'] ?>" class="form-check-input" <?= in_array((int)$a['id'], $selectedIds ?? [], true) ? 'checked' : '' ?> data-testid="pkg-asset-<?= (int)$a['id'] ?>">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= e($a['name']) ?> <span class="text-slate">— <?= e($a['category_name'] ?? '') ?></span></div>
                                <div class="text-slate small text-mono"><?= e($a['bmn_number']) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                    <div id="pkgNoResult" class="text-slate small text-center py-3" style="display:none;">Tidak ada alat yang cocok dengan pencarian.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary" data-testid="btn-save-package"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
    </div>
</form>
<script>
function applyPkgFilters() {
    const q = (document.getElementById('pkgSearch')?.value || '').trim().toLowerCase();
    const terms = q.split(/\s+/).filter(Boolean);
    const cat = document.getElementById('pkgCategoryFilter')?.value || '';
    let visibleCount = 0;
    document.querySelectorAll('.asset-row').forEach(r => {
        const matchText = terms.every(t => r.dataset.name.includes(t));
        const matchCat = !cat || r.dataset.category === cat;
        if (matchText && matchCat) {
            r.style.removeProperty('display');
            visibleCount++;
        } else {
            // .d-flex uses `!important` in Bootstrap, so hiding needs !important too
            r.style.setProperty('display', 'none', 'important');
        }
    });
    const noResult = document.getElementById('pkgNoResult');
    if (noResult) noResult.style.display = visibleCount === 0 ? '' : 'none';
}
document.getElementById('pkgSearch').addEventListener('input', applyPkgFilters);
document.getElementById('pkgCategoryFilter').addEventListener('change', applyPkgFilters);
</script>
