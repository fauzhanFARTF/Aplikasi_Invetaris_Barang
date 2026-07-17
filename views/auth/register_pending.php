<div class="auth-card" data-testid="register-pending-card">
    <div class="brand-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
    <h1>Menunggu Verifikasi</h1>
    <p class="sub">Pendaftaran Anda sudah kami terima.</p>

    <div class="alert alert-info text-start" data-testid="pending-message">
        <i class="fa-solid fa-hourglass-half me-2"></i>
        Administrator Diskominfo akan meninjau pendaftaran Anda terlebih dahulu.
        Setelah disetujui, Anda bisa langsung masuk memakai tombol
        <strong>Masuk dengan Google</strong> di halaman awal.
    </div>

    <p class="text-slate small">
        Belum disetujui juga? Silakan hubungi Administrator Diskominfo Kabupaten Tangerang.
    </p>

    <a href="<?= BASE_PATH ?>/login" class="btn btn-outline-navy w-100" data-testid="pending-back">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Masuk
    </a>
</div>
