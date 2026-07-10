<div class="page-header">
    <div>
        <h1>Notifikasi</h1>
        <p class="subtitle">Riwayat notifikasi in-app.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="<?= BASE_PATH ?>/notifications/read-all">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn btn-outline-navy" data-testid="btn-read-all"><i class="fa-solid fa-check-double"></i> Tandai Semua Dibaca</button>
        </form>
        <?php if (!empty($notifs)): ?>
            <!-- <form method="POST" action="<?= BASE_PATH ?>/notifications/delete-all" data-confirm="Hapus SEMUA riwayat notifikasi Anda? Tindakan ini tidak dapat dibatalkan.">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn btn-outline-navy text-danger" data-testid="btn-delete-all-notifs"><i class="fa-solid fa-trash"></i> Hapus Semua Riwayat</button>
            </form> -->
        <?php endif; ?>
    </div>
</div>

<div class="card-sb" data-livetable>
    <?php if (empty($notifs)): ?>
        <div class="text-center text-slate py-5"><i class="fa-regular fa-bell-slash" style="font-size:36px;"></i><div class="mt-2">Belum ada notifikasi.</div></div>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <!-- <div class="col-md-8"><input type="search" data-ls-search class="form-control" placeholder="Cari judul atau isi notifikasi... (langsung tampil)" data-testid="search-input"></div>
            <div class="col-md-4">
                <select class="form-select" data-ls-filter="read">
                    <option value="">— Semua —</option>
                    <option value="0">Belum Dibaca</option>
                    <option value="1">Sudah Dibaca</option>
                </select>
            </div> -->
        </div>
        <?php foreach ($notifs as $n): ?>
            <div class="d-flex gap-3 p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>" data-testid="notif-<?= (int)$n['id'] ?>"
                 data-ls-row data-ls-read="<?= $n['is_read'] ? '1' : '0' ?>" data-ls-text="<?= e(strtolower($n['title'].' '.$n['body'])) ?>">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(245, 158, 11, 0.14);color:#B45309;display:grid;place-items:center;flex-shrink:0;"><i class="fa-solid fa-bell"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <div class="fw-semibold"><?= e($n['title']) ?></div>
                        <div class="text-slate small"><?= fmt_datetime($n['created_at']) ?></div>
                    </div>
                    <div class="small text-slate mt-1"><?= nl2br(e($n['body'])) ?></div>
                    <?php if ($n['link']): ?>
                        <a href="<?= e(url($n['link'])) ?>" class="btn btn-sm btn-outline-navy mt-2">Buka</a>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-column gap-1">
                    <?php if (!$n['is_read']): ?>
                        <form method="POST" action="<?= BASE_PATH ?>/notifications/<?= (int)$n['id'] ?>/read"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-sm btn-outline-navy" title="Tandai dibaca"><i class="fa-regular fa-circle-check"></i></button></form>
                    <?php endif; ?>
                    <!-- <form method="POST" action="<?= BASE_PATH ?>/notifications/<?= (int)$n['id'] ?>/delete" data-confirm="Hapus notifikasi ini?">
                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                        <button class="btn btn-sm btn-outline-navy text-danger" title="Hapus" data-testid="btn-delete-notif-<?= (int)$n['id'] ?>"><i class="fa-solid fa-trash"></i></button>
                    </form> -->
                </div>
            </div>
        <?php endforeach; ?>
        <div data-ls-empty style="display:none;" class="text-center text-slate py-4">Tidak ada notifikasi yang cocok dengan pencarian / filter.</div>
    <?php endif; ?>
</div>
