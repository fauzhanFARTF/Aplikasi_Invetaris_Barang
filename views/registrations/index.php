<div class="page-header">
    <div>
        <h1>Verifikasi Pendaftaran</h1>
        <p class="subtitle">Pendaftaran mandiri lewat akun Google yang menunggu persetujuan Administrator.</p>
    </div>
</div>

<div class="card-sb">
    <div class="hint-box">
        <i class="fa-solid fa-circle-info"></i>
        <div>Pendaftar <strong>belum bisa masuk</strong> sampai Anda menyetujui. Setelah disetujui, yang bersangkutan masuk lewat tombol <strong>Masuk dengan Google</strong> — mereka tidak punya password. Pendaftar yang ditolak tidak bisa masuk dan tidak bisa mendaftar ulang dengan akun Google yang sama.</div>
    </div>

    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="registrations-table">
            <thead><tr><th></th><th>Nama</th><th>Email</th><th>Role Diminta</th><th>Unit Kerja</th><th>Telepon</th><th>Mendaftar</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): $photoUrl = user_avatar_url($u['photo'] ?? null); ?>
                <tr data-testid="reg-row-<?= (int)$u['id'] ?>">
                    <td>
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($u['name']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:50%;border:1px solid #E2E8F0;">
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td class="small"><?= e($u['email']) ?></td>
                    <td><span class="badge bg-secondary"><?= e(role_label($u['role'])) ?></span></td>
                    <td class="small text-slate"><?= e($u['unit_kerja'] ?: '—') ?></td>
                    <td class="small"><?= e($u['phone'] ?: '—') ?></td>
                    <td class="small text-slate"><?= fmt_datetime($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['reg_status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Menunggu</span>
                        <?php else: ?>
                            <span class="badge bg-dark">Ditolak</span>
                            <?php if (!empty($u['verified_by_name'])): ?>
                                <div class="text-slate" style="font-size:11px;">oleh <?= e($u['verified_by_name']) ?><br><?= fmt_datetime($u['verified_at']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($u['reg_status'] === 'pending'): ?>
                            <form method="POST" action="<?= BASE_PATH ?>/registrations/<?= e($u['uuid']) ?>/approve" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-primary" data-confirm="Setujui pendaftaran <?= e($u['name']) ?> sebagai <?= e(role_label($u['role'])) ?>?" data-testid="btn-approve-<?= (int)$u['id'] ?>">
                                    <i class="fa-solid fa-check"></i> Setujui
                                </button>
                            </form>
                            <form method="POST" action="<?= BASE_PATH ?>/registrations/<?= e($u['uuid']) ?>/reject" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-outline-danger" data-confirm="Tolak pendaftaran <?= e($u['name']) ?>? Yang bersangkutan tidak akan bisa masuk." data-testid="btn-reject-<?= (int)$u['id'] ?>">
                                    <i class="fa-solid fa-xmark"></i> Tolak
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= BASE_PATH ?>/registrations/<?= e($u['uuid']) ?>/approve" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-outline-navy" data-confirm="Setujui ulang pendaftaran <?= e($u['name']) ?>?" data-testid="btn-reapprove-<?= (int)$u['id'] ?>">
                                    <i class="fa-solid fa-rotate-left"></i> Setujui Ulang
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-slate py-4">Tidak ada pendaftaran yang menunggu verifikasi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
