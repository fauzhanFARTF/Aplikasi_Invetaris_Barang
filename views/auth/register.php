<div class="auth-card" data-testid="register-card" style="max-width:520px;">
    <div class="brand-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
    <h1>Lengkapi Pendaftaran</h1>
    <p class="sub">Akun Google Anda dikenali. Lengkapi data berikut, lalu Administrator akan meninjau pendaftaran Anda.</p>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger" data-testid="register-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="d-flex align-items-center gap-3 p-2 mb-3 border rounded-3" data-testid="google-identity">
        <?php if (!empty($profile['picture'])): ?>
            <img src="<?= e($profile['picture']) ?>" alt="Foto akun Google" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
        <?php endif; ?>
        <div class="min-w-0 text-start">
            <div class="fw-semibold"><?= e($profile['name']) ?></div>
            <div class="text-slate small"><?= e($profile['email']) ?></div>
        </div>
    </div>

    <form method="POST" action="<?= BASE_PATH ?>/daftar" data-testid="register-form" class="text-start">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

        <div class="mb-3">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="name" required class="form-control" value="<?= e($profile['name']) ?>" data-testid="register-name">
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
            <div class="form-text">Diambil dari akun Google dan tidak bisa diubah.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Mendaftar sebagai *</label>
            <select name="role" required class="form-select" data-testid="register-role">
                <option value="">— Pilih —</option>
                <?php foreach (_register_roles() as $r): ?>
                    <option value="<?= $r ?>"><?= e(role_label($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <strong>IT Staff</strong> untuk petugas Diskominfo yang ikut menangani peralatan.
                <strong>Personel Luar</strong> untuk peminjam dari luar bidang.
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Telepon</label>
            <input type="text" name="phone" class="form-control" data-testid="register-phone" placeholder="mis. 0812-3456-7890">
        </div>

        <div class="mb-4">
            <label class="form-label">Unit Kerja</label>
            <select name="unit_kerja" id="unitKerjaSelect" class="form-select" data-testid="register-unit-kerja">
                <option value="">— Pilih —</option>
                <?php foreach (unit_kerja_options() as $uk): ?>
                    <option value="<?= e($uk) ?>"><?= e($uk) ?></option>
                <?php endforeach; ?>
                <option value="__other__">Lainnya…</option>
            </select>
            <input type="text" name="unit_kerja_other" id="unitKerjaOther" class="form-control mt-2"
                   placeholder="Tulis unit kerja" style="display:none;" data-testid="register-unit-kerja-other">
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" data-testid="register-submit">
            <i class="fa-solid fa-paper-plane"></i> Kirim Pendaftaran
        </button>
    </form>

    <p class="text-center mt-3 mb-0">
        <a href="<?= BASE_PATH ?>/login" class="small text-slate">Batal, kembali ke halaman masuk</a>
    </p>

    <script>
        // Sama seperti form Manajemen User: "Lainnya" memunculkan isian bebas.
        (function () {
            var sel = document.getElementById('unitKerjaSelect');
            var other = document.getElementById('unitKerjaOther');
            if (!sel || !other) return;
            function sync() {
                var isOther = sel.value === '__other__';
                other.style.display = isOther ? '' : 'none';
                other.required = isOther;
                if (!isOther) other.value = '';
            }
            sel.addEventListener('change', sync);
            sync();
        })();
    </script>
</div>
