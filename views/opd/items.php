<?php $canReturn = role_is('admin_gudang', 'admin'); ?>
<div class="page-header">
    <div>
        <h1>Barang di OPD</h1>
        <p class="subtitle">Barang yang keluar lewat peminjaman <strong>Kebutuhan Jaringan</strong> dan sedang berada di OPD.</p>
    </div>
    <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<div class="hint-box mb-3">
    <i class="fa-solid fa-circle-info"></i>
    <div>Barang berstatus <span class="badge bg-info text-dark">Di OPD</span> masih <strong>menunggu</strong> — tetap milik Diskominfo dan dikembalikan hanya bila <strong>rusak</strong> (atau ditarik). Barang ini tidak digabung dengan alat lain di menu Pengembalian; tarik lewat tombol di baris masing-masing. Barang <span class="badge bg-primary">Dipinjam</span> dikembalikan berjadwal lewat menu Pengembalian biasa.</div>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-8"><input type="search" data-ls-search class="form-control" placeholder="Cari OPD, nama barang, kode, atau SN..." autocomplete="off"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="opd-items-table">
            <thead><tr>
                <th>OPD</th><th>Nama Barang</th><th>Kode</th><th>Model / SN</th>
                <th>Tgl Pemasangan</th><th>Penanggung Jawab</th><th>Personel</th><th>Status</th>
                <?php if ($canReturn): ?><th></th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $atOpd = $r['item_status'] === 'AtOpd'; ?>
                <?php $model = trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')); ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($r['opd_name'].' '.$r['asset_name'].' '.$r['asset_code'].' '.$r['bmn_number'].' '.$model.' '.($r['serial_number'] ?? '').' '.($r['requester_name'] ?? '').' '.($r['personnel'] ?? ''))) ?>">
                    <td>
                        <a href="<?= BASE_PATH ?>/loans/<?= e($r['loan_uuid']) ?>" class="fw-semibold text-decoration-none"><?= e($r['opd_name']) ?></a>
                        <div class="text-slate small text-mono"><?= e($r['loan_code']) ?></div>
                    </td>
                    <td><?= e($r['asset_name']) ?></td>
                    <td class="text-mono small"><?= e($r['asset_code']) ?><?php if (!empty($r['bmn_number'])): ?><div class="text-slate">No. BMD: <?= e($r['bmn_number']) ?></div><?php endif; ?></td>
                    <td class="small">
                        <?= $model !== '' ? e($model) : '<span class="text-slate">—</span>' ?>
                        <?php if (!empty($r['serial_number'])): ?><div class="text-slate text-mono">SN: <?= e($r['serial_number']) ?></div><?php endif; ?>
                    </td>
                    <td class="small"><?= !empty($r['checkout_at']) ? fmt_date($r['checkout_at']) : '<span class="text-slate">—</span>' ?></td>
                    <td class="small"><?= e($r['requester_name']) ?></td>
                    <td class="small"><?= !empty($r['personnel']) ? e($r['personnel']) : '<span class="text-slate">—</span>' ?></td>
                    <td>
                        <?php if ($atOpd): ?>
                            <span class="badge bg-info text-dark">Di OPD</span>
                            <div class="text-slate small mt-1">menunggu (kembali bila rusak)</div>
                        <?php else: ?>
                            <span class="badge bg-primary">Dipinjam</span>
                            <?php if (!empty($r['expected_return_date'])): ?><div class="text-slate small mt-1">rencana <?= fmt_date($r['expected_return_date']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($canReturn): ?>
                    <td class="text-nowrap">
                        <div class="d-flex gap-1">
                            <?php if ($atOpd): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-return-opd"
                                        data-action="<?= BASE_PATH ?>/opd-items/<?= (int)$r['item_id'] ?>/return"
                                        data-name="<?= e($r['asset_name']) ?>" data-opd="<?= e($r['opd_name']) ?>"
                                        data-testid="btn-return-opd-<?= (int)$r['item_id'] ?>">
                                    <i class="fa-solid fa-rotate-left"></i> Kembalikan
                                </button>
                            <?php else: ?>
                                <span class="text-slate small align-self-center">lewat Pengembalian</span>
                            <?php endif; ?>
                            <?php if (Auth::hasRole('superadmin')): ?>
                                <button type="button" class="btn btn-sm btn-outline-navy btn-edit-opd"
                                        data-action="<?= BASE_PATH ?>/sa/opd-item/<?= (int)$r['item_id'] ?>/edit"
                                        data-name="<?= e($r['asset_name']) ?>"
                                        data-will-return="<?= (int)($r['will_return'] ?? 1) ?>"
                                        data-date="<?= e($r['expected_return_date'] ?? '') ?>"
                                        title="Ubah rencana (Super Admin)" data-testid="btn-edit-opd-<?= (int)$r['item_id'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="<?= BASE_PATH ?>/sa/opd-item/<?= (int)$r['item_id'] ?>/delete"
                                      data-confirm="Hapus &quot;<?= e($r['asset_name']) ?>&quot; dari OPD <?= e($r['opd_name']) ?>? Barang dilepas dari acara dan alat kembali Tersedia.">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus dari OPD (Super Admin)" data-testid="btn-delete-opd-<?= (int)$r['item_id'] ?>"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; if (empty($rows)): ?>
                <tr><td colspan="<?= $canReturn ? 9 : 8 ?>" class="text-center text-slate py-4">Belum ada barang yang berada di OPD.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="<?= $canReturn ? 9 : 8 ?>" class="text-center text-slate py-4">Tidak ada barang yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canReturn): ?>
