<div class="page-header">
    <div>
        <h1>Penyerahan Alat</h1>
        <p class="subtitle">Daftar peminjaman disetujui yang siap diserahkan ke pemohon.</p>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input type="search" data-ls-search class="form-control" placeholder="Cari kode, pemohon, atau acara... (langsung tampil)" data-testid="search-input"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="checkout-list">
            <thead><tr><th>Kode</th><th>Pemohon</th><th>Acara</th><th>Rentang</th><th>Progress</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($loans as $l): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($l['loan_code'].' '.$l['requester_name'].' '.$l['event_name'])) ?>">
                    <td class="code"><?= e($l['loan_code']) ?></td>
                    <td><?= e($l['requester_name']) ?></td>
                    <td><?= e($l['event_name']) ?></td>
                    <td class="small"><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></td>
                    <td class="small"><span class="fw-bold"><?= (int)$l['out_items'] ?></span> / <?= (int)$l['total_items'] ?> alat</td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td><a href="<?= BASE_PATH ?>/checkout/<?= (int)$l['id'] ?>" class="btn btn-sm btn-amber" data-testid="btn-scan-<?= (int)$l['id'] ?>"><i class="fa-solid fa-barcode"></i> Scan</a></td>
                </tr>
            <?php endforeach; if (empty($loans)): ?>
                <tr><td colspan="7" class="text-center text-slate py-4">Tidak ada peminjaman yang menunggu penyerahan hari ini.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="7" class="text-center text-slate py-4">Tidak ada peminjaman yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>
