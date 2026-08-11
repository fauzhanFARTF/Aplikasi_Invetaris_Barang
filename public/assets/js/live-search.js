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
// SORTING: kolom mana pun otomatis bisa diurutkan dengan klik header <th>-nya —
// tidak perlu markup tambahan di baris. Header kosong (mis. kolom checkbox atau
// kolom aksi tanpa judul) otomatis dilewati. Diurutkan berdasarkan isi <td> pada
// kolom yang sama: dikenali sebagai tanggal ("10 Mar 2026[, 14:30]"), angka/rupiah
// ("Rp 1.500.000"), atau teks biasa (natural sort, angka di dalam teks diurutkan
// sesuai nilainya). Sel kosong/"—" selalu di akhir, berapa pun arah urutannya. Bisa
// dipaksa pakai nilai tertentu lewat atribut data-sort="..." di <td> kalau format
// tampilannya tidak bisa ditebak otomatis.
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

    // ---- Sort tabel dari header kolom manapun (klik <th>) ----
    // state sort per <table>: Map<tableEl, {colIndex, dir}>
    const sortStateMap = new WeakMap();
    const MONTHS_ID = { jan: 0, feb: 1, mar: 2, apr: 3, mei: 4, may: 4, jun: 5, jul: 6, agu: 7, aug: 7, sep: 8, okt: 9, oct: 9, nov: 10, des: 11, dec: 11 };

    function parseDateCell(raw) {
        const m = raw.match(/^(\d{1,2})\s+([A-Za-z]{3,})\s+(\d{4})(?:[,\s]+(\d{1,2}):(\d{2}))?$/);
        if (!m) return null;
        const mon = MONTHS_ID[m[2].toLowerCase().slice(0, 3)];
        if (mon === undefined) return null;
        return Date.UTC(parseInt(m[3], 10), mon, parseInt(m[1], 10), m[4] ? parseInt(m[4], 10) : 0, m[5] ? parseInt(m[5], 10) : 0);
    }

    function parseNumberCell(raw) {
        const s = raw.replace(/^Rp\.?\s?/i, '').trim();
        if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
        if (/^-?\d+,\d+$/.test(s)) return parseFloat(s.replace(',', '.'));
        if (/^-?\d+(\.\d+)?$/.test(s)) return parseFloat(s);
        return null;
    }

    // rank 0 = nilai terparsir (angka/tanggal, dibandingkan numerik), rank 1 = teks
    // biasa (natural sort), rank 2 = kosong/"—" — selalu di akhir apa pun arahnya.
    function sortCellValue(td) {
        const raw = (td?.getAttribute('data-sort') ?? td?.textContent ?? '').trim();
        if (raw === '' || raw === '—') return { rank: 2, num: 0, text: '' };
        const d = parseDateCell(raw);
        if (d !== null) return { rank: 0, num: d, text: raw.toLowerCase() };
        const n = parseNumberCell(raw);
        if (n !== null) return { rank: 0, num: n, text: raw.toLowerCase() };
        return { rank: 1, num: 0, text: raw.toLowerCase() };
    }

    function applySort(table) {
        if (!table) return;
        const state = sortStateMap.get(table);
        if (!state) return;
        const tbody = table.tBodies[0];
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll(':scope > tr[data-ls-row]'));
        const dirMul = state.dir === 'desc' ? -1 : 1;
        rows.sort((ra, rb) => {
            const a = sortCellValue(ra.children[state.colIndex]);
            const b = sortCellValue(rb.children[state.colIndex]);
            if (a.rank !== b.rank) return a.rank - b.rank; // kosong tetap di akhir, tak terpengaruh arah
            const cmp = a.rank === 0 ? (a.num - b.num) : a.text.localeCompare(b.text, 'id', { numeric: true, sensitivity: 'base' });
            return cmp * dirMul;
        });
        const anchor = tbody.querySelector(':scope > tr[data-ls-empty]') || null;
        rows.forEach(row => tbody.insertBefore(row, anchor));

        // Perbarui indikator panah di header — cuma kolom aktif yang menampilkannya.
        table.querySelectorAll('thead th[data-ls-sortable] [data-ls-sort-icon]').forEach(el => el.remove());
        const activeTh = table.querySelectorAll('thead th')[state.colIndex];
        if (activeTh) {
            const icon = document.createElement('i');
            icon.setAttribute('data-ls-sort-icon', '');
            icon.className = 'fa-solid ' + (state.dir === 'desc' ? 'fa-arrow-down-long' : 'fa-arrow-up-long');
            icon.style.cssText = 'margin-left:6px;font-size:11px;opacity:.7;';
            activeTh.appendChild(icon);
        }
    }

    /** Header yang belum ditandai sortable diinisialisasi sekali (skip yang kosong/isinya cuma checkbox). */
    function initSortableHeaders() {
        document.querySelectorAll('[data-livetable] table thead th').forEach(th => {
            if (th.hasAttribute('data-ls-sortable') || th.hasAttribute('data-no-sort')) return;
            if (th.textContent.trim() === '') return; // kolom checkbox / aksi tanpa judul
            th.setAttribute('data-ls-sortable', '');
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            th.title = 'Klik untuk mengurutkan';
        });
    }

    function reapplySortIfAny(container) {
        const table = container.querySelector(':scope table');
        if (table && sortStateMap.has(table)) applySort(table);
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

            reapplySortIfAny(container);

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

    // Klik header kolom -> urutkan. Klik lagi header yang sama -> balik arah.
    document.addEventListener('click', ev => {
        const th = ev.target.closest ? ev.target.closest('th[data-ls-sortable]') : null;
        if (!th) return;
        const table = th.closest('table');
        if (!table) return;
        const colIndex = Array.from(th.parentElement.children).indexOf(th);
        const current = sortStateMap.get(table);
        const dir = (current && current.colIndex === colIndex && current.dir === 'asc') ? 'desc' : 'asc';
        sortStateMap.set(table, { colIndex, dir });
        applySort(table);
    });

    function scanAndInit() {
        initSortableHeaders();
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
