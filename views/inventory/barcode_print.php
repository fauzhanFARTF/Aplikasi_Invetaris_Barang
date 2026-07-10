<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Barcode Aset · <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_PREFIX ?>/assets/css/app.css">
    <style>
        body { background: #EEF2F8; }
        .toolbar { padding: 16px 20px; display: flex; justify-content: flex-end; gap: 10px; align-items: center; }
        .toolbar .hint { margin-right: auto; color: var(--sb-slate); font-size: 13px; }

        .label-sheet {
            max-width: 900px; margin: 0 auto 40px; padding: 0 16px;
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;
        }
        .barcode-label {
            background: #fff; border: 1.5px dashed #B9C3D4; border-radius: 10px;
            padding: 18px 14px; text-align: center; break-inside: avoid; page-break-inside: avoid;
        }
        .barcode-label .lbl-org { font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; color: #94A3B8; font-weight: 700; }
        .barcode-label .lbl-name { font-size: 15px; font-weight: 800; color: #0F172A; margin: 4px 0 2px; line-height: 1.25; min-height: 38px; }
        .barcode-label .lbl-bmn { font-size: 12px; color: #475569; font-family: 'JetBrains Mono', monospace; margin-bottom: 8px; }
        .barcode-label svg.code128 { width: 100%; height: auto; max-height: 70px; }
        .barcode-label .lbl-code-text { font-size: 12px; font-family: 'JetBrains Mono', monospace; font-weight: 700; letter-spacing: 0.04em; color: #0F172A; margin: 3px 0 10px; }
        .barcode-label .lbl-qr { display: flex; justify-content: center; margin-top: 6px; }
        .barcode-label .lbl-qr canvas { width: 130px !important; height: 130px !important; }
        .barcode-label .lbl-foot { font-size: 9.5px; color: #94A3B8; margin-top: 6px; }

        @media print {
            @page { size: A4; margin: 10mm; }
            body { background: #fff; }
            .label-sheet { grid-template-columns: repeat(2, 1fr); gap: 10px; max-width: 100%; }
            .barcode-label { border-style: solid; border-color: #D9DFE9; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <div class="hint"><i class="fa-solid fa-circle-info"></i>&nbsp; <?= count($assets) ?> label siap dicetak. Gunakan kertas label/sticker atau kertas biasa lalu gunting sesuai garis putus-putus.</div>
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Cetak</button>
    <button onclick="window.close()" class="btn btn-outline-navy">Tutup</button>
</div>

<div class="label-sheet" id="labelSheet">
    <?php foreach ($assets as $a): $code = $a['barcode'] ?: $a['bmn_number']; ?>
        <div class="barcode-label">
            <div class="lbl-org"><?= e(APP_NAME) ?></div>
            <div class="lbl-name"><?= e($a['name']) ?></div>
            <div class="lbl-bmn">BMN: <?= e($a['bmn_number']) ?> · <?= e($a['asset_code']) ?></div>
            <svg class="code128" data-code="<?= e($code) ?>"></svg>
            <div class="lbl-code-text"><?= e($code) ?></div>
            <div class="lbl-qr"><canvas data-code="<?= e($code) ?>"></canvas></div>
            <div class="lbl-foot">Pindai dengan kamera HP atau alat pemindai barcode</div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
    // Render setiap barcode Code128 (dipindai alat scanner genggam / kamera) dan QR (dipindai kamera HP)
    document.querySelectorAll('svg.code128').forEach(svg => {
        try {
            JsBarcode(svg, svg.dataset.code, { format: 'CODE128', displayValue: false, height: 40, margin: 0 });
        } catch (e) { console.error('Gagal membuat barcode:', e); }
    });
    document.querySelectorAll('canvas[data-code]').forEach(canvas => {
        QRCode.toCanvas(canvas, canvas.dataset.code, { width: 64, margin: 0 }, err => { if (err) console.error(err); });
    });
</script>
</body>
</html>
