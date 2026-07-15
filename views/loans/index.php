<?php $user = Auth::user(); ?>
<div class="page-header">
    <div>
        <h1>Peminjaman</h1>
        <p class="subtitle">Daftar pengajuan peminjaman alat streaming.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (role_is('admin_gudang','admin')): ?>
            <!-- <form method="POST" action="<?= BASE_PATH ?>/loans/delete-all" data-confirm="Hapus SEMUA riwayat peminjaman yang sudah Selesai/Ditolak/Dibatalkan/Dikembalikan? Tindakan ini tidak dapat dibatalkan.">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button type="submit" class="btn btn-outline-navy text-danger" data-testid="btn-delete-all-loans"><i class="fa-solid fa-trash"></i> Hapus Semua Riwayat</button>
            </form> -->
        <?php endif; ?>
        <?php if (role_is('pemohon','inventory_staff','admin')): ?>
            <a href="<?= BASE_PATH ?>/loans/create" class="btn btn-amber" data-testid="btn-new-loan"><i class="fa-solid fa-plus"></i> Ajukan Peminjaman</a>
        <?php endif; ?>
        <?= reset_button('loans', 'Reset Peminjaman', 'RESET SEMUA peminjaman/acara? Seluruh data peminjaman dihapus PERMANEN dan status alat yang dipinjam/dipesan dikembalikan ke Tersedia. Tindakan ini TIDAK BISA dibatalkan.') ?>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input type="search" data-ls-search class="form-control" placeholder="Cari kode, pemohon, acara, atau nama alat... (langsung tampil)" data-testid="search-input" autocomplete="off"></div>
        <div class="col-md-6 d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm <?= $currentStatus === '' ? 'btn-primary' : 'btn-outline-navy' ?>" data-ls-filter="status" data-ls-value="">Semua</button>
            <?php foreach (['Pending','Approved','CheckedOut','Returned','Completed','Rejected','Cancelled'] as $s): ?>
                <button type="button" class="btn btn-sm <?= $currentStatus === $s ? 'btn-primary' : 'btn-outline-navy' ?>" data-ls-filter="status" data-ls-value="<?= $s ?>"><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="loans-table">
            <thead><tr><th>Kode</th><th>Pemohon</th><th>Acara</th><th>Tanggal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php $finalStatuses = ['Completed','Rejected','Cancelled','Returned']; ?>
            <?php foreach ($loans as $l): ?>
                <tr data-ls-row data-ls-status="<?= e($l['status']) ?>"
                    data-ls-text="<?= e(strtolower($l['loan_code'].' '.$l['requester_name'].' '.$l['event_name'].' '.($l['asset_names'] ?? ''))) ?>">
                    <td class="code"><?= e($l['loan_code']) ?></td>
                    <td><?= e($l['requester_name']) ?></td>
                    <td><?= e($l['event_name']) ?></td>
                    <td class="small"><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="d-flex gap-1">
                        <a href="<?= BASE_PATH ?>/loans/<?= e($l["uuid"]) ?>" class="btn btn-sm btn-outline-navy" data-testid="btn-view-loan-<?= (int)$l['id'] ?>"><i class="fa-regular fa-eye"></i></a>
                        <?php if (role_is('admin_gudang','admin') && in_array($l['status'], $finalStatuses, true)): ?>
                            <!-- <form method="POST" action="<?= BASE_PATH ?>/loans/<?= e($l["uuid"]) ?>/delete" data-confirm="Hapus riwayat peminjaman <?= e($l['loan_code']) ?>? Tindakan ini tidak dapat dibatalkan.">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-navy text-danger" title="Hapus riwayat" data-testid="btn-delete-loan-<?= (int)$l['id'] ?>"><i class="fa-solid fa-trash"></i></button>
                            </form> -->
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (empty($loans)): ?>
                <tr><td colspan="6" class="text-center text-slate py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="6" class="text-center text-slate py-4">Tidak ada peminjaman yang cocok dengan pencarian / filter.</td></tr>
            </tbody>
        </table>
    </div>
</div>
