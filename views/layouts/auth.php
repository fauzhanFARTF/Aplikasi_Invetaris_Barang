<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Masuk') ?> · <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_PREFIX ?>/assets/css/app.css">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_PREFIX ?>/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_PREFIX ?>/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ASSET_PREFIX ?>/assets/img/favicon-180.png">
    <link rel="shortcut icon" href="<?= ASSET_PREFIX ?>/assets/img/favicon.ico">
</head>
<body>
<div class="auth-page">
    <div class="auth-side no-print">
        <div class="side-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
        <h2>Kelola semua aset dinas dengan tenang, semua tercatat rapi.</h2>
        <p>SIMANTAP BMN membantu tim Diskominfo Kabupaten Tangerang meminjam, memeriksa, dan merawat berbagai barang milik negara — dari peralatan streaming, perangkat jaringan, hingga kendaraan dinas (mobil &amp; motor) — tanpa ribet, cukup beberapa klik.</p>
        <div class="side-feats">
            <div class="feat"><i class="fa-solid fa-qrcode"></i> Penyerahan / pengembalian alat cukup pindai barcode</div>
            <div class="feat"><i class="fa-solid fa-bell"></i> Notifikasi otomatis untuk setiap persetujuan</div>
            <div class="feat"><i class="fa-solid fa-shield-halved"></i> Riwayat & laporan aset selalu tersimpan aman</div>
        </div>
    </div>
    <div class="auth-form-col">
        <?= $content ?>
    </div>
</div>
</body>
</html>
