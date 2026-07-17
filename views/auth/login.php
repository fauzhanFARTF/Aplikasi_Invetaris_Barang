<div class="auth-card" data-testid="login-card">
    <div class="brand-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
    <h1>Masuk ke SIMANTAP</h1>
    <p class="sub">Sistem Informasi Manajemen Aset Terpadu — Diskominfo Kabupaten Tangerang</p>

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
        <?php if (turnstile_enabled()): ?>
            <div class="mb-3 d-flex justify-content-center">
                <div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>" data-theme="auto" data-testid="turnstile-widget"></div>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-lg w-100" data-testid="login-submit"><i class="fa-solid fa-right-to-bracket"></i> Masuk</button>
    </form>

    <?php if (Google::enabled()): ?>
        <div class="auth-divider"><span>atau</span></div>
        <a href="<?= BASE_PATH ?>/auth/google" class="btn btn-google btn-lg w-100" data-testid="login-google">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            Masuk dengan Google
        </a>
        <p class="text-slate small text-center mt-3 mb-0" data-testid="register-hint">
            Belum punya akun? Masuk dengan Google untuk mendaftar sebagai
            <strong>IT Staff</strong> atau <strong>Personel Luar</strong>.
            Pendaftaran Anda akan ditinjau Administrator terlebih dahulu.
        </p>
    <?php endif; ?>
    <?php if (turnstile_enabled()): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
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
