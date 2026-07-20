<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Serah Terima <?= e($loan['loan_code']) ?></title>
    <link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
    <style>
        /* Halaman cetak: selalu terang (app.css sudah mengunci @media print). */
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
        .tag-hp { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; background: #FDE68A; color: #7C2D12; }
        .tag-pp { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; background: #E2E8F0; color: #334155; }
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
        <h2>BERITA ACARA SERAH TERIMA BARANG</h2>
        <div class="no">Nomor: <?= e($loan['loan_code']) ?></div>
    </div>

    <?php
        // Tanggal serah terima = saat barang keluar dari gudang (checkout_at),
        // bukan tanggal pengajuan. Bila belum diserahkan, pakai tanggal hari ini.
        $serahTanggal = !empty($loan['checkout_at']) ? date('d F Y', strtotime((string)$loan['checkout_at'])) : date('d F Y');
    ?>
    <p style="margin:12px 0;">Pada hari ini, <strong><?= e($serahTanggal) ?></strong>, telah dilakukan serah terima barang milik daerah dari <strong>Dinas Komunikasi dan Informatika Kabupaten Tangerang</strong> kepada instansi penerima berikut:</p>

    <table class="ba-meta">
        <tr><td>Instansi Penerima (OPD)</td><td>: <strong><?= e($loan['event_name']) ?></strong></td></tr>
        <tr><td>Penanggungjawab (Diskominfo)</td><td>: <strong><?= e($loan['requester_name']) ?></strong><?= $loan['requester_unit'] ? ' — '.e($loan['requester_unit']) : '' ?><?= $loan['requester_phone'] ? ' ('.e($loan['requester_phone']).')' : '' ?></td></tr>
        <?php if (!empty($participants)): ?>
            <tr><td>Personel Instalasi</td><td>: <?= e(implode(', ', array_column($participants, 'name'))) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($loan['purpose'])): ?><tr><td>Tujuan / Keperluan</td><td>: <?= nl2br(e($loan['purpose'])) ?></td></tr><?php endif; ?>
        <tr><td>Tanggal Serah Terima</td><td>: <?= e($serahTanggal) ?></td></tr>
        <?php $baWillReturn = (int)($loan['will_return'] ?? 1) === 1; ?>
        <tr><td>Status Barang</td><td>: <strong><?= $baWillReturn ? 'Dikembalikan — rencana ' . e(date('d F Y', strtotime((string)$loan['end_date']))) : 'Tetap di OPD (tanpa batas waktu)' ?></strong></td></tr>
    </table>

    <table class="items">
        <thead><tr><th style="width:32px;">No</th><th>Nama Barang</th><th>Kode Aset</th><th>No. BMD</th><th>Brand / Model</th><th>Serial Number</th><th style="width:90px;">Keterangan</th></tr></thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($it['asset_name']) ?></td>
                    <td><?= e($it['asset_code']) ?></td>
                    <td><?= e($it['bmn_number']) ?></td>
                    <td><?= e(trim(($it['brand'] ?? '').' '.($it['model'] ?? ''))) ?: '—' ?></td>
                    <td><?= e($it['serial_number'] ?: '—') ?></td>
                    <td><?= $baWillReturn ? '<span class="tag-pp">Dikembalikan</span>' : '<span class="tag-hp">Tetap di OPD</span>' ?></td>
                </tr>
            <?php endforeach; if (empty($items)): ?>
                <tr><td colspan="7" style="text-align:center;">Tidak ada barang.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:10px;">
        <?php if ($baWillReturn): ?>
            Seluruh barang di atas tetap menjadi milik Diskominfo Kabupaten Tangerang dan <strong>dikembalikan</strong> sesuai rencana pada <strong><?= e(date('d F Y', strtotime((string)$loan['end_date']))) ?></strong>.
        <?php else: ?>
            Seluruh barang di atas diserahkan untuk ditempatkan di instansi penerima dan <strong>tetap berada di OPD tanpa batas waktu</strong>.
        <?php endif; ?>
    </p>

    <div class="ba-sign">
        <div class="box">
            <div>Yang Menyerahkan,<br><strong>Diskominfo Kab. Tangerang</strong></div>
            <div class="space"></div>
            <div class="nm"><?= e(Auth::user()['name'] ?? '(…………………………)') ?></div>
        </div>
        <div class="box">
            <div>Yang Menerima,<br><strong><?= e($loan['event_name']) ?></strong></div>
            <div class="space"></div>
            <div class="nm">(…………………………)</div>
        </div>
    </div>

    <div class="ba-note">
        Dokumen ini dicetak dari Sistem Informasi Manajemen Aset (SIMANTAP) Diskominfo Kabupaten Tangerang pada <?= e(date('d/m/Y H:i')) ?> WIB.
    </div>
</div>
</body>
</html>
