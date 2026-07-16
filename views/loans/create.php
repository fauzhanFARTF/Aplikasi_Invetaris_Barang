<div class="page-header">
    <div>
        <h1>Ajukan Peminjaman</h1>
        <p class="subtitle">Pilih tanggal & alat/paket yang dibutuhkan untuk acara Anda.</p>
    </div>
    <a href="<?= BASE_PATH ?>/loans" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_PATH ?>/loans/create" id="loanForm" data-testid="loan-create-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card-sb">
                <div class="card-title">Rincian Acara</div>
                <div class="mb-3">
                    <label class="form-label">Nama Acara *</label>
                    <input type="text" name="event_name" class="form-control" required placeholder="mis. Live Streaming Rapat Paripurna" data-testid="input-event-name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Lokasi Acara</label>
                    <input type="text" name="event_location" class="form-control" placeholder="Gedung Smart Building, Ruang..." data-testid="input-event-location">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tujuan / Keperluan</label>
                    <textarea name="purpose" rows="3" class="form-control" placeholder="Jelaskan singkat kebutuhan penggunaan alat" data-testid="input-purpose"></textarea>
                </div>
                <?php if (!is_personal_borrower()): // pemohon murni: peminjaman pribadi tanpa personel ?>
                <div class="mb-3">
                    <label class="form-label">Personel yang Dilibatkan</label>
                    <?php if (empty($itStaff)): ?>
                        <div class="form-text">Belum ada user ber-role IT Staff. Atur <strong>IT Staff</strong> sebagai role utama atau peran tambahan user di Manajemen User agar bisa dipilih.</div>
                    <?php else: ?>
                        <div class="border rounded-3 p-2" style="max-height:180px;overflow-y:auto;" data-testid="participants-box">
                            <?php foreach ($itStaff as $st): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="participant_ids[]" value="<?= (int)$st['id'] ?>" id="part<?= (int)$st['id'] ?>" data-testid="participant-<?= (int)$st['id'] ?>">
                                    <label class="form-check-label" for="part<?= (int)$st['id'] ?>">
                                        <?= e($st['name']) ?><?php if (!empty($st['unit_kerja'])): ?> <span class="text-slate small">· <?= e($st['unit_kerja']) ?></span><?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Hanya personel ber-role IT Staff yang dapat dilibatkan — termasuk user yang memegang IT Staff sebagai peran tambahan.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Tanggal Mulai *</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required min="<?= date('Y-m-d') ?>" data-testid="input-start-date">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Tanggal Selesai *</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required min="<?= date('Y-m-d') ?>" data-testid="input-end-date">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Jam Acara</label>
                        <input type="time" name="start_time" class="form-control" data-testid="input-start-time">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-sb">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="card-title mb-0">Pilih Paket Alat</div>
                    <span class="text-slate small">Opsional — pilih preset untuk kebutuhan umum</span>
                </div>
                <div class="row g-2">
                    <?php foreach ($packages as $p): ?>
                        <div class="col-md-6">
                            <label class="d-flex gap-2 p-3 border rounded-3 h-100" style="cursor:pointer;">
                                <input type="checkbox" name="package_ids[]" value="<?= (int)$p['id'] ?>" class="form-check-input mt-1" data-testid="pkg-<?= (int)$p['id'] ?>">
                                <div>
                                    <div class="fw-semibold"><?= e($p['name']) ?></div>
                                    <div class="text-slate small mb-1"><?= e($p['description']) ?></div>
                                    <div class="text-mono small text-slate"><?= e($p['items'] ?? '—') ?></div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-sb mt-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="card-title mb-0">Pilih Alat Individual</div>
                    <div class="d-flex gap-2">
                        <select id="assetCategoryFilter" class="form-select form-select-sm" style="max-width:180px;" data-testid="asset-category-filter">
                            <option value="">— Semua Kategori —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" id="assetSearch" class="form-control form-control-sm" placeholder="Cari alat..." style="max-width:220px;" data-testid="asset-search">
                    </div>
                </div>
                <div id="assetList" style="max-height:400px; overflow-y:auto;" data-testid="asset-list">
                    <?php foreach ($assets as $a): $photoUrl = asset_photo_url($a['photo'] ?? null); ?>
                        <?php $brandModel = trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? '')); ?>
                        <label class="d-flex gap-2 align-items-center p-2 border-bottom asset-row" data-name="<?= e(strtolower($a['name'].' '.$a['asset_code'].' '.$a['bmn_number'].' '.($a['category_name'] ?? '').' '.$brandModel.' '.($a['serial_number'] ?? '').' '.($holders[$a['id']] ?? '').' '.($followers[$a['id']] ?? ''))) ?>" data-category="<?= (int)($a['category_id'] ?? 0) ?>" data-id="<?= (int)$a['id'] ?>">
                            <input type="checkbox" name="asset_ids[]" value="<?= (int)$a['id'] ?>" class="form-check-input" <?= $a['status'] !== 'Available' ? 'disabled' : '' ?> data-testid="asset-<?= (int)$a['id'] ?>">
                            <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($a['name']) ?>" class="asset-thumb rounded" style="width:52px;height:52px;object-fit:cover;border:1px solid #E2E8F0;background:#fff;flex-shrink:0;" data-testid="asset-photo-<?= (int)$a['id'] ?>">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= e($a['name']) ?></div>
                                <div class="text-slate small text-mono"><?= e($a['asset_code']) ?></div>
                                <?php if (!empty($a['category_name'])): ?><div class="text-slate small"><?= e($a['category_name']) ?></div><?php endif; ?>
                                <?php if ($brandModel !== ''): ?><div class="text-slate small"><?= e($brandModel) ?></div><?php endif; ?>
                                <?php if (!empty($a['serial_number'])): ?><div class="text-slate small text-mono">SN: <?= e($a['serial_number']) ?></div><?php endif; ?>
                            </div>
                            <div class="text-center" style="flex-shrink:0;"><?= status_badge($a['status']) ?></div>
                            <?php if (!empty($holders[$a['id']])): ?>
                                <div class="small text-slate" style="min-width:150px;max-width:210px;flex-shrink:0;">
                                    <div><span class="text-slate">Pemesan:</span> <?= e($holders[$a['id']]) ?></div>
                                    <?php if (!empty($followers[$a['id']])): ?>
                                        <div style="font-size:11px;"><span class="text-slate">Terlibat:</span> <?= e($followers[$a['id']]) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <div id="assetNoResult" class="text-slate small text-center py-3" style="display:none;">Tidak ada alat yang cocok dengan pencarian.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary" data-testid="btn-submit-loan"><i class="fa-solid fa-paper-plane"></i> Submit Pengajuan</button>
    </div>
