<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Keluar <?= e($loan['loan_code']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSET_PREFIX ?>/assets/css/app.css">
    <style>
        .ba-page { max-width: 820px; margin: 0 auto; padding: 28px 34px; color: #0F172A; font-size: 13px; }
        .ba-head { text-align: center; border-bottom: 3px double #0F172A; padding-bottom: 10px; margin-bottom: 4px; }
        .ba-head .logo { height: 64px; margin-bottom: 6px; }
        .ba-head .sub { font-size: 12px; letter-spacing: .5px; }
        .ba-head h1 { font-size: 18px; margin: 2px 0; font-weight: 800; }
        .ba-title { text-align: center; margin: 18px 0 4px; }
        .ba-title h2 { font-size: 15px; font-weight: 700; text-decoration: underline; margin: 0; }
        .ba-title .no { font-size: 12px; color: #334155; }
        .ba-meta td { padding: 3px 6px; vertical-align: top; font-size: 13px; }
        .ba-meta td:first-child { width: 190px; color: #334155; }
        table.items { width: 100%; border-collapse: collapse; margin: 10px 0 6px; }
        table.items th, table.items td { border: 1px solid #94A3B8; padding: 5px 7px; font-size: 12px; text-align: left; }
        table.items th { background: #F1F5F9; }
        .ba-sign { display: flex; justify-content: space-between; gap: 40px; margin-top: 36px; }
        .ba-sign .box { flex: 1; text-align: center; font-size: 13px; }
        .ba-sign .space { height: 70px; }
        .ba-sign .nm { font-weight: 700; text-decoration: underline; }
        .ba-note { margin-top: 22px; font-size: 11px; color: #475569; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print" style="text-align:right;padding:16px;max-width:820px;margin:0 auto;">
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Cetak / Simpan PDF</button>
    <button onclick="window.close()" class="btn btn-outline-navy">Tutup</button>
</div>

<div class="ba-page">
    <div class="ba-head">
        <img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo" class="logo">
        <div class="sub">PEMERINTAH KABUPATEN TANGERANG</div>
        <h1>DINAS KOMUNIKASI DAN INFORMATIKA</h1>
        <div class="sub">Smart Building — Diskominfo Kabupaten Tangerang</div>
    </div>

    <div class="ba-title">
        <h2>BERITA ACARA PEMINJAMAN / KELUAR ALAT</h2>
        <div class="no">Nomor: <?= e($loan['loan_code']) ?></div>
    </div>

    <p style="margin:12px 0;">Pada hari ini, <strong><?= e(date('d F Y')) ?></strong>, telah dilakukan serah terima peminjaman (keluar) alat inventaris BMN dengan rincian sebagai berikut:</p>

    <table class="ba-meta">
        <tr><td>Nama Acara</td><td>: <strong><?= e($loan['event_name']) ?></strong></td></tr>
        <tr><td>Lokasi Acara</td><td>: <?= e($loan['event_location'] ?: '—') ?></td></tr>
        <tr><td>Tanggal Kegiatan</td><td>: <?= fmt_date($loan['start_date']) ?> s/d <?= fmt_date($loan['end_date']) ?></td></tr>
        <?php if (!empty($loan['start_time'])): ?><tr><td>Jam Acara</td><td>: <?= e(substr((string)$loan['start_time'],0,5)) ?> WIB</td></tr><?php endif; ?>
        <tr><td>Peminjam (Penanggungjawab)</td><td>: <strong><?= e($loan['requester_name']) ?></strong><?= $loan['requester_unit'] ? ' — '.e($loan['requester_unit']) : '' ?><?= $loan['requester_phone'] ? ' ('.e($loan['requester_phone']).')' : '' ?></td></tr>
        <?php if (!empty($participants)): ?>
            <tr><td>Personel yang Terlibat</td><td>: <?= e(implode(', ', array_column($participants, 'name'))) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($loan['purpose'])): ?><tr><td>Tujuan / Keperluan</td><td>: <?= nl2br(e($loan['purpose'])) ?></td></tr><?php endif; ?>
    </table>

    <table class="items">
        <thead><tr><th style="width:32px;">No</th><th>Nama Alat</th><th>Kode Aset</th><th>No. BMN</th><th>Brand / Model</th><th>Serial Number</th></tr></thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($it['asset_name']) ?></td>
                    <td><?= e($it['asset_code']) ?></td>
                    <td><?= e($it['bmn_number']) ?></td>
                    <td><?= e(trim(($it['brand'] ?? '').' '.($it['model'] ?? ''))) ?: '—' ?></td>
                    <td><?= e($it['serial_number'] ?: '—') ?></td>
                </tr>
            <?php endforeach; if (empty($items)): ?>
                <tr><td colspan="6" style="text-align:center;">Tidak ada alat.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:10px;">Alat tersebut di atas dipinjam dalam kondisi baik dan lengkap, serta menjadi tanggung jawab peminjam selama masa peminjaman hingga dikembalikan.</p>

    <div class="ba-sign">
        <div class="box">
            <div>Yang Menyerahkan,<br><strong>Admin Gudang</strong></div>
            <div class="space"></div>
            <div class="nm"><?= e(Auth::user()['name'] ?? '(…………………………)') ?></div>
        </div>
        <div class="box">
            <div>Yang Menerima,<br><strong>Peminjam</strong></div>
            <div class="space"></div>
            <div class="nm"><?= e($loan['requester_name']) ?></div>
        </div>
    </div>

    <div class="ba-note">
        Dokumen ini dicetak dari Sistem Informasi Manajemen Aset (SIMANTAP BMN) Diskominfo Kabupaten Tangerang pada <?= e(date('d/m/Y H:i')) ?> WIB.
    </div>
</div>
</body>
</html>
