// SIMASSTA BMN — Live Search & Live Filter (generic, client-side, no reload)
//
// Cara pakai di view:
//   <div data-livetable>
//       <input type="search" data-ls-search placeholder="Cari...">
//       <select data-ls-filter="status"> ... </select>                (dropdown)
//       <button data-ls-filter="status" data-ls-value="Pending">...</button>  (tombol pill)
//       ...
//       <table>
//         <tbody>
//           <tr data-ls-row data-ls-text="teks pencarian lowercase" data-ls-status="Pending"> ... </tr>
//         </tbody>
//       </table>
//       <tr data-ls-empty style="display:none"><td>Tidak ada hasil yang cocok.</td></tr>
//       <div data-ls-count></div>   (opsional, menampilkan jumlah baris tampil)
//   </div>
//
// Semua pencarian & filter berjalan langsung di browser (tanpa reload halaman / request server),
// cocok untuk daftar yang datanya sudah dirender di halaman.
//
// CATATAN IMPLEMENTASI (kenapa pakai event delegation + query ulang tiap "apply"):
// Versi sebelumnya meng-cache referensi <input>/<select>/baris pada saat inisialisasi
// (DOMContentLoaded). Kalau container ini di-render belakangan, atau elemen di dalamnya
// sempat diganti/di-refresh oleh script lain setelah DOMContentLoaded (mis. widget lain,
// partial reload, dsb), referensi yang di-cache jadi basi -> input yang diketik user sudah
// bukan node yang sama dengan yang didengarkan listener, sehingga live search terlihat
// "diam" walau tidak ada error di console. Supaya tahan terhadap kasus itu, versi ini:
//   1. Mendengarkan event di level document (delegation), bukan di elemen spesifik.
//   2. Selalu mencari ulang baris (data-ls-row) dari DOM setiap kali filter dijalankan,
//      bukan dari array yang di-cache sekali di awal.
//   3. Membungkus tiap proses filter per-container dengan try/catch supaya error di satu
//      container tidak menghentikan container lain di halaman yang sama.

(function () {
    function debounce(fn, delay) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // state per container: Map<containerEl, {field: value}>
    const stateMap = new WeakMap();

    function getState(container) {
        let s = stateMap.get(container);
        if (!s) { s = {}; stateMap.set(container, s); }
        return s;
    }

    function applyContainer(container) {
        try {
            const searchInput = container.querySelector('[data-ls-search]');
            const filterEls = Array.from(container.querySelectorAll('[data-ls-filter]'));
            const rows = Array.from(container.querySelectorAll('[data-ls-row]'));
            const emptyEl = container.querySelector('[data-ls-empty]');
            const countEl = container.querySelector('[data-ls-count]');
            const state = getState(container);

            // Sinkronkan state dari elemen filter saat ini (select/pill aktif)
            filterEls.forEach(el => {
                const field = el.getAttribute('data-ls-filter');
                if (el.tagName === 'SELECT') {
                    if (!(field in state)) state[field] = el.value || '';
                } else if (el.classList.contains('btn-primary') && !(field in state)) {
                    state[field] = el.getAttribute('data-ls-value') || '';
                }
            });

            const q = (searchInput?.value || '').trim().toLowerCase();
            const terms = q.split(/\s+/).filter(Boolean);
            let visible = 0;

            rows.forEach(row => {
                let match = true;

                if (terms.length) {
                    const text = (row.getAttribute('data-ls-text') || row.textContent || '').toLowerCase();
                    if (!terms.every(t => text.includes(t))) match = false;
                }

                if (match) {
                    for (const field in state) {
                        const val = state[field];
                        if (val === '' || val === undefined || val === null) continue;
                        const rowVal = row.getAttribute('data-ls-' + field) ?? '';
                        if (rowVal !== val) { match = false; break; }
                    }
                }

                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (emptyEl) {
                emptyEl.style.display = (rows.length > 0 && visible === 0) ? '' : 'none';
            }
            if (countEl) {
                countEl.textContent = visible;
            }
            container.dispatchEvent(new CustomEvent('ls:changed', { detail: { visible, total: rows.length } }));
        } catch (err) {
            console.error('[live-search] gagal memfilter container:', container, err);
        }
    }

    const debouncedApply = debounce(applyContainer, 120);

    function closestLiveTable(el) {
        return el.closest ? el.closest('[data-livetable]') : null;
    }

    // --- Event delegation di level document: tahan terhadap DOM yang berubah/di-replace ---
    document.addEventListener('input', ev => {
        const target = ev.target;
        if (!target.matches || !target.matches('[data-ls-search]')) return;
        const container = closestLiveTable(target);
        if (container) debouncedApply(container);
    });

    document.addEventListener('search', ev => {
        const target = ev.target;
        if (!target.matches || !target.matches('[data-ls-search]')) return;
        const container = closestLiveTable(target);
        if (container) applyContainer(container);
    });

    document.addEventListener('change', ev => {
        const target = ev.target;
        if (!target.matches || !target.matches('[data-ls-filter]') || target.tagName !== 'SELECT') return;
        const container = closestLiveTable(target);
        if (!container) return;
        const field = target.getAttribute('data-ls-filter');
        getState(container)[field] = target.value || '';
        applyContainer(container);
    });

    document.addEventListener('click', ev => {
        const target = ev.target.closest ? ev.target.closest('[data-ls-filter]') : null;
        if (!target || target.tagName === 'SELECT') return;
        const container = closestLiveTable(target);
        if (!container) return;
        ev.preventDefault();
        const field = target.getAttribute('data-ls-filter');
        const value = target.getAttribute('data-ls-value') || '';
        getState(container)[field] = value;
        container.querySelectorAll('[data-ls-filter="' + field + '"]').forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-navy');
        });
        target.classList.remove('btn-outline-navy');
        target.classList.add('btn-primary');
        applyContainer(container);
    });

    function scanAndInit() {
        document.querySelectorAll('[data-livetable]').forEach(applyContainer);
    }

    // Jalankan begitu DOM siap. Kalau script ini ternyata baru attach SETELAH
    // DOMContentLoaded sudah lewat (mis. dimuat belakangan/async), tetap jalan
    // langsung karena document.readyState sudah bukan 'loading'.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scanAndInit);
    } else {
        scanAndInit();
    }
    // Jaga-jaga: jalankan sekali lagi saat window 'load' (semua resource selesai),
    // tidak berbahaya karena applyContainer aman dipanggil berkali-kali (idempotent).
    window.addEventListener('load', scanAndInit);

    // Kalau ada container/baris yang muncul belakangan (mis. hasil AJAX), otomatis
    // ikut ter-filter ulang tanpa perlu memanggil apa pun secara manual.
    if (window.MutationObserver) {
        const observer = new MutationObserver(muts => {
            for (const m of muts) {
                if (m.addedNodes && m.addedNodes.length) { scanAndInit(); break; }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Tetap diekspos untuk kompatibilitas kalau ada kode lain yang memanggilnya manual.
    window.initLiveTables = scanAndInit;
})();
