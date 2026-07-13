<div class="auth-card" data-testid="login-card">
    <div class="brand-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
    <h1>Masuk ke SIMASSTA BMN</h1>
    <p class="sub">Sistem Informasi Manajemen Aset Streaming — Diskominfo Kabupaten Tangerang</p>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger" data-testid="login-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_PATH ?>/login" data-testid="login-form">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <div class="mb-3">
            <label class="form-label">Email Dinas</label>
            <input type="email" name="email" class="form-control" required autofocus placeholder="nama@diskominfo.tangerangkab.go.id" value="<?= old('email') ?>" data-testid="login-email">
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="loginPassword" class="form-control" required placeholder="Masukkan password Anda" data-testid="login-password">
                <button type="button" class="input-group-text" id="togglePassword" aria-label="Tampilkan password" style="cursor:pointer;">
                    <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100" data-testid="login-submit"><i class="fa-solid fa-right-to-bracket"></i> Masuk</button>
    </form>
    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function () {
            const input = document.getElementById('loginPassword');
            const icon = document.getElementById('togglePasswordIcon');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.className = showing ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        });
    </script>
</div>
<?php unset($_SESSION['_old']); ?>
