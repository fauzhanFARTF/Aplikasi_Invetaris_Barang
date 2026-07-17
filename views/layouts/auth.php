<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Masuk') ?> · <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset_url("/assets/css/app.css") ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_PREFIX ?>/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_PREFIX ?>/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ASSET_PREFIX ?>/assets/img/favicon-180.png">
    <link rel="shortcut icon" href="<?= ASSET_PREFIX ?>/assets/img/favicon.ico">
</head>
<body>
<div class="auth-page">
    <div class="auth-side no-print">
        <div id="particles-js" aria-hidden="true"></div>
        <div class="side-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
        <h2>Kelola semua aset dinas dengan tenang, semua tercatat rapi.</h2>
        <p>SIMANTAP membantu tim Diskominfo Kabupaten Tangerang meminjam, memeriksa, dan merawat berbagai barang milik negara — dari peralatan streaming, perangkat jaringan, hingga kendaraan dinas (mobil &amp; motor) — tanpa ribet, cukup beberapa klik.</p>
        <div class="side-feats">
            <div class="feat"><i class="fa-solid fa-qrcode"></i> Penyerahan / pengembalian alat cukup pindai QR code</div>
            <div class="feat"><i class="fa-solid fa-bell"></i> Notifikasi otomatis untuk setiap persetujuan</div>
            <div class="feat"><i class="fa-solid fa-shield-halved"></i> Riwayat & laporan aset selalu tersimpan aman</div>
        </div>
    </div>
    <div class="auth-form-col">
        <?= $content ?>
    </div>
</div>

<script src="<?= asset_url("/assets/js/particles.min.js") ?>" defer></script>
<script>
    // Partikel di panel biru. Sengaja dijaga ringan — halaman login adalah
    // halaman pertama yang dibuka orang, sering dari HP kentang di lapangan.
    window.addEventListener('load', function () {
        // 1) Panel biru di-display:none pada layar <=900px, jadi di HP partikelnya
        //    tidak dibuat sama sekali. Ini penghematan terbesar: tidak ada kanvas,
        //    tidak ada requestAnimationFrame, tidak ada beban baterai.
        if (!window.matchMedia('(min-width: 901px)').matches) return;
        // 2) Hormati pengguna yang meminta animasi dikurangi.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        if (typeof particlesJS !== 'function') return;

        particlesJS('particles-js', {
            particles: {
                // 46 -> sekitar 27 partikel nyata di panel ini (density menghitung ulang
                // berdasarkan luas kanvas). Bukan 50: jumlah garis penghubung tumbuh kuadratik terhadap
                // jumlah partikel, jadi di sinilah biaya sebenarnya berada.
                number: { value: 46, density: { enable: true, value_area: 900 } },
                // Putih & emas — mengikuti identitas SIMANTAP. Warna-warni asli
                // (ungu/hijau/merah) bertabrakan dengan panel navy.
                color: { value: ['#FFFFFF', '#FFDD87', '#F5B301'] },
                shape: { type: 'circle' },
                // random: false — di particles.js, random: true mengacak opacity dari
                // 0 sampai nilai ini, jadi sebagian partikel nyaris tak terlihat dan
                // keseluruhannya terkesan redup. Nilai tetap 0.8 membuat semuanya
                // terang merata.
                opacity: { value: 0.8, random: false, anim: { enable: false } },
                size: { value: 2.6, random: true, anim: { enable: false } },
                line_linked: { enable: true, distance: 130, color: '#8FB6F0', opacity: 0.34, width: 1 },
                // Melayang pelan (1.1, bukan 6) supaya terasa tenang, bukan gelisah.
                move: { enable: true, speed: 1.1, direction: 'none', random: true, straight: false, out_mode: 'out', bounce: false }
            },
            // 3) Interaktivitas dimatikan total. Hover-repulse memaksa hitung jarak
            //    mouse ke SETIAP partikel tiap frame, dan click-push menambah
            //    partikel terus-menerus sampai berat. Di halaman login tidak ada
            //    gunanya.
            interactivity: { detect_on: 'canvas', events: { onhover: { enable: false }, onclick: { enable: false }, resize: true } },
            // 4) retina_detect: false. Kalau true, di layar 2x kanvasnya digambar
            //    4x lebih banyak piksel — ini penghemat terbesar kedua setelah
            //    tidak jalan di HP.
            retina_detect: false
        });
    });
</script>
</body>
</html>