</form>

<script>
function applyAssetFilters() {
    const q = (document.getElementById('assetSearch')?.value || '').trim().toLowerCase();
    const terms = q.split(/\s+/).filter(Boolean);
    const cat = document.getElementById('assetCategoryFilter')?.value || '';
    let visibleCount = 0;
    document.querySelectorAll('.asset-row').forEach(r => {
        const matchText = terms.every(t => r.dataset.name.includes(t));
        const matchCat = !cat || r.dataset.category === cat;
        if (matchText && matchCat) {
            // Reset any forced inline display so the row's normal (d-flex) display applies again
            r.style.removeProperty('display');
            visibleCount++;
        } else {
            // Bootstrap's .d-flex utility uses `display: flex !important;`, so a plain
            // `r.style.display = 'none'` cannot override it — must force !important here too.
            r.style.setProperty('display', 'none', 'important');
        }
    });
    const noResult = document.getElementById('assetNoResult');
    if (noResult) noResult.style.display = visibleCount === 0 ? '' : 'none';
}
document.getElementById('assetSearch')?.addEventListener('input', applyAssetFilters);
document.getElementById('assetCategoryFilter')?.addEventListener('change', applyAssetFilters);

// Refresh availability when date range changes
async function refreshAvail() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    if (!s || !e) return;
    const r = await fetch(`${window.BASE_PATH || ''}/ajax/availability?start=${s}&end=${e}`);
    const j = await r.json();
    const busy = new Set(j.busy_asset_ids || []);
    document.querySelectorAll('.asset-row').forEach(row => {
        const id = parseInt(row.dataset.id);
        const cb = row.querySelector('input[type=checkbox]');
        if (busy.has(id)) {
            cb.disabled = true; cb.checked = false;
            row.style.opacity = '0.5';
        } else {
            row.style.opacity = '';
            // Do not enable if original status was not Available - check attribute
        }
    });
}
document.getElementById('start_date').addEventListener('change', refreshAvail);
document.getElementById('end_date').addEventListener('change', refreshAvail);

// Alat yang dicentang otomatis pindah ke atas daftar (checked-first, urutan stabil).
function reorderCheckedAssets() {
    const list = document.getElementById('assetList');
    if (!list) return;
    const rows = Array.from(list.querySelectorAll('.asset-row'));
    rows.sort((a, b) => {
        const ca = a.querySelector('input[type=checkbox]').checked ? 0 : 1;
        const cb = b.querySelector('input[type=checkbox]').checked ? 0 : 1;
        return ca - cb; // Array.prototype.sort stabil -> urutan dalam tiap grup tetap
    });
    rows.forEach(r => list.appendChild(r));
}
document.getElementById('assetList')?.addEventListener('change', function (e) {
    if (e.target && e.target.matches('input[type=checkbox]')) reorderCheckedAssets();
});
</script>
