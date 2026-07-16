<?php $user = Auth::user(); ?>
<div class="page-header">
    <div>
        <h1>Manajemen Alat / Aset</h1>
        <p class="subtitle">Inventaris aset streaming BMN — total <?= count($assets) ?> item ditampilkan.</p>
    </div>
    <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
        <div class="d-flex gap-2">
            <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
                <button type="button" class="btn btn-outline-navy" id="btnPrintSelected" disabled data-testid="btn-print-selected"><i class="fa-solid fa-qrcode"></i> Cetak QR Code Terpilih (<span id="selCount">0</span>)</button>
            <?php endif; ?>
            <a href="<?= BASE_PATH ?>/inventory/create" class="btn btn-amber" data-testid="btn-new-asset"><i class="fa-solid fa-plus"></i> Tambah Alat</a>
            <?= reset_button('assets', 'Reset Alat', 'RESET SEMUA alat? Seluruh alat dihapus PERMANEN, termasuk peminjaman & perbaikan yang terkait. Tindakan ini TIDAK BISA dibatalkan.') ?>
        </div>
    <?php endif; ?>
</div>

<div class="card-sb">
    <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
    <div class="hint-box">
        <i class="fa-solid fa-circle-info"></i>
        <div>Setiap alat punya QR code unik. Centang alat lalu klik <strong>"Cetak QR Code Terpilih"</strong> untuk mencetak stiker QR yang bisa ditempel di alat. Stiker ini bisa dipindai memakai <strong>kamera HP</strong> maupun <strong>alat pemindai QR (2D scanner USB/Bluetooth)</strong> saat penyerahan/pengembalian alat.</div>
    </div>
    <?php endif; ?>
    <div data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-5"><input type="search" id="assetSearchBox" data-ls-search class="form-control" placeholder="Cari nama alat, kode, atau BMN... (langsung tampil)" data-testid="search-input" autocomplete="off"></div>
        <div class="col-md-3">
            <select class="form-select" data-ls-filter="category" data-testid="filter-category">
                <option value="">— Semua Kategori —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" data-ls-filter="status" data-testid="filter-status">
                <option value="">— Semua Status —</option>
                <?php foreach (['Available','Booked','CheckedOut','Damaged','Lost','Retired'] as $s): ?>
                    <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="assets-table">
            <thead><tr>
                <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?><th style="width:32px;"><input type="checkbox" class="form-check-input" id="selectAll" aria-label="Pilih semua"></th><?php endif; ?>
                <th>Foto</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Brand/Model</th><th>No. BMN</th><th>Kode QR</th><th>Harga Dulu</th><th>Nilai Sekarang</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($assets as $a): ?>
                <tr class="asset-row" data-ls-row
                    data-ls-text="<?= e(strtolower($a['name'].' '.$a['bmn_number'].' '.$a['asset_code'].' '.($a['category_name'] ?? '').' '.($a['brand'] ?? '').' '.($a['model'] ?? '').' '.($a['barcode'] ?? ''))) ?>"
                    data-ls-category="<?= (int)($a['category_id'] ?? 0) ?>"
                    data-ls-status="<?= e($a['status']) ?>">
                    <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
                        <td><input type="checkbox" class="form-check-input asset-check" value="<?= e($a['uuid']) ?>" data-testid="check-<?= (int)$a['id'] ?>"></td>
                    <?php endif; ?>
                    <td>
                        <?php $photoUrl = asset_photo_url($a['photo'] ?? null); ?>
                        <a href="<?= e($photoUrl) ?>" target="_blank" title="Lihat foto ukuran penuh">
                            <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($a['name']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0;background:#fff;">
                        </a>
                    </td>
                    <td class="code"><?= e($a['asset_code']) ?></td>
                    <td><strong><?= e($a['name']) ?></strong><?php if ($a['serial_number']): ?><div class="text-slate small text-mono">SN: <?= e($a['serial_number']) ?></div><?php endif; ?></td>
                    <td class="small"><?= e($a['category_name'] ?: '—') ?></td>
                    <td class="small"><?= e(trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? ''))) ?: '—' ?></td>
                    <td class="text-mono small"><?= e($a['bmn_number']) ?></td>
                    <td class="text-mono small"><?= e($a['barcode']) ?></td>
                    <td class="text-mono small"><?= fmt_rupiah($a['purchase_price'] ?? null) ?></td>
                    <td class="text-mono small"><?= fmt_rupiah($a['current_value'] ?? null) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-nowrap">
                        <?php if (role_is('admin_gudang','admin','supervisor')): ?>
                            <a href="<?= BASE_PATH ?>/inventory/<?= e($a["uuid"]) ?>/barcode" target="_blank" class="btn btn-sm btn-outline-navy" title="Cetak QR Code" data-testid="btn-barcode-<?= (int)$a['id'] ?>"><i class="fa-solid fa-qrcode"></i></a>
                        <?php endif; ?>
                        <?php $canManageThis = inventory_can_manage($a['created_by'] ?? null); ?>
                        <?php if ($canManageThis): ?>
                            <a href="<?= BASE_PATH ?>/inventory/<?= e($a["uuid"]) ?>/edit" class="btn btn-sm btn-outline-navy" title="Ubah" data-testid="btn-edit-<?= (int)$a['id'] ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                        <?php endif; ?>
                        <?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
                            <?php if (in_array($a['status'], ['Available','Damaged'])): ?>
                                <form method="POST" action="<?= BASE_PATH ?>/inventory/<?= e($a["uuid"]) ?>/retire" data-confirm="Nonaktifkan (retire) alat ini?" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Retire" data-testid="btn-retire-<?= (int)$a['id'] ?>"><i class="fa-solid fa-box-archive"></i></button>
                                </form>
                            <?php elseif ($a['status'] === 'Retired'): ?>
                                <form method="POST" action="<?= BASE_PATH ?>/inventory/<?= e($a["uuid"]) ?>/unretire" data-confirm="Aktifkan kembali alat ini? Status akan menjadi Tersedia." style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button class="btn btn-sm btn-outline-navy" title="Aktifkan kembali" data-testid="btn-unretire-<?= (int)$a['id'] ?>"><i class="fa-solid fa-box-open"></i></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canManageThis): ?>
                            <?php $lockDelete = (!Auth::hasRole('superadmin') && !empty($a['has_loan'])); ?>
                            <?php if ($lockDelete): ?>
                                <button class="btn btn-sm btn-outline-danger" title="Pernah dipinjam — hanya Super Admin yang dapat menghapus" disabled data-testid="btn-delete-locked-<?= (int)$a['id'] ?>"><i class="fa-solid fa-lock"></i></button>
                            <?php else: ?>
                                <form method="POST" action="<?= BASE_PATH ?>/inventory/<?= e($a["uuid"]) ?>/delete" data-confirm="Hapus alat ini? (masih bisa dipulihkan lewat Riwayat Terhapus)" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus" data-testid="btn-delete-<?= (int)$a['id'] ?>"><i class="fa-regular fa-trash-can"></i></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (empty($assets)): ?>
                <tr><td colspan="12" class="text-center text-slate py-4">Tidak ada data.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="12" class="text-center text-slate py-4">Tidak ada alat yang cocok dengan pencarian / filter.</td></tr>
            </tbody>
        </table>
    </div>
    </div>
</div>

<?php if (role_is('admin_gudang','admin','it_staff_pembantu')): ?>
<script>
    const selectAll = document.getElementById('selectAll');
    const checks = () => Array.from(document.querySelectorAll('.asset-check'));
    const btnPrint = document.getElementById('btnPrintSelected');
    const selCount = document.getElementById('selCount');

    function refreshSelection() {
        const selected = checks().filter(c => c.checked);
        selCount.textContent = selected.length;
        btnPrint.disabled = selected.length === 0;
    }
    selectAll?.addEventListener('change', () => { checks().forEach(c => c.checked = selectAll.checked); refreshSelection(); });
    checks().forEach(c => c.addEventListener('change', refreshSelection));
    btnPrint?.addEventListener('click', () => {
        const ids = checks().filter(c => c.checked).map(c => c.value).join(',');
        if (ids) window.open((window.BASE_PATH || '') + '/inventory/barcode/print?ids=' + ids, '_blank');
    });
</script>
<?php endif; ?>
