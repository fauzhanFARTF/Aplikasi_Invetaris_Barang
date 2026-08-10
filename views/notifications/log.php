<div class="page-header">
    <div>
        <h1>Log Notifikasi</h1>
        <p class="subtitle">Seluruh notifikasi yang terkirim ke semua user — Staff Approval, Admin Gudang, Personel Luar (peminjaman), dsb — baik ke Kotak Masuk web maupun Telegram.</p>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="hint-box">
        <i class="fa-solid fa-circle-info"></i>
        <div>Kolom <strong>Kanal</strong> menandakan Telegram bila Chat ID penerima sudah terisi saat notifikasi dibuat (kanal itu dicoba dikirim) — bukan konfirmasi bahwa Telegram-nya berhasil diterima. Menampilkan 300 notifikasi terbaru.</div>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-md-5"><input type="search" data-ls-search class="form-control" placeholder="Cari judul, isi, atau nama penerima... (langsung tampil)" autocomplete="off" data-testid="search-input"></div>
        <div class="col-md-4">
            <select class="form-select" data-ls-filter="role" data-testid="filter-role">
                <option value="">— Semua Role Penerima —</option>
                <?php foreach (['superadmin','admin','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan','pemohon'] as $r): ?>
                    <option value="<?= $r ?>"><?= e(role_label($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" data-ls-filter="telegram" data-testid="filter-telegram">
                <option value="">— Semua Kanal —</option>
                <option value="1">Web + Telegram</option>
                <option value="0">Web saja</option>
            </select>
        </div>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="text-center text-slate py-5">
            <i class="fa-regular fa-bell-slash" style="font-size:36px;"></i>
            <div class="mt-2">Belum ada notifikasi.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sb align-middle" data-testid="notif-log-table">
                <thead><tr>
                    <th>Waktu</th><th>Penerima</th><th>Judul</th><th>Isi</th><th>Kanal</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($notifs as $n): $hasTelegram = !empty($n['telegram_chat_id']); ?>
                    <tr data-ls-row data-testid="notif-log-<?= (int)$n['id'] ?>"
                        data-ls-text="<?= e(strtolower($n['title'].' '.$n['body'].' '.$n['user_name'])) ?>"
                        data-ls-role="<?= e($n['user_role']) ?>"
                        data-ls-telegram="<?= $hasTelegram ? '1' : '0' ?>">
                        <td class="text-slate small text-nowrap"><?= fmt_datetime($n['created_at']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($n['user_name']) ?></div>
                            <div class="text-slate small"><?= e(role_label($n['user_role'])) ?></div>
                        </td>
                        <td><?= e($n['title']) ?></td>
                        <td class="small text-slate" style="max-width:320px;"><?= e(mb_strimwidth($n['body'] ?? '', 0, 140, '…')) ?></td>
                        <td>
                            <span class="badge bg-secondary">Web</span>
                            <?php if ($hasTelegram): ?><span class="badge bg-info text-dark">Telegram</span><?php endif; ?>
                        </td>
                        <td><?= $n['is_read'] ? '<span class="text-slate small">Dibaca</span>' : '<span class="badge bg-warning text-dark">Belum dibaca</span>' ?></td>
                        <td><?php if ($n['link']): ?><a href="<?= e(url($n['link'])) ?>" class="btn btn-sm btn-outline-navy">Buka</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-ls-empty style="display:none;" class="text-center text-slate py-4">Tidak ada notifikasi yang cocok dengan filter.</div>
    <?php endif; ?>
</div>