<div class="modal fade" id="returnOpdModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="returnOpdForm" data-testid="return-opd-form">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kembalikan Barang dari OPD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Menarik <strong id="returnOpdName">barang</strong> dari <strong id="returnOpdOpd">OPD</strong>. Pilih kondisi barang saat ditarik.</p>
                    <label class="form-label">Kondisi *</label>
                    <select name="condition" id="returnOpdCondition" class="form-select mb-3" data-testid="return-opd-condition">
                        <option value="Damaged" selected>Rusak — perlu perbaikan</option>
                        <option value="Good">Baik — ditarik / dikembalikan</option>
                        <option value="Lost">Hilang</option>
                    </select>
                    <label class="form-label" id="returnOpdNoteLabel">Keterangan Kerusakan *</label>
                    <textarea name="note" id="returnOpdNote" class="form-control" rows="3" data-testid="return-opd-note" placeholder="Jelaskan kerusakan / kondisi barang"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" data-testid="btn-return-opd-confirm"><i class="fa-solid fa-rotate-left"></i> Proses</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php if (Auth::hasRole('superadmin')): ?>
<div class="modal fade" id="editOpdModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editOpdForm" data-testid="edit-opd-form">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Rencana Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Barang: <strong id="editOpdName">—</strong></p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="will_return" value="1" id="editOpdWillReturn" data-testid="edit-opd-will-return">
                        <label class="form-check-label" for="editOpdWillReturn"><strong>Barang akan dikembalikan?</strong></label>
                    </div>
                    <div id="editOpdDateWrap" style="display:none;">
                        <label class="form-label">Rencana Tanggal Kembali *</label>
                        <input type="date" name="expected_return_date" id="editOpdDate" class="form-control" data-testid="edit-opd-date">
                    </div>
                    <div class="form-text mt-2">Dicentang &rarr; barang berstatus <strong>Dipinjam</strong> dan masuk menu Pengembalian. Tidak dicentang &rarr; berstatus <strong>Di OPD</strong> (menunggu, kembali bila rusak).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-testid="btn-edit-opd-save"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var el = document.getElementById('editOpdModal');
    if (!el || !window.bootstrap) return;
    var modal = new bootstrap.Modal(el);
    var form = document.getElementById('editOpdForm');
    var cb   = document.getElementById('editOpdWillReturn');
    var wrap = document.getElementById('editOpdDateWrap');
    var date = document.getElementById('editOpdDate');
    function sync() {
        wrap.style.display = cb.checked ? '' : 'none';
        date.disabled = !cb.checked;
        if (cb.checked) date.setAttribute('required', ''); else date.removeAttribute('required');
    }
    cb.addEventListener('change', sync);
    document.querySelectorAll('.btn-edit-opd').forEach(function (b) {
        b.addEventListener('click', function () {
            form.action = b.dataset.action;
            document.getElementById('editOpdName').textContent = b.dataset.name;
            cb.checked = b.dataset.willReturn === '1';
            date.value = b.dataset.date || '';
            sync();
            modal.show();
        });
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var modalEl = document.getElementById('returnOpdModal');
    if (!modalEl || !window.bootstrap) return;
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('returnOpdForm');
    var cond = document.getElementById('returnOpdCondition');
    var noteLabel = document.getElementById('returnOpdNoteLabel');
    function syncNote() {
        var c = cond.value;
        noteLabel.textContent = c === 'Good' ? 'Keterangan (opsional)' : (c === 'Lost' ? 'Keterangan Kehilangan *' : 'Keterangan Kerusakan *');
    }
    cond.addEventListener('change', syncNote);
    document.querySelectorAll('.btn-return-opd').forEach(function (b) {
        b.addEventListener('click', function () {
            form.action = b.dataset.action;
            document.getElementById('returnOpdName').textContent = b.dataset.name;
            document.getElementById('returnOpdOpd').textContent = b.dataset.opd;
            cond.value = 'Damaged'; syncNote();
            document.getElementById('returnOpdNote').value = '';
            modal.show();
        });
    });
})();
</script>
<?php endif; ?>
