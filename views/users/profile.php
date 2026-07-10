<div class="page-header">
    <div>
        <h1>Profil Saya</h1>
        <p class="subtitle">Ubah password Anda di sini.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card-sb">
            <div class="card-title">Informasi Akun</div>
            <table class="table table-sm mb-0" style="table-layout: fixed; width: 100%;">
                <tr><td class="text-slate" style="width:38%;">Nama</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['name']) ?></td></tr>
                <tr><td class="text-slate">Email</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['email']) ?></td></tr>
                <tr><td class="text-slate">Role</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e(role_label($user['role'])) ?></td></tr>
                <tr><td class="text-slate">Unit Kerja</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['unit_kerja'] ?: '—') ?></td></tr>
                <tr><td class="text-slate">Telepon</td><td style="word-break:break-word; overflow-wrap:anywhere;"><?= e($user['phone'] ?: '—') ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card-sb">
            <div class="card-title">Ganti Password</div>
            <form method="POST" action="<?= BASE_PATH ?>/profile" data-testid="profile-form">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <div class="mb-3"><label class="form-label">Password Lama *</label><input type="password" name="old_password" class="form-control" required data-testid="input-old-password"></div>
                <div class="mb-3"><label class="form-label">Password Baru *</label><input type="password" name="new_password" class="form-control" required minlength="6" data-testid="input-new-password"></div>
                <button class="btn btn-primary" data-testid="btn-save-profile"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </form>
        </div>
    </div>
</div>
