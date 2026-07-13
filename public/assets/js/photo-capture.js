// Reusable "choose file" + "ambil foto dari kamera" untuk form foto (aset & user).
// Dipakai di views/inventory/form.php dan views/users/form.php.
function initPhotoCapture(opts) {
    var input = document.getElementById(opts.inputId);
    var wrap = document.getElementById(opts.previewWrapId);
    var img = document.getElementById(opts.previewImgId);
    var removeCheck = opts.removeCheckId ? document.getElementById(opts.removeCheckId) : null;
    if (!input) return;

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            wrap.style.display = '';
            if (removeCheck) removeCheck.checked = false; // pilih file baru membatalkan "hapus foto"
        };
        reader.readAsDataURL(file);
    });

    var btnOpen = document.getElementById(opts.openBtnId);
    if (!btnOpen) return;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Di origin http:// biasa (mis. http://172.16.64.250) browser MEMBLOKIR akses
        // kamera demi keamanan, sehingga navigator.mediaDevices tidak tersedia. Kamera
        // hanya aktif di https:// atau di localhost. Beri pesan yang tepat, bukan
        // sekadar "tidak didukung browser".
        btnOpen.disabled = true;
        btnOpen.title = window.isSecureContext
            ? 'Kamera tidak didukung di browser ini.'
            : 'Kamera butuh HTTPS. Buka aplikasi lewat https:// (atau localhost) agar bisa ambil foto. Sementara ini gunakan pilih file.';
        return;
    }

    var btnCapture = document.getElementById(opts.captureBtnId);
    var btnClose = document.getElementById(opts.closeBtnId);
    var panel = document.getElementById(opts.panelId);
    var video = document.getElementById(opts.videoId);
    var stream = null;

    function stopCamera() {
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        panel.style.display = 'none';
    }

    btnOpen.addEventListener('click', async function () {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: opts.facingMode || 'environment' } });
            video.srcObject = stream;
            panel.style.display = '';
        } catch (e) {
            if (window.toast) toast('Tidak bisa mengakses kamera: ' + e.message, 'error');
        }
    });

    btnClose.addEventListener('click', stopCamera);

    btnCapture.addEventListener('click', function () {
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
            if (!blob) return;
            var file = new File([blob], 'kamera_' + Date.now() + '.jpg', { type: 'image/jpeg' });
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
            stopCamera();
        }, 'image/jpeg', 0.9);
    });
}
window.initPhotoCapture = initPhotoCapture;
