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

    <form method="POST" action="<?= BASE_PATH ?>/daftar" enctype="multipart/form-data" data-testid="register-form" class="text-start">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

        <div class="mb-3">
            <label class="form-label">Foto Profil</label>
            <div class="d-flex align-items-center gap-3">
                <img id="photoPreview" alt="Pratinjau foto"
                     src="<?= e($profile['picture'] ?: (ASSET_PREFIX . '/assets/img/logo-kominfo-icon.png')) ?>"
                     style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid var(--sb-border,#E2E8F0);background:#fff;flex-shrink:0;"
                     data-default="<?= e($profile['picture'] ?: (ASSET_PREFIX . '/assets/img/logo-kominfo-icon.png')) ?>"
                     data-testid="register-photo-preview">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex gap-2 flex-wrap">
                        <label class="btn btn-sm btn-outline-navy mb-0">
                            <i class="fa-solid fa-upload"></i> Upload Foto
                            <input type="file" name="photo" id="photoFile" accept="image/jpeg,image/png,image/webp" hidden data-testid="register-photo-file">
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-navy" id="btnCamera" data-testid="register-photo-camera">
                            <i class="fa-solid fa-camera"></i> Ambil dari Kamera
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-navy d-none" id="btnResetPhoto" data-testid="register-photo-reset">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </button>
                    </div>
                    <div class="form-text mb-0">JPG/PNG/WEBP, maksimal 3MB. Bila dikosongkan, foto akun Google yang dipakai.</div>
                </div>
            </div>

            <div id="cameraBox" class="mt-2 border rounded-3 p-2" style="display:none;">
                <video id="cameraVideo" autoplay playsinline muted style="width:100%;max-height:260px;border-radius:8px;background:#000;"></video>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-primary" id="btnCapture" data-testid="register-photo-capture"><i class="fa-solid fa-camera"></i> Jepret</button>
                    <button type="button" class="btn btn-sm btn-outline-navy" id="btnCloseCamera">Tutup</button>
                </div>
            </div>
            <div class="form-text text-danger" id="cameraErr" style="display:none;"></div>
            <input type="hidden" name="photo_camera" id="photoCamera">
        </div>

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

        // Foto profil: upload berkas ATAU jepret lewat kamera. Hasil jepretan
        // dikirim sebagai data URL base64 di input tersembunyi photo_camera,
        // supaya tidak bergantung pada dukungan DataTransfer di peramban.
        (function () {
            var preview = document.getElementById('photoPreview');
            var file    = document.getElementById('photoFile');
            var camInput= document.getElementById('photoCamera');
            var btnCam  = document.getElementById('btnCamera');
            var btnCap  = document.getElementById('btnCapture');
            var btnClose= document.getElementById('btnCloseCamera');
            var btnReset= document.getElementById('btnResetPhoto');
            var box     = document.getElementById('cameraBox');
            var video   = document.getElementById('cameraVideo');
            var errBox  = document.getElementById('cameraErr');
            if (!preview || !file || !camInput) return;
            var stream = null;

            function showErr(m) { errBox.textContent = m; errBox.style.display = m ? '' : 'none'; }
            function markChanged() { btnReset.classList.remove('d-none'); }
            function stopCam() {
                if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
                video.srcObject = null;
                box.style.display = 'none';
            }

            // Upload berkas -> pratinjau, batalkan foto kamera.
            file.addEventListener('change', function () {
                var f = file.files && file.files[0];
                if (!f) return;
                if (f.size > 3 * 1024 * 1024) { showErr('Ukuran foto maksimal 3MB.'); file.value = ''; return; }
                showErr('');
                camInput.value = '';
                var r = new FileReader();
                r.onload = function (e) { preview.src = e.target.result; markChanged(); };
                r.readAsDataURL(f);
            });

            btnCam.addEventListener('click', function () {
                showErr('');
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    showErr('Kamera tidak didukung peramban ini. Silakan pakai Upload Foto.');
                    return;
                }
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                    .then(function (s) { stream = s; video.srcObject = s; box.style.display = ''; })
                    .catch(function () {
                        // Paling sering: izin ditolak, atau halaman bukan HTTPS.
                        showErr('Tidak bisa membuka kamera. Pastikan izin kamera diberikan dan situs diakses lewat HTTPS. Anda tetap bisa memakai Upload Foto.');
                    });
            });

            btnCap.addEventListener('click', function () {
                if (!video.videoWidth) { showErr('Kamera belum siap, coba sesaat lagi.'); return; }
                // Perkecil ke maks 640px agar base64-nya ringan (jauh di bawah 3MB).
                var max = 640, w = video.videoWidth, h = video.videoHeight;
                var scale = Math.min(1, max / Math.max(w, h));
                var c = document.createElement('canvas');
                c.width = Math.round(w * scale); c.height = Math.round(h * scale);
                c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
                var data = c.toDataURL('image/jpeg', 0.85);
                camInput.value = data;
                preview.src = data;
                file.value = '';       // jepretan kamera menang atas berkas
                markChanged();
                showErr('');
                stopCam();
            });

            btnClose.addEventListener('click', stopCam);
            btnReset.addEventListener('click', function () {
                camInput.value = ''; file.value = '';
                preview.src = preview.dataset.default;
                btnReset.classList.add('d-none');
                showErr('');
                stopCam();
            });
            window.addEventListener('pagehide', stopCam);
        })();
    </script>
</div>
