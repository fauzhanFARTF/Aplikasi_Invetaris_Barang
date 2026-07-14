<?php $user = Auth::user(); ?>
<div class="page-header">
    <div>
        <h1>Paket Alat</h1>
        <p class="subtitle">Bundling alat untuk skenario liputan yang umum, memudahkan pengajuan peminjaman.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (role_is('admin_gudang','admin')): ?>
            <a href="<?= BASE_PATH ?>/packages/create" class="btn btn-amber" data-testid="btn-new-package"><i class="fa-solid fa-plus"></i> Tambah Paket</a>
        <?php endif; ?>
        <?= reset_button('packages', 'Reset Paket', 'RESET SEMUA paket alat? Seluruh paket dihapus PERMANEN. Tindakan ini TIDAK BISA dibatalkan.') ?>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input type="search" data-ls-search class="form-control" placeholder="Cari nama atau deskripsi paket... (langsung tampil)" data-testid="search-input"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="packages-table">
            <thead><tr><th>Nama Paket</th><th>Deskripsi</th><th>Jumlah Alat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($p['name'].' '.$p['description'])) ?>">
                    <td><strong><?= e($p['name']) ?></strong></td>
                    <td class="small text-slate"><?= e($p['description'] ?: '—') ?></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$p['item_count'] ?> alat</span></td>
                    <td class="text-nowrap">
                        <?php if (role_is('admin_gudang','admin')): ?>
                            <a href="<?= BASE_PATH ?>/packages/<?= (int)$p['id'] ?>/edit" class="btn btn-sm btn-outline-navy"><i class="fa-regular fa-pen-to-square"></i></a>
                            <form method="POST" action="<?= BASE_PATH ?>/packages/<?= (int)$p['id'] ?>/delete" data-confirm="Hapus paket ini?" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (empty($packages)): ?>
                <tr><td colspan="4" class="text-center text-slate py-4">Belum ada paket.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="4" class="text-center text-slate py-4">Tidak ada paket yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>
