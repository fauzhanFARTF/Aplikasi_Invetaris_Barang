<div class="page-header">
    <div>
        <h1>Riwayat Terhapus</h1>
        <p class="subtitle">Data yang sudah dihapus (soft delete) dari seluruh modul — masih bisa dipulihkan kapan saja.</p>
    </div>
</div>

<div class="card-sb">
    <div class="card-title">Data Terhapus</div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="trash-table">
            <thead><tr><th>Jenis Data</th><th>Nama / Kode</th><th>Dihapus oleh</th><th>Tanggal Dihapus</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-testid="trash-row-<?= e($r['type']) ?>-<?= $r['id'] ?>">
                    <td><span class="badge bg-secondary"><?= e($r['type_label']) ?></span></td>
                    <td><strong><?= e($r['label']) ?></strong></td>
                    <td class="small text-slate"><?= e($r['deleted_by_name'] ?? '—') ?></td>
                    <td class="small text-mono"><?= fmt_datetime($r['deleted_at']) ?></td>
                    <td class="text-nowrap">
                        <form method="POST" action="<?= BASE_PATH ?>/trash/<?= e($r['type']) ?>/<?= $r['id'] ?>/restore" data-confirm="Pulihkan data ini?">
                            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <button class="btn btn-sm btn-outline-navy" data-testid="btn-restore-<?= e($r['type']) ?>-<?= $r['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Pulihkan</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-center text-slate py-4">Belum ada data yang dihapus.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-sb mt-3">
    <div class="card-title">Riwayat Pemulihan</div>
    <p class="text-slate small">Data yang pernah dihapus lalu dipulihkan kembali — supaya tetap terlihat siapa yang menghapus dan siapa yang memulihkannya.</p>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="restored-table">
            <thead><tr><th>Jenis Data</th><th>Nama / Kode</th><th>Dihapus oleh</th><th>Dipulihkan oleh</th><th>Tanggal Dipulihkan</th></tr></thead>
            <tbody>
            <?php foreach ($restoredRows as $r): ?>
                <tr data-testid="restored-row-<?= e($r['type']) ?>-<?= $r['id'] ?>">
                    <td><span class="badge bg-secondary"><?= e($r['type_label']) ?></span></td>
                    <td><strong><?= e($r['label']) ?></strong></td>
                    <td class="small text-slate"><?= e($r['deleted_by_name'] ?? '—') ?></td>
                    <td class="small text-slate"><?= e($r['restored_by_name'] ?? '—') ?></td>
                    <td class="small text-mono"><?= fmt_datetime($r['restored_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($restoredRows)): ?>
                <tr><td colspan="5" class="text-center text-slate py-4">Belum ada data yang pernah dipulihkan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
