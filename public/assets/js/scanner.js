// Barcode/QR scanner wrapper using html5-qrcode CDN
// Loaded on-demand in scanner pages.
class BarcodeScanner {
    constructor(elementId, onScan) {
        this.elementId = elementId;
        this.onScan = onScan;
        this.scanner = null;
        this.lastCode = null;
        this.lastAt = 0;
    }
    async start() {
        this.scanner = new Html5Qrcode(this.elementId, {
            formatsToSupport: window.Html5QrcodeSupportedFormats ? [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
            ] : undefined,
            verbose: false,
        });
        const config = { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.333 };
        try {
            await this.scanner.start({ facingMode: 'environment' }, config, this._handle.bind(this), () => {});
        } catch (e) {
            // fallback to any camera
            const cams = await Html5Qrcode.getCameras();
            if (cams && cams.length) {
                await this.scanner.start(cams[0].id, config, this._handle.bind(this), () => {});
            } else { throw e; }
        }
    }
    _handle(text) {
        const now = Date.now();
        if (text === this.lastCode && now - this.lastAt < 2000) return; // debounce
        this.lastCode = text; this.lastAt = now;
        this.onScan(text);
    }
    async stop() {
        try { await this.scanner?.stop(); } catch(_) {}
    }
}
window.BarcodeScanner = BarcodeScanner;
