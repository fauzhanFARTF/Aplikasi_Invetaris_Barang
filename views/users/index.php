<div class="page-header">
    <div>
        <h1>Manajemen User</h1>
        <p class="subtitle">Kelola akun pengguna sistem beserta hak akses (role).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/users/create" class="btn btn-amber" data-testid="btn-new-user"><i class="fa-solid fa-user-plus"></i> Tambah User</a>
        <?= reset_button('users', 'Reset User', 'RESET SEMUA user? Semua akun dihapus PERMANEN kecuali Super Admin. Peminjaman & notifikasi terkait ikut terhapus. Tindakan ini TIDAK BISA dibatalkan.') ?>
    </div>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-5"><input type="search" data-ls-search class="form-control" placeholder="Cari nama, email, atau unit kerja... (langsung tampil)" data-testid="search-input"></div>
        <div class="col-md-4">
            <select class="form-select" data-ls-filter="role">
                <option value="">— Semua Role —</option>
                <?php foreach (['superadmin','admin','admin_gudang','supervisor','pemohon'] as $r): ?>
                    <option value="<?= $r ?>"><?= e(role_label($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" data-ls-filter="active">
                <option value="">— Semua Status —</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="users-table">
            <thead><tr><th></th><th>Nama</th><th>Email</th><th>Role</th><th>Unit Kerja</th><th>Telepon</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $photoUrl = user_avatar_url($u['photo'] ?? null); ?>
                <tr data-ls-row data-ls-role="<?= e($u['role']) ?>" data-ls-active="<?= $u['is_active'] ? '1' : '0' ?>"
                    data-ls-text="<?= e(strtolower($u['name'].' '.$u['email'].' '.($u['unit_kerja'] ?? '').' '.($u['phone'] ?? ''))) ?>">
                    <td>
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($u['name']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:50%;border:1px solid #E2E8F0;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center text-slate" style="width:36px;height:36px;border-radius:50%;background:#F1F5F9;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td class="small"><?= e($u['email']) ?></td>
                    <td><span class="badge bg-secondary"><?= e(role_label($u['role'])) ?></span></td>
                    <td class="small text-slate"><?= e($u['unit_kerja'] ?: '—') ?></td>
                    <td class="small"><?= e($u['phone'] ?: '—') ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-dark">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php $canManage = $u['role'] !== 'superadmin' || Auth::role() === 'superadmin'; ?>
                        <?php if ($canManage): ?>
                            <a href="<?= BASE_PATH ?>/users/<?= (int)$u['id'] ?>/edit" class="btn btn-sm btn-outline-navy" data-testid="btn-edit-user-<?= (int)$u['id'] ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                            <form method="POST" action="<?= BASE_PATH ?>/users/<?= (int)$u['id'] ?>/toggle" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button class="btn btn-sm btn-outline-danger" data-confirm="Ubah status user ini?"><i class="fa-solid fa-power-off"></i></button>
                            </form>
                            <?php if ((int)$u['id'] !== Auth::id()): ?>
                                <form method="POST" action="<?= BASE_PATH ?>/users/<?= (int)$u['id'] ?>/delete" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Hapus user ini? (masih bisa dipulihkan lewat Riwayat Terhapus)" data-testid="btn-delete-user-<?= (int)$u['id'] ?>"><i class="fa-regular fa-trash-can"></i></button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate small"><i class="fa-solid fa-lock"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-ls-empty style="display:none;"><td colspan="8" class="text-center text-slate py-4">Tidak ada user yang cocok dengan pencarian / filter.</td></tr>
            </tbody>
        </table>
    </div>
</div>
