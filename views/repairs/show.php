<div class="page-header">
    <div>
        <h1>Perbaikan <span class="text-mono text-slate" style="font-size:16px;"><?= e($repair['repair_code']) ?></span></h1>
        <p class="subtitle"><?= e($repair['asset_name']) ?> · <?= e($repair['bmn_number']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/repairs" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        <a href="<?= BASE_PATH ?>/repairs/<?= (int)$repair['id'] ?>/print" target="_blank" class="btn btn-amber" data-testid="btn-print-spk"><i class="fa-solid fa-print"></i> Cetak SPK</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-sb">
            <div class="card-title">Detail Kerusakan</div>
            <table class="table table-sm">
                <tr><td class="text-slate">Status</td><td><?= status_badge($repair['status']) ?></td></tr>
                <tr><td class="text-slate">Kode Repair</td><td class="text-mono"><?= e($repair['repair_code']) ?></td></tr>
                <tr><td class="text-slate">Aset</td><td><?= e($repair['asset_name']) ?> (<?= e($repair['asset_code']) ?>)</td></tr>
                <tr><td class="text-slate">No. BMN</td><td class="text-mono"><?= e($repair['bmn_number']) ?></td></tr>
                <tr><td class="text-slate">Brand / Model</td><td><?= e(trim($repair['brand'].' '.$repair['model'])) ?: '—' ?></td></tr>
                <tr><td class="text-slate">Serial</td><td class="text-mono"><?= e($repair['serial_number'] ?: '—') ?></td></tr>
                <tr><td class="text-slate">Loan Sumber</td><td><?= e($repair['loan_code'] ?: '—') ?><?= $repair['requester_name'] ? ' · '.e($repair['requester_name']) : '' ?></td></tr>
                <tr><td class="text-slate">Dibuat</td><td><?= fmt_datetime($repair['created_at']) ?></td></tr>
                <?php if ($repair['form_printed_at']): ?>
                <tr><td class="text-slate">SPK Dicetak</td><td><?= fmt_datetime($repair['form_printed_at']) ?></td></tr>
                <?php endif; ?>
                <?php if ($repair['completed_at']): ?>
                <tr><td class="text-slate">Ditutup</td><td><?= fmt_datetime($repair['completed_at']) ?> oleh <?= e($repair['completed_by_name']) ?></td></tr>
                <?php endif; ?>
            </table>
            <div class="alert alert-warning mb-0"><strong>Keluhan:</strong><br><?= nl2br(e($repair['complaint'])) ?></div>
        </div>
    </div>

    <div class="col-lg-6">
        <?php if ($repair['status'] === 'Completed'): ?>
            <div class="card-sb">
                <div class="card-title">Hasil Perbaikan</div>
                <p class="mb-2"><strong>Teknisi:</strong> <?= e($repair['technician_name']) ?></p>
                <p><strong>Tindakan:</strong></p>
                <div class="alert alert-info mb-0"><?= nl2br(e($repair['action_taken'])) ?></div>
            </div>
        <?php else: ?>
            <div class="card-sb">
                <div class="card-title">Tutup Perbaikan</div>
                <p class="text-slate small">Isi form ini setelah menerima kembali alat + Formulir Perbaikan (kertas) yang telah ditandatangani teknisi. Status aset akan otomatis kembali ke <strong>Tersedia</strong>.</p>
                <form method="POST" action="<?= BASE_PATH ?>/repairs/<?= (int)$repair['id'] ?>/complete" data-testid="repair-complete-form">
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Nama Teknisi *</label>
                        <input type="text" name="technician_name" class="form-control" required placeholder="mis. Rian Hidayat" data-testid="input-technician">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tindakan Perbaikan (salin dari kertas SPK) *</label>
                        <textarea name="action_taken" class="form-control" rows="6" required placeholder="Jelaskan tindakan yang dilakukan teknisi..." data-testid="input-action"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" data-testid="btn-complete-repair"><i class="fa-solid fa-check"></i> Tutup Perbaikan & Set Tersedia</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
