<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK Perbaikan <?= e($repair['repair_code']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSET_PREFIX ?>/assets/css/app.css">
</head>
<body onload="window.print()">
<div class="no-print" style="text-align:right;padding:16px;">
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Cetak</button>
    <button onclick="window.close()" class="btn btn-outline-navy">Tutup</button>
</div>

<div class="spk-page">
    <div class="header">
        <div class="sub">PEMERINTAH KABUPATEN TANGERANG</div>
        <h1>DINAS KOMUNIKASI DAN INFORMATIKA</h1>
        <h2>Formulir Perbaikan Alat (SPK) — Aset BMN</h2>
        <div class="sub">Smart Building — Diskominfo Kabupaten Tangerang</div>
    </div>

    <table>
        <tr><td>No. SPK</td><td class="text-mono"><?= e($repair['repair_code']) ?></td></tr>
        <tr><td>Tanggal Cetak</td><td><?= fmt_datetime($repair['form_printed_at'] ?: date('Y-m-d H:i:s')) ?></td></tr>
        <tr><td>Nama Alat</td><td><strong><?= e($repair['asset_name']) ?></strong></td></tr>
        <tr><td>Kode Aset</td><td class="text-mono"><?= e($repair['asset_code']) ?></td></tr>
        <tr><td>Nomor BMN</td><td class="text-mono"><?= e($repair['bmn_number']) ?></td></tr>
        <tr><td>Brand / Model</td><td><?= e(trim($repair['brand'].' '.$repair['model'])) ?: '—' ?></td></tr>
        <tr><td>Serial Number</td><td class="text-mono"><?= e($repair['serial_number'] ?: '—') ?></td></tr>
        <tr><td>Kode Peminjaman Sumber</td><td class="text-mono"><?= e($repair['loan_code'] ?: '—') ?></td></tr>
        <tr><td>Pemohon Terakhir</td><td><?= e($repair['requester_name'] ?: '—') ?><?= $repair['requester_unit'] ? ' — '.e($repair['requester_unit']) : '' ?></td></tr>
    </table>

    <div style="font-weight:600;margin-bottom:6px;">Keluhan / Kerusakan Awal:</div>
    <div class="complaint-box"><?= nl2br(e($repair['complaint'])) ?></div>

    <div style="font-weight:600;margin-bottom:6px;">Tindakan Perbaikan (diisi Teknisi):</div>
    <div class="action-box">&nbsp;</div>

    <div class="signatures">
        <div class="box">
            <div>Diserahkan oleh,<br><strong>Admin Gudang</strong></div>
            <div class="space"></div>
            <div class="name">(…………………………………)</div>
        </div>
        <div class="box">
            <div>Diperbaiki oleh,<br><strong>Teknisi</strong></div>
            <div class="space"></div>
            <div class="name">(…………………………………)</div>
        </div>
    </div>

    <div style="margin-top:30px;font-size:11px;color:#64748B;">
        Formulir ini merupakan Surat Perintah Kerja (SPK) fisik untuk proses perbaikan. Setelah selesai, teknisi wajib
        mengisi <em>Tindakan Perbaikan</em>, menandatangani, dan mengembalikan alat + formulir ini ke Admin Gudang untuk
        pembaruan status pada sistem.
    </div>
</div>
</body>
</html>
