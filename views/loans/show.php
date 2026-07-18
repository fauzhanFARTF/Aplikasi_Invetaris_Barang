<?php $user = Auth::user(); ?>
<div class="page-header">
    <div>
        <h1>Peminjaman <span class="text-mono text-slate" style="font-size:16px;"><?= e($loan['loan_code']) ?></span>
            <?php if (($loan['loan_type'] ?? 'event') === 'opd'): ?>
                <span class="badge bg-info text-dark align-middle" style="font-size:11px;">Kebutuhan Jaringan</span>
            <?php endif; ?>
        </h1>
        <p class="subtitle"><?php if (($loan['loan_type'] ?? 'event') === 'opd'): ?>OPD: <?php endif; ?><?= e($loan['event_name']) ?></p>
        <?= audit_trail_info($loan) ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/loans" class="btn btn-outline-navy"><i class="fa-solid fa-arrow-left"></i> Kembali</a>

        <?php if ($loan['status'] === 'Pending' && role_is('supervisor','admin')): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal" data-testid="btn-approve"><i class="fa-solid fa-check"></i> Setujui</button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-testid="btn-reject"><i class="fa-solid fa-xmark"></i> Tolak</button>
        <?php endif; ?>

        <?php if (in_array($loan['status'], ['Pending','Approved']) && (role_is('admin') || (int)$loan['requester_id'] === Auth::id())): ?>
            <form method="POST" action="<?= BASE_PATH ?>/loans/<?= e($loan["uuid"]) ?>/cancel" data-confirm="Batalkan peminjaman ini?">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn btn-outline-navy" data-testid="btn-cancel-loan"><i class="fa-solid fa-ban"></i> Batalkan</button>
            </form>
        <?php endif; ?>

        <?php if ($loan['status'] === 'Approved' && role_is('admin_gudang','admin')): ?>
            <a href="<?= BASE_PATH ?>/checkout/<?= e($loan["uuid"]) ?>" class="btn btn-amber" data-testid="btn-goto-checkout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Penyerahan Sekarang</a>
        <?php endif; ?>

        <?php if ($loan['status'] === 'CheckedOut' && role_is('admin_gudang','admin')): ?>
            <a href="<?= BASE_PATH ?>/checkin/<?= e($loan["uuid"]) ?>" class="btn btn-amber" data-testid="btn-goto-checkin"><i class="fa-solid fa-arrow-right-to-bracket"></i> Pengembalian Sekarang</a>
        <?php endif; ?>

        <?php if (in_array($loan['status'], ['Approved','CheckedOut','Returned','Completed'], true) && role_is('admin_gudang','admin')): ?>
            <?php $isOpd = ($loan['loan_type'] ?? 'event') === 'opd'; ?>
            <a href="<?= BASE_PATH ?>/loans/<?= e($loan["uuid"]) ?>/berita-acara" target="_blank" class="btn btn-outline-navy" data-testid="btn-berita-acara">
                <i class="fa-solid fa-file-lines"></i> <?= $isOpd ? 'Berita Acara Serah Terima' : 'Berita Acara Keluar' ?>
            </a>
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
                <?php if (!empty($loan['start_time'])): ?>
                    <tr><td class="text-slate">Jam Acara</td><td><?= e(substr((string)$loan['start_time'], 0, 5)) ?></td></tr>
                <?php endif; ?>
                <tr><td class="text-slate">Lokasi</td><td><?= e($loan['event_location'] ?: '—') ?></td></tr>
                <tr><td class="text-slate">Personel</td><td>
                    <?php if (!empty($participants)): ?>
                        <?php foreach ($participants as $p): ?>
                            <div><?= e($p['name']) ?><?php if (!empty($p['unit_kerja'])): ?> <span class="small text-slate">· <?= e($p['unit_kerja']) ?></span><?php endif; ?></div>
                        <?php endforeach; ?>
                    <?php else: ?>—<?php endif; ?>
                </td></tr>
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
            <?php $canEditItems = in_array($loan['status'], ['Pending','Approved']) && (role_is('admin') || (int)$loan['requester_id'] === Auth::id()); ?>
            <?php if ($canEditItems && count($items) > 1): ?>
                <div class="hint-box mb-2"><i class="fa-solid fa-circle-info"></i><div>Anda dapat membatalkan salah satu alat tanpa membatalkan seluruh peminjaman. Jika hanya tersisa satu alat lalu dihapus, peminjaman akan dibatalkan otomatis.</div></div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-sb align-middle" data-testid="loan-items-table">
                    <?php $participantNames = !empty($participants) ? implode(', ', array_column($participants, 'name')) : ''; ?>
                    <thead><tr><th>#</th><th>Foto</th><th>Alat</th><th>Status Item</th><th>Peminjam</th><?php if ($canEditItems): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i => $it): $itPhoto = asset_photo_url($it['photo'] ?? null); ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <a href="<?= e($itPhoto) ?>" target="_blank" title="Lihat foto"><img src="<?= e($itPhoto) ?>" alt="Foto <?= e($it['asset_name']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0;background:#fff;"></a>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($it['asset_name']) ?></div>
                                <div class="text-slate small text-mono"><?= e($it['asset_code']) ?></div>
                                <?php if (!empty($it['category_name'])): ?><div class="text-slate small"><?= e($it['category_name']) ?></div><?php endif; ?>
                                <?php $bm = trim(($it['brand'] ?? '') . ' ' . ($it['model'] ?? '')); ?>
                                <?php if ($bm !== ''): ?><div class="text-slate small"><?= e($bm) ?></div><?php endif; ?>
                                <?php if (!empty($it['serial_number'])): ?><div class="text-slate small text-mono">SN: <?= e($it['serial_number']) ?></div><?php endif; ?>
                            </td>
                            <td><?= status_badge($it['item_status']) ?></td>
                            <td class="small">
                                <div><i class="fa-solid fa-user me-1 text-slate"></i><?= e($loan['requester_name']) ?></div>
                                <?php if ($participantNames !== ''): ?><div class="text-slate"><i class="fa-solid fa-users me-1"></i><?= e($participantNames) ?></div><?php endif; ?>
                            </td>
                            <?php if ($canEditItems): ?>
                            <td class="text-nowrap">
                                <?php if (in_array($it['item_status'], ['Reserved'])): ?>
                                    <form method="POST" action="<?= BASE_PATH ?>/loans/<?= e($loan['uuid']) ?>/items/<?= (int)$it['id'] ?>/remove" data-confirm="Batalkan alat &quot;<?= e($it['asset_name']) ?>&quot; dari peminjaman ini?" style="display:inline;">
                                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Batalkan alat ini" data-testid="btn-remove-item-<?= (int)$it['id'] ?>"><i class="fa-solid fa-xmark"></i> Batalkan</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate small">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php if ($loan['status'] === 'Pending' && role_is('supervisor','admin')): ?>
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_PATH ?>/loans/<?= e($loan["uuid"]) ?>/approve" data-testid="approve-form">
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
        <form method="POST" action="<?= BASE_PATH ?>/loans/<?= e($loan["uuid"]) ?>/reject" data-testid="reject-form">
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
