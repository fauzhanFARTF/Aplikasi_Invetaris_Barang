<?php $user = Auth::user(); ?>
<div class="page-header">
    <div>
        <h1>Peminjaman <span class="text-mono text-slate" style="font-size:16px;"><?= e($loan['loan_code']) ?></span></h1>
        <p class="subtitle"><?= e($loan['event_name']) ?></p>
        <?= audit_trail_info($loan) ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/loans" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>

        <?php if ($loan['status'] === 'Pending' && in_array($user['role'], ['supervisor','admin'])): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal" data-testid="btn-approve"><i class="fa-solid fa-check"></i> Setujui</button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-testid="btn-reject"><i class="fa-solid fa-xmark"></i> Tolak</button>
        <?php endif; ?>

        <?php if (in_array($loan['status'], ['Pending','Approved']) && (Auth::role() === 'admin' || (int)$loan['requester_id'] === Auth::id())): ?>
            <form method="POST" action="<?= BASE_PATH ?>/loans/<?= (int)$loan['id'] ?>/cancel" data-confirm="Batalkan peminjaman ini?">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn btn-outline-navy" data-testid="btn-cancel-loan"><i class="fa-solid fa-ban"></i> Batalkan</button>
            </form>
        <?php endif; ?>

        <?php if ($loan['status'] === 'Approved' && in_array($user['role'], ['admin_gudang','admin'])): ?>
            <a href="<?= BASE_PATH ?>/checkout/<?= (int)$loan['id'] ?>" class="btn btn-amber" data-testid="btn-goto-checkout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Penyerahan Sekarang</a>
        <?php endif; ?>

        <?php if ($loan['status'] === 'CheckedOut' && in_array($user['role'], ['admin_gudang','admin'])): ?>
            <a href="<?= BASE_PATH ?>/checkin/<?= (int)$loan['id'] ?>" class="btn btn-amber" data-testid="btn-goto-checkin"><i class="fa-solid fa-arrow-right-to-bracket"></i> Pengembalian Sekarang</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-sb">
            <div class="card-title">Ringkasan</div>
            <table class="table table-sm mb-0">
                <tr><td class="text-slate">Status</td><td><?= status_badge($loan['status']) ?></td></tr>
                <tr><td class="text-slate">Pemohon</td><td><?= e($loan['requester_name']) ?><br><span class="small text-slate"><?= e($loan['requester_unit']) ?></span></td></tr>
                <tr><td class="text-slate">Tanggal</td><td><?= fmt_date($loan['start_date']) ?> — <?= fmt_date($loan['end_date']) ?></td></tr>
                <tr><td class="text-slate">Lokasi</td><td><?= e($loan['event_location'] ?: '—') ?></td></tr>
                <tr><td class="text-slate">Tujuan</td><td><?= nl2br(e($loan['purpose'] ?: '—')) ?></td></tr>
                <tr><td class="text-slate">Diajukan</td><td><?= fmt_datetime($loan['created_at']) ?></td></tr>
                <?php if ($loan['approved_at']): ?>
                    <tr><td class="text-slate">Keputusan</td><td><?= fmt_datetime($loan['approved_at']) ?><br><span class="small text-slate">oleh <?= e($loan['supervisor_name']) ?></span></td></tr>
                <?php endif; ?>
                <?php if ($loan['approval_note']): ?>
                    <tr><td class="text-slate">Catatan Approval</td><td><?= nl2br(e($loan['approval_note'])) ?></td></tr>
                <?php endif; ?>
                <?php if ($loan['checkout_at']): ?>
                    <tr><td class="text-slate">Penyerahan</td><td><?= fmt_datetime($loan['checkout_at']) ?></td></tr>
                <?php endif; ?>
                <?php if ($loan['checkin_at']): ?>
                    <tr><td class="text-slate">Pengembalian</td><td><?= fmt_datetime($loan['checkin_at']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-sb">
            <div class="card-title">Daftar Alat (<?= count($items) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sb align-middle" data-testid="loan-items-table">
                    <thead><tr><th>#</th><th>Alat</th><th>BMN</th><th>Kode QR</th><th>Paket</th><th>Status Item</th><th>Kondisi Kembali</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($it['asset_name']) ?></strong><div class="text-slate small text-mono"><?= e($it['asset_code']) ?></div></td>
                            <td class="text-mono small"><?= e($it['bmn_number']) ?></td>
                            <td class="text-mono small"><?= e($it['barcode']) ?></td>
                            <td class="small"><?= e($it['package_name'] ?? '—') ?></td>
                            <td><?= status_badge($it['item_status']) ?></td>
                            <td>
                                <?php if ($it['return_condition']):
                                    $condBadgeKey = ['Good' => 'ReturnedGood', 'Damaged' => 'ReturnedDamaged', 'Lost' => 'ReturnedLost'][$it['return_condition']] ?? 'ReturnedDamaged';
                                ?>
                                    <?= status_badge($condBadgeKey) ?>
                                    <?php if ($it['return_condition'] === 'Lost'): ?>
                                        <div class="small text-slate mt-1">Harga dulu: <?= fmt_rupiah($it['purchase_price']) ?> · Nilai sekarang: <?= fmt_rupiah($it['current_value']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($it['damage_note']): ?><div class="small text-danger mt-1"><?= e($it['damage_note']) ?></div><?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php if ($loan['status'] === 'Pending' && in_array($user['role'], ['supervisor','admin'])): ?>
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_PATH ?>/loans/<?= (int)$loan['id'] ?>/approve" data-testid="approve-form">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Setujui Peminjaman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Konfirmasi persetujuan atas <strong><?= e($loan['loan_code']) ?></strong>.</p>
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea name="note" class="form-control" rows="3" data-testid="approve-note"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-navy" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-success" type="submit" data-testid="btn-approve-confirm"><i class="fa-solid fa-check"></i> Setujui</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_PATH ?>/loans/<?= (int)$loan['id'] ?>/reject" data-testid="reject-form">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tolak Peminjaman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Anda akan menolak pengajuan <strong><?= e($loan['loan_code']) ?></strong>.</p>
                    <label class="form-label">Alasan Penolakan *</label>
                    <textarea name="note" class="form-control" rows="3" required data-testid="reject-note"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-navy" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-danger" type="submit" data-testid="btn-reject-confirm"><i class="fa-solid fa-xmark"></i> Tolak</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
