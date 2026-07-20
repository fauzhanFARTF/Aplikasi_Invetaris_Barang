<?php
/**
 * Kartu "Alat yang Masih Dipinjam" — dipakai di dashboard dan di halaman scan
 * admin gudang (penyerahan & pengembalian).
 *
 * Variabel yang dipakai:
 *   $borrowedItems    array hasil borrowed_items()
 *   $borrowedTitle    judul kartu (opsional)
 *   $borrowedSubtitle keterangan di bawah judul (opsional)
 *   $borrowedEmpty    teks bila kosong (opsional)
 */
$bTitle    = $borrowedTitle    ?? 'Alat yang Masih Dipinjam';
$bSubtitle = $borrowedSubtitle ?? 'Alat yang sudah keluar gudang dan belum dikembalikan, beserta penanggung jawab dan personel yang dilibatkan.';
$bEmpty    = $borrowedEmpty    ?? 'Tidak ada alat yang sedang dipinjam.';
?>
<div class="card-sb" data-testid="borrowed-items-card">
    <div class="card-title mb-1"><i class="fa-solid fa-people-carry-box me-2 text-slate"></i><?= e($bTitle) ?><?php if (!empty($borrowedItems)): ?> <span class="badge bg-primary align-middle"><?= count($borrowedItems) ?></span><?php endif; ?></div>
    <div class="text-slate small mb-2" style="margin-top:-4px;"><?= e($bSubtitle) ?></div>

    <?php if (empty($borrowedItems)): ?>
        <div class="text-slate small py-2"><?= e($bEmpty) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sb align-middle mb-0" data-testid="borrowed-items-table">
                <thead><tr><th>Alat</th><th>Penanggung Jawab</th><th>Personel Terlibat</th><th>Keperluan</th><th>Keluar</th></tr></thead>
                <tbody>
                <?php foreach ($borrowedItems as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold small"><?= e($b['asset_name']) ?></div>
                            <div class="text-slate small text-mono"><?= e($b['asset_code']) ?></div>
                        </td>
                        <td class="small"><i class="fa-solid fa-user me-1 text-slate"></i><?= e($b['requester_name']) ?></td>
                        <td class="small">
                            <?php if (!empty($b['personnel'])): ?>
                                <i class="fa-solid fa-users me-1 text-slate"></i><?= e($b['personnel']) ?>
                            <?php else: ?><span class="text-slate">—</span><?php endif; ?>
                        </td>
                        <td class="small">
                            <a href="<?= BASE_PATH ?>/loans/<?= e($b['loan_uuid']) ?>" class="text-decoration-none"><?= e($b['event_name']) ?></a>
                            <div class="text-slate text-mono" style="font-size:11px;"><?= e($b['loan_code']) ?><?= ($b['loan_type'] ?? 'event') === 'opd' ? ' · Kebutuhan Jaringan' : '' ?></div>
                        </td>
                        <td class="small">
                            <?= !empty($b['checkout_at']) ? fmt_date($b['checkout_at']) : '<span class="text-slate">—</span>' ?>
                            <?php $due = $b['expected_return_date'] ?: (($b['loan_type'] ?? 'event') === 'event' ? $b['end_date'] : null); ?>
                            <?php if (!empty($due) && $due < '2099-01-01'): ?>
                                <div class="text-slate" style="font-size:11px;">kembali <?= fmt_date($due) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
