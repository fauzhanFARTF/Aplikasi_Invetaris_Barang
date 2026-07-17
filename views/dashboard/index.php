<?php $user = Auth::user(); ?>
<?php if (Auth::hasRole('pemohon','inventory_staff')): ?>
<div class="hint-box no-print">
    <i class="fa-solid fa-circle-info"></i>
    <div>Butuh alat untuk kegiatan? Klik <strong>"Ajukan Peminjaman"</strong> di kanan atas, isi formulir singkat, lalu tunggu persetujuan dari supervisor. Anda akan mendapat notifikasi begitu disetujui.</div>
</div>
<?php endif; ?>
<div class="page-header">
    <div>
        <h1>Selamat datang, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h1>
        <p class="subtitle">Ringkasan aset streaming & aktivitas peminjaman.</p>
    </div>
    <?php if (role_is('pemohon','inventory_staff','admin')): ?>
        <a href="<?= BASE_PATH ?>/loans/create" class="btn btn-amber" data-testid="btn-create-loan"><i class="fa-solid fa-plus"></i> Ajukan Peminjaman</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4" data-testid="stat-cards">
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-navy">
            <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="label">Total Aset</div>
            <div class="value" data-testid="stat-total"><?= $stats['total_assets'] ?></div>
            <div class="text-slate small">Alat aktif (non-retired)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-success">
            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="label">Tersedia</div>
            <div class="value" data-testid="stat-available"><?= $stats['available'] ?></div>
            <div class="text-slate small">Siap dipinjam</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-info">
            <div class="stat-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <div class="label">Sedang Dipinjam</div>
            <div class="value" data-testid="stat-checkedout"><?= $stats['checked_out'] ?></div>
            <div class="text-slate small">Di lapangan / studio</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-danger">
            <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="label">Dalam Perbaikan</div>
            <div class="value" data-testid="stat-damaged"><?= $stats['damaged'] ?></div>
            <div class="text-slate small">Menunggu teknisi</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-navy">
            <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="label">Barang Menunggu Approval</div>
            <div class="value" data-testid="stat-items-pending"><?= $stats['items_pending'] ?></div>
            <div class="text-slate small">Pada peminjaman berstatus Pending</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card hover-lift tone-success">
            <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
            <div class="label">Barang Disetujui</div>
            <div class="value" data-testid="stat-items-approved"><?= $stats['items_approved'] ?></div>
            <div class="text-slate small">Siap diserahkan</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-sb">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="card-title mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-slate"></i>Peminjaman Terbaru<?= in_array($user['role'], ['pemohon','inventory_staff'], true) ? ' Anda' : '' ?></div>
                <a href="<?= BASE_PATH ?>/loans" class="btn btn-sm btn-outline-navy">Lihat semua</a>
            </div>
            <?php if (empty($myLoans)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-regular fa-clipboard"></i></div>
                    <h4>Belum ada peminjaman</h4>
                    <p>Riwayat peminjaman Anda akan muncul di sini.</p>
                    <?php if (role_is('pemohon','inventory_staff','admin')): ?>
                        <a href="<?= BASE_PATH ?>/loans/create" class="btn btn-sm btn-amber"><i class="fa-solid fa-plus"></i> Ajukan Sekarang</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sb align-middle" data-testid="table-my-loans">
                        <thead><tr><th>Kode</th><th>Acara</th><th>Peminjam</th><th>Status</th><?php if (role_is('admin_gudang')): ?><th></th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($myLoans as $l): ?>
                            <tr>
                                <td class="code"><a href="<?= BASE_PATH ?>/loans/<?= e($l["uuid"]) ?>"><?= e($l['loan_code']) ?></a></td>
                                <td>
                                    <div class="fw-semibold"><?= e($l['event_name']) ?></div>
                                    <?php if (!empty($l['event_location'])): ?><div class="text-slate small"><i class="fa-solid fa-location-dot me-1"></i><?= e($l['event_location']) ?></div><?php endif; ?>
                                    <div class="text-slate small"><i class="fa-regular fa-calendar me-1"></i><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></div>
                                    <?php if (!empty($l['start_time'])): ?><div class="text-slate small"><i class="fa-regular fa-clock me-1"></i><?= e(substr((string)$l['start_time'],0,5)) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?= e($l['requester_name'] ?? '—') ?></div>
                                    <?php if (!empty($loanParticipants[$l['id']])): ?><div class="text-slate small"><i class="fa-solid fa-users me-1"></i><?= e($loanParticipants[$l['id']]) ?></div><?php endif; ?>
                                </td>
                                <td><?= status_badge($l['status']) ?></td>
                                <?php if (role_is('admin_gudang')): ?>
                                <td class="text-nowrap">
                                    <a href="<?= BASE_PATH ?>/loans/<?= e($l['uuid']) ?>/berita-acara" target="_blank" class="btn btn-sm btn-outline-navy" title="Cetak Berita Acara Keluar" data-testid="btn-berita-<?= (int)$l['id'] ?>"><i class="fa-solid fa-file-lines"></i> Berita Acara</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($showSchedule): // Personel Luar tidak melihat agenda dinas ?>
        <div class="card-sb">
            <div class="card-title"><i class="fa-solid fa-calendar-day me-2 text-slate"></i>Jadwal Hari Ini &amp; Selanjutnya</div>
            <?php if (empty($scheduleLoans)): ?>
                <div class="text-slate small">Tidak ada jadwal acara hari ini maupun mendatang.</div>
            <?php else: foreach ($scheduleLoans as $l): ?>
                <div class="d-flex align-items-start justify-content-between py-2 border-bottom gap-2">
                    <div class="min-w-0">
                        <div class="small text-mono"><a href="<?= BASE_PATH ?>/loans/<?= e($l['uuid']) ?>"><?= e($l['loan_code']) ?></a></div>
                        <div class="fw-semibold small"><?= e($l['event_name']) ?></div>
                        <?php if (!empty($l['event_location'])): ?><div class="text-slate small"><i class="fa-solid fa-location-dot me-1"></i><?= e($l['event_location']) ?></div><?php endif; ?>
                        <div class="text-slate small"><i class="fa-regular fa-calendar me-1"></i><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></div>
                        <?php if (!empty($l['start_time'])): ?><div class="text-slate small"><i class="fa-regular fa-clock me-1"></i><?= e(substr((string)$l['start_time'],0,5)) ?></div><?php endif; ?>
                        <div class="text-slate small"><i class="fa-solid fa-user me-1"></i><?= e($l['requester_name']) ?><?php if (!empty($loanParticipants[$l['id']])): ?> <span class="text-slate">· <i class="fa-solid fa-users me-1"></i><?= e($loanParticipants[$l['id']]) ?></span><?php endif; ?></div>
                    </div>
                    <?= status_badge($l['status']) ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="card-sb mt-3">
            <div class="card-title"><i class="fa-solid fa-calendar-check me-2 text-slate"></i>Jadwal yang Telah Lewat</div>
            <?php if (empty($pastLoans)): ?>
                <div class="text-slate small">Belum ada jadwal acara yang telah lewat.</div>
            <?php else: foreach ($pastLoans as $l): ?>
                <div class="d-flex align-items-start justify-content-between py-2 border-bottom gap-2">
                    <div class="min-w-0">
                        <div class="small text-mono"><a href="<?= BASE_PATH ?>/loans/<?= e($l['uuid']) ?>"><?= e($l['loan_code']) ?></a></div>
                        <div class="fw-semibold small"><?= e($l['event_name']) ?></div>
                        <?php if (!empty($l['event_location'])): ?><div class="text-slate small"><i class="fa-solid fa-location-dot me-1"></i><?= e($l['event_location']) ?></div><?php endif; ?>
                        <div class="text-slate small"><i class="fa-regular fa-calendar me-1"></i><?= fmt_date($l['start_date']) ?> — <?= fmt_date($l['end_date']) ?></div>
                        <?php if (!empty($l['start_time'])): ?><div class="text-slate small"><i class="fa-regular fa-clock me-1"></i><?= e(substr((string)$l['start_time'],0,5)) ?></div><?php endif; ?>
                        <div class="text-slate small"><i class="fa-solid fa-user me-1"></i><?= e($l['requester_name']) ?><?php if (!empty($loanParticipants[$l['id']])): ?> <span class="text-slate">· <i class="fa-solid fa-users me-1"></i><?= e($loanParticipants[$l['id']]) ?></span><?php endif; ?></div>
                    </div>
                    <?= status_badge($l['status']) ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; // showSchedule ?>
        <div class="card-sb<?= $showSchedule ? ' mt-3' : '' ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-title mb-0"><i class="fa-solid fa-screwdriver-wrench me-2 text-slate"></i>Perbaikan Aktif</div>
                <?php if (role_is('admin_gudang','admin')): ?>
                    <a href="<?= BASE_PATH ?>/repairs" class="btn btn-sm btn-outline-navy">Kelola</a>
                <?php endif; ?>
            </div>
            <?php if (empty($recentDamage)): ?>
                <div class="text-slate small">Tidak ada alat dalam perbaikan.</div>
            <?php else: foreach ($recentDamage as $r): ?>
                <div class="d-flex align-items-start justify-content-between py-2 border-bottom">
                    <div>
                        <div class="fw-semibold small"><?= e($r['asset_name']) ?></div>
                        <div class="text-slate small text-mono"><?= e($r['bmn_number']) ?></div>
                    </div>
                    <?= status_badge($r['status']) ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
