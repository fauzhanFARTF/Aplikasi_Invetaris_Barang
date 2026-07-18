<div class="page-header">
    <div>
        <h1>Ajukan Peminjaman</h1>
        <p class="subtitle">Pilih tanggal & alat/paket yang dibutuhkan untuk acara Anda.</p>
    </div>
    <a href="<?= BASE_PATH ?>/loans" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_PATH ?>/loans/create" id="loanForm" data-testid="loan-create-form">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="loan_type" id="loanType" value="event">
    <div class="row g-3">
        <div class="col-lg-5">
            <!-- Pemilih jenis peminjaman -->
            <div class="loan-type-toggle mb-3" role="tablist" data-testid="loan-type-toggle">
                <button type="button" class="lt-btn active" data-type="event" data-testid="lt-event">
                    <i class="fa-solid fa-calendar-day"></i> Untuk Acara
                </button>
                <button type="button" class="lt-btn" data-type="opd" data-testid="lt-opd">
                    <i class="fa-solid fa-building-columns"></i> Untuk OPD
                </button>
            </div>

            <!-- ===== Blok ACARA ===== -->
            <div class="card-sb" id="blockEvent" data-testid="block-event">
                <div class="card-title">Rincian Acara</div>
                <div class="mb-3">
                    <label class="form-label">Nama Acara *</label>
                    <input type="text" name="event_name" class="form-control" data-req-event required placeholder="mis. Live Streaming Rapat Paripurna" data-testid="input-event-name">
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
                        <div class="form-text">Belum ada user ber-role IT Staff lain yang bisa dilibatkan. Atur <strong>IT Staff</strong> sebagai role utama atau peran tambahan user di Manajemen User agar bisa dipilih.</div>
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
                        <div class="form-text">Hanya personel ber-role IT Staff yang dapat dilibatkan — termasuk user yang memegang IT Staff sebagai peran tambahan. Nama Anda sendiri tidak ditampilkan karena sudah tercatat sebagai pemohon/penanggungjawab.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Tanggal Mulai *</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" data-req-event required min="<?= date('Y-m-d') ?>" data-testid="input-start-date">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Tanggal Selesai *</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" data-req-event required min="<?= date('Y-m-d') ?>" data-testid="input-end-date">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Jam Acara</label>
                        <input type="time" name="start_time" class="form-control" data-testid="input-start-time">
                    </div>
                </div>
            </div>

            <!-- ===== Blok OPD ===== -->
            <div class="card-sb" id="blockOpd" style="display:none;" data-testid="block-opd">
                <div class="card-title">Rincian Kebutuhan OPD</div>
                <div class="hint-box">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>Barang untuk OPD dikeluarkan <strong>tanpa batas waktu</strong>. Tanggal keluar dicatat <strong>saat barang benar-benar diserahkan dari gudang</strong>, bukan sekarang. Barang <strong>pinjam pakai</strong> tetap milik Diskominfo dan dikembalikan lewat penyerahan aset bila rusak; barang <strong>habis pakai</strong> diserahkan penuh ke OPD dan tidak dikembalikan.</div>
                </div>
                <?php $opd = opd_options(); ?>
                <div class="mb-3">
                    <label class="form-label">Nama OPD *</label>
                    <?php if (!empty($opd)): ?>
                        <select name="opd_name" class="form-select" data-req-opd disabled data-testid="input-opd-name">
                            <option value="">— Pilih OPD —</option>
                            <?php foreach ($opd as $grp => $items): ?>
                                <?php if (is_array($items)): ?>
                                    <optgroup label="<?= e((string)$grp) ?>">
                                        <?php foreach ($items as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?>
                                    </optgroup>
                                <?php else: ?>
                                    <option value="<?= e($items) ?>"><?= e($items) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="opd_name" class="form-control" data-req-opd disabled placeholder="mis. Dinas Pendidikan Kabupaten Tangerang" data-testid="input-opd-name">
                        <div class="form-text">Ketik nama OPD tujuan. Daftar pilihan menyusul setelah data resmi tersedia.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tujuan / Keperluan</label>
                    <textarea name="opd_purpose" rows="3" class="form-control" disabled placeholder="Jelaskan singkat kebutuhan / penempatan alat di OPD" data-testid="input-opd-purpose"></textarea>
                </div>
                <?php if (!is_personal_borrower() && !empty($itStaff)): ?>
                <div class="mb-3">
                    <label class="form-label">Personel yang Dilibatkan dalam Instalasi</label>
                    <div class="border rounded-3 p-2" style="max-height:180px;overflow-y:auto;" data-testid="opd-participants-box">
                        <?php foreach ($itStaff as $st): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="participant_ids[]" value="<?= (int)$st['id'] ?>" id="opdpart<?= (int)$st['id'] ?>" disabled data-testid="opd-participant-<?= (int)$st['id'] ?>">
                                <label class="form-check-label" for="opdpart<?= (int)$st['id'] ?>">
                                    <?= e($st['name']) ?><?php if (!empty($st['unit_kerja'])): ?> <span class="text-slate small">· <?= e($st['unit_kerja']) ?></span><?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Boleh dikosongkan bila tidak ada personel yang perlu ikut memasang.</div>
                </div>
                <?php endif; ?>

                <div class="mb-1">
                    <label class="form-label">Status Penyerahan Barang</label>
                    <div class="border rounded-3 p-2" style="max-height:220px;overflow-y:auto;" data-testid="opd-consumable-box">
                        <div id="opdConsumableList"></div>
                        <div id="opdConsumableEmpty" class="text-slate small py-1">Pilih alat di sebelah kanan terlebih dahulu. Alat yang dipilih akan muncul di sini untuk ditentukan statusnya.</div>
                    </div>
                    <div class="form-text">
                        Setiap barang bisa berstatus salah satu dari:
                        <strong>Habis pakai</strong> — diserahkan penuh ke OPD, tidak dikembalikan (mis. kabel, konektor); atau
                        <strong>Pinjam pakai</strong> — tetap milik Diskominfo, dikembalikan hanya bila rusak.
                        Barang <strong>pinjam pakai</strong> adalah default; centang hanya yang <strong>habis pakai</strong>.
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
document.getElementById('start_date')?.addEventListener('change', refreshAvail);
document.getElementById('end_date')?.addEventListener('change', refreshAvail);

// ── Pemilih jenis: Untuk Acara / Untuk OPD ────────────────────────────────────
// Kunci: field di blok yang TERSEMBUNYI di-disable. Field yang required tapi
// tak terlihat akan menggagalkan submit; field disabled juga tidak ikut terkirim,
// jadi event_name & opd_name tidak pernah bertabrakan.
(function () {
    const hidden = document.getElementById('loanType');
    const blocks = { event: document.getElementById('blockEvent'), opd: document.getElementById('blockOpd') };
    const buttons = document.querySelectorAll('.lt-btn');

    function setType(type) {
        hidden.value = type;
        Object.entries(blocks).forEach(([k, el]) => {
            if (!el) return;
            const on = k === type;
            el.style.display = on ? '' : 'none';
            // Nyalakan/matikan seluruh kontrol di blok agar hanya yang aktif terkirim.
            el.querySelectorAll('input, select, textarea').forEach(c => { c.disabled = !on; });
            // `required` hanya berlaku untuk field yang memang wajib di jenis ini.
            el.querySelectorAll('[data-req-' + k + ']').forEach(c => { if (on) c.setAttribute('required', ''); });
            el.querySelectorAll('[data-req-' + (k === 'event' ? 'opd' : 'event') + ']').forEach(c => c.removeAttribute('required'));
        });
        buttons.forEach(b => b.classList.toggle('active', b.dataset.type === type));
        window.__loanType = type;
        // Fungsi didefinisikan di blok berikutnya; saat setType('event') pertama
        // dipanggil, blok itu belum jalan — jadi diguard.
        if (window.rebuildConsumableList) window.rebuildConsumableList();
    }
    buttons.forEach(b => b.addEventListener('click', () => setType(b.dataset.type)));
    window.__loanType = 'event';
    setType('event');
})();

// Checklist "Barang Habis Pakai" di blok OPD. Isinya alat yang SEDANG dipilih di
// panel kanan; centang menandai barang yang tidak ditunggu kembali. Tanda disimpan
// di Set agar tidak hilang saat daftar dibangun ulang. Hanya aktif di mode OPD.
(function () {
    const marked = new Set();          // asset id yang ditandai habis pakai
    const list = document.getElementById('opdConsumableList');
    const empty = document.getElementById('opdConsumableEmpty');
    if (!list) return;

    window.rebuildConsumableList = function () {
        const opd = window.__loanType === 'opd';
        // Ingat dulu centang saat ini sebelum membangun ulang.
        list.querySelectorAll('input[type=checkbox]').forEach(cb => {
            if (cb.checked) marked.add(cb.value); else marked.delete(cb.value);
        });

        const chosen = Array.from(document.querySelectorAll('.asset-row'))
            .filter(r => { const c = r.querySelector('input[name="asset_ids[]"]'); return c && c.checked; });

        list.innerHTML = '';
        chosen.forEach(r => {
            const id = r.querySelector('input[name="asset_ids[]"]').value;
            const nameEl = r.querySelector('.fw-semibold');
            const name = nameEl ? nameEl.textContent.trim() : ('Alat #' + id);
            const isHp = marked.has(id);
            const wrap = document.createElement('div');
            wrap.className = 'form-check d-flex align-items-center justify-content-between gap-2 py-1';
            // name diisi HANYA saat mode OPD supaya di mode acara tidak ikut terkirim.
            // Badge status di kanan menjelaskan arti centang tanpa perlu menebak.
            wrap.innerHTML =
                '<span class="d-inline-flex align-items-center gap-2">'
                + '<input class="form-check-input mt-0" type="checkbox" '
                + (opd ? 'name="consumable_ids[]" ' : '')
                + 'value="' + id + '" id="opdcons' + id + '"' + (opd ? '' : ' disabled')
                + (isHp ? ' checked' : '') + ' data-testid="consumable-' + id + '">'
                + '<label class="form-check-label small mb-0" for="opdcons' + id + '">' + name + '</label>'
                + '</span>'
                + '<span class="badge ' + (isHp ? 'bg-warning text-dark' : 'bg-secondary')
                + '" data-status="' + id + '" style="font-weight:600;">'
                + (isHp ? 'Habis pakai — tidak kembali' : 'Pinjam pakai — kembali bila rusak')
                + '</span>';
            list.appendChild(wrap);
        });
        empty.style.display = chosen.length ? 'none' : '';
    };

    // Perbarui badge status begitu centang berubah, tanpa membangun ulang seluruh daftar.
    list.addEventListener('change', function (e) {
        if (!e.target || e.target.type !== 'checkbox') return;
        const id = e.target.value;
        const badge = list.querySelector('[data-status="' + id + '"]');
        if (!badge) return;
        const hp = e.target.checked;
        badge.className = 'badge ' + (hp ? 'bg-warning text-dark' : 'bg-secondary');
        badge.style.fontWeight = '600';
        badge.textContent = hp ? 'Habis pakai — tidak kembali' : 'Pinjam pakai — kembali bila rusak';
    });

    // Bangun ulang setiap pilihan alat berubah (termasuk saat centang dari panel kanan).
    document.getElementById('assetList')?.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[name="asset_ids[]"]')) window.rebuildConsumableList();
    });
    window.rebuildConsumableList();
})();

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
