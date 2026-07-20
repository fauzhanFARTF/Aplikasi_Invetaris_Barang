<?php $isArchive = $isArchive ?? false; $otherCount = $otherCount ?? 0; ?>
<div class="page-header">
    <div>
        <h1><?= $isArchive ? 'Arsip Notifikasi' : 'Notifikasi' ?></h1>
        <p class="subtitle">
            <?= $isArchive
                ? 'Notifikasi yang sudah disingkirkan dari kotak masuk. Tetap tersimpan dan bisa dikembalikan.'
                : 'Riwayat notifikasi in-app. Yang sudah tidak perlu bisa dipindahkan ke arsip.' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$isArchive): ?>
            <form method="POST" action="<?= BASE_PATH ?>/notifications/read-all">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn btn-outline-navy" data-testid="btn-read-all"><i class="fa-solid fa-check-double"></i> Tandai Semua Dibaca</button>
            </form>
            <?php if (!empty($notifs)): ?>
                <form method="POST" action="<?= BASE_PATH ?>/notifications/arsip-semua" data-confirm="Pindahkan SEMUA notifikasi di kotak masuk ke arsip?">
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <button class="btn btn-outline-navy" data-testid="btn-archive-all"><i class="fa-solid fa-box-archive"></i> Arsipkan Semua</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Tab: kotak masuk vs arsip -->
<div class="d-flex gap-2 mb-3" role="tablist" data-testid="notif-tabs">
    <a href="<?= BASE_PATH ?>/notifications"
       class="btn btn-sm <?= $isArchive ? 'btn-outline-navy' : 'btn-primary' ?>" data-testid="tab-inbox">
        <i class="fa-regular fa-bell"></i> Kotak Masuk
        <?php if ($isArchive && $otherCount): ?><span class="badge bg-light text-dark ms-1"><?= $otherCount ?></span><?php endif; ?>
    </a>
    <a href="<?= BASE_PATH ?>/notifications/arsip"
       class="btn btn-sm <?= $isArchive ? 'btn-primary' : 'btn-outline-navy' ?>" data-testid="tab-archive">
        <i class="fa-solid fa-box-archive"></i> Arsip
        <?php if (!$isArchive && $otherCount): ?><span class="badge bg-light text-dark ms-1"><?= $otherCount ?></span><?php endif; ?>
    </a>
</div>

<div class="card-sb" data-livetable>
    <?php if (empty($notifs)): ?>
        <div class="text-center text-slate py-5">
            <i class="fa-regular <?= $isArchive ? 'fa-folder-open' : 'fa-bell-slash' ?>" style="font-size:36px;"></i>
            <div class="mt-2"><?= $isArchive ? 'Belum ada notifikasi di arsip.' : 'Belum ada notifikasi.' ?></div>
        </div>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <div class="col-md-8"><input type="search" data-ls-search class="form-control" placeholder="Cari judul atau isi notifikasi..." autocomplete="off" data-testid="search-input"></div>
        </div>
        <?php foreach ($notifs as $n): ?>
            <div class="d-flex gap-3 p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>" data-testid="notif-<?= (int)$n['id'] ?>"
                 data-ls-row data-ls-read="<?= $n['is_read'] ? '1' : '0' ?>" data-ls-text="<?= e(strtolower($n['title'].' '.$n['body'])) ?>">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(245, 158, 11, 0.14);color:#B45309;display:grid;place-items:center;flex-shrink:0;">
                    <i class="fa-solid <?= $isArchive ? 'fa-box-archive' : 'fa-bell' ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <div class="fw-semibold"><?= e($n['title']) ?></div>
                        <div class="text-slate small"><?= fmt_datetime($n['created_at']) ?></div>
                    </div>
                    <div class="small text-slate mt-1"><?= nl2br(e($n['body'])) ?></div>
                    <?php if (!empty($n['archived_at'])): ?>
                        <div class="text-slate small mt-1"><i class="fa-solid fa-box-archive me-1"></i>Diarsipkan <?= fmt_datetime($n['archived_at']) ?></div>
                    <?php endif; ?>
                    <?php if ($n['link']): ?>
                        <a href="<?= e(url($n['link'])) ?>" class="btn btn-sm btn-outline-navy mt-2">Buka</a>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-column gap-1">
                    <?php if (!$isArchive): ?>
                        <?php if (!$n['is_read']): ?>
                            <form method="POST" action="<?= BASE_PATH ?>/notifications/<?= (int)$n['id'] ?>/read">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-outline-navy" title="Tandai dibaca"><i class="fa-regular fa-circle-check"></i></button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_PATH ?>/notifications/<?= (int)$n['id'] ?>/arsip">
                            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <button class="btn btn-sm btn-outline-navy" title="Pindahkan ke arsip" data-testid="btn-archive-notif-<?= (int)$n['id'] ?>"><i class="fa-solid fa-box-archive"></i></button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_PATH ?>/notifications/<?= (int)$n['id'] ?>/kembalikan">
                            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <button class="btn btn-sm btn-outline-navy" title="Kembalikan ke kotak masuk" data-testid="btn-unarchive-notif-<?= (int)$n['id'] ?>"><i class="fa-solid fa-rotate-left"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div data-ls-empty style="display:none;" class="text-center text-slate py-4">Tidak ada notifikasi yang cocok dengan pencarian.</div>
    <?php endif; ?>
</div>
