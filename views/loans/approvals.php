<div class="page-header">
    <div>
        <h1>Approval Peminjaman</h1>
        <p class="subtitle">Tinjau dan berikan keputusan atas pengajuan peminjaman alat.</p>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="card-title">Menunggu Persetujuan (<?= count($pending) ?>)</div>
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input type="search" data-ls-search class="form-control" placeholder="Cari kode, pemohon, atau acara... (langsung tampil)" data-testid="search-input"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="pending-approvals-table">
            <thead><tr><th>Kode</th><th>Pemohon</th><th>Acara</th><th>Tanggal</th><th>Diajukan</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $l): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($l['loan_code'].' '.$l['requester_name'].' '.$l['requester_unit'].' '.$l['event_name'])) ?>">
                    <td class="code"><?= e($l['loan_code']) ?></td>
                    <td><?= e($l['requester_name']) ?><br><span class="text-slate small"><?= e($l['requester_unit']) ?></span></td>
                    <td><?= e($l['event_name']) ?></td>
                    <td class="small"><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></td>
                    <td class="small text-slate"><?= fmt_datetime($l['created_at']) ?></td>
                    <td><a href="<?= BASE_PATH ?>/loans/<?= e($l["uuid"]) ?>" class="btn btn-sm btn-primary" data-testid="btn-review-<?= (int)$l['id'] ?>">Tinjau</a></td>
                </tr>
            <?php endforeach; if (empty($pending)): ?>
                <tr><td colspan="6" class="text-center text-slate py-4">Tidak ada pengajuan menunggu.</td></tr>
            <?php endif; ?>
            <tr data-ls-empty style="display:none;"><td colspan="6" class="text-center text-slate py-4">Tidak ada pengajuan yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card-sb mt-3" data-livetable>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="card-title mb-0">Riwayat Keputusan Terakhir</div>
        <div style="max-width:320px;width:100%;"><input type="search" data-ls-search class="form-control form-control-sm" placeholder="Cari kode, pemohon, atau acara..."></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle">
            <thead><tr><th>Kode</th><th>Pemohon</th><th>Acara</th><th>Status</th><th>Diputuskan</th></tr></thead>
            <tbody>
            <?php foreach ($decided as $l): ?>
                <tr data-ls-row data-ls-status="<?= e($l['status']) ?>" data-ls-text="<?= e(strtolower($l['loan_code'].' '.$l['requester_name'].' '.$l['event_name'])) ?>">
                    <td class="code"><a href="<?= BASE_PATH ?>/loans/<?= e($l["uuid"]) ?>"><?= e($l['loan_code']) ?></a></td>
                    <td><?= e($l['requester_name']) ?></td>
                    <td><?= e($l['event_name']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="small text-slate"><?= fmt_datetime($l['approved_at']) ?> · <?= e($l['supervisor_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr data-ls-empty style="display:none;"><td colspan="5" class="text-center text-slate py-4">Tidak ada riwayat yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>
