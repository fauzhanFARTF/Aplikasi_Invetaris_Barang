<div class="page-header">
    <div>
        <h1>Perbaikan Alat</h1>
        <p class="subtitle">Alat rusak yang sedang / telah ditangani teknisi berdasarkan Formulir Perbaikan (SPK) fisik.</p>
    </div>
    <?= reset_button('repairs', 'Reset Perbaikan', 'RESET SEMUA catatan perbaikan? Seluruh riwayat perbaikan dihapus PERMANEN. Tindakan ini TIDAK BISA dibatalkan.') ?>
</div>

<div class="card-sb" data-livetable>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="card-title mb-0">Perbaikan Aktif (<?= count($active) ?>)</div>
        <div style="max-width:320px;width:100%;"><input type="search" data-ls-search class="form-control form-control-sm" placeholder="Cari kode, alat, BMN, atau keluhan... (langsung tampil)" data-testid="search-input-active" autocomplete="off"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="repairs-active">
            <thead><tr><th>Kode</th><th>Alat</th><th>BMN</th><th>Keluhan</th><th>Status</th><th>Dibuat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($active as $r): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($r['repair_code'].' '.$r['asset_name'].' '.$r['asset_code'].' '.$r['bmn_number'].' '.$r['complaint'].' '.$r['status'])) ?>">
                    <td class="code"><?= e($r['repair_code']) ?></td>
                    <td><?= e($r['asset_name']) ?><br><span class="small text-slate text-mono"><?= e($r['asset_code']) ?></span></td>
                    <td class="text-mono small"><?= e($r['bmn_number']) ?></td>
                    <td class="small"><?= e(mb_strimwidth($r['complaint'], 0, 60, '…')) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="small text-slate"><?= fmt_datetime($r['created_at']) ?></td>
                    <td class="text-nowrap">
                        <a href="<?= BASE_PATH ?>/repairs/<?= e($r["uuid"]) ?>" class="btn btn-sm btn-outline-navy" data-testid="btn-view-repair-<?= (int)$r['id'] ?>"><i class="fa-regular fa-eye"></i></a>
                        <a href="<?= BASE_PATH ?>/repairs/<?= e($r["uuid"]) ?>/print" target="_blank" class="btn btn-sm btn-amber" data-testid="btn-print-repair-<?= (int)$r['id'] ?>"><i class="fa-solid fa-print"></i> SPK</a>
                    </td>
                </tr>
            <?php endforeach; if (empty($active)): ?>
                <tr><td colspan="7" class="text-center text-slate py-4">Tidak ada alat dalam perbaikan.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="7" class="text-center text-slate py-4">Tidak ada perbaikan aktif yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card-sb mt-3" data-livetable>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="card-title mb-0">Perbaikan Selesai (20 terakhir)</div>
        <div style="max-width:320px;width:100%;"><input type="search" data-ls-search class="form-control form-control-sm" placeholder="Cari kode, alat, atau teknisi... (langsung tampil)" data-testid="search-input-done" autocomplete="off"></div>
        <?php if (!empty($done)): ?>
            <form method="POST" action="<?= BASE_PATH ?>/repairs/delete-all" data-confirm="Hapus SEMUA riwayat perbaikan yang sudah Selesai? Tindakan ini tidak dapat dibatalkan.">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <!-- <button type="submit" class="btn btn-sm btn-outline-navy text-danger" data-testid="btn-delete-all-repairs"><i class="fa-solid fa-trash"></i> Hapus Semua Riwayat</button> -->
            </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle">
            <thead><tr><th>Kode</th><th>Alat</th><th>Teknisi</th><th>Selesai</th><th>Ditutup Oleh</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($done as $r): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($r['repair_code'].' '.$r['asset_name'].' '.($r['technician_name'] ?? '').' '.($r['completed_by_name'] ?? ''))) ?>">
                    <td class="code"><a href="<?= BASE_PATH ?>/repairs/<?= e($r["uuid"]) ?>"><?= e($r['repair_code']) ?></a></td>
                    <td><?= e($r['asset_name']) ?></td>
                    <td class="small"><?= e($r['technician_name']) ?></td>
                    <td class="small text-slate"><?= fmt_datetime($r['completed_at']) ?></td>
                    <td class="small"><?= e($r['completed_by_name']) ?></td>
                    <td>
                        <form method="POST" action="<?= BASE_PATH ?>/repairs/<?= e($r["uuid"]) ?>/delete" data-confirm="Hapus riwayat perbaikan <?= e($r['repair_code']) ?>? Tindakan ini tidak dapat dibatalkan.">
                            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <!-- <button type="submit" class="btn btn-sm btn-outline-navy text-danger" title="Hapus riwayat" data-testid="btn-delete-repair-<?= (int)$r['id'] ?>"><i class="fa-solid fa-trash"></i></button> -->
                        </form>
                    </td>
                </tr>
            <?php endforeach; if (empty($done)): ?>
                <tr><td colspan="6" class="text-center text-slate py-4">Belum ada riwayat perbaikan.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="6" class="text-center text-slate py-4">Tidak ada riwayat perbaikan yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>
