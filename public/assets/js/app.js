// SIMASSTA BMN — client script
document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss flash alerts
    document.querySelectorAll('.alert.autoclose').forEach(el => {
        setTimeout(() => el.classList.add('fade'), 4000);
        setTimeout(() => el.remove(), 4600);
    });

    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const openSidebar = () => { sidebar?.classList.add('open'); backdrop?.classList.add('show'); document.body.style.overflow = 'hidden'; };
    const closeSidebar = () => { sidebar?.classList.remove('open'); backdrop?.classList.remove('show'); document.body.style.overflow = ''; };
    menuToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    backdrop?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', ev => { if (ev.key === 'Escape') closeSidebar(); });
    // Close drawer automatically after tapping a nav link (mobile)
    sidebar?.querySelectorAll('.nav-item').forEach(link => link.addEventListener('click', () => {
        if (window.innerWidth <= 900) closeSidebar();
    }));

    // Sidebar ciut/lebar (desktop). Kelasnya di <html> — sudah dipasang skrip
    // inline di <head> sebelum paint, di sini tinggal mengurus tombolnya.
    const sidebarToggle = document.getElementById('sidebarToggle');
    const syncToggle = () => {
        const collapsed = document.documentElement.classList.contains('sb-collapsed');
        const icon = sidebarToggle?.querySelector('i');
        if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        if (sidebarToggle) {
            const label = collapsed ? 'Lebarkan menu' : 'Ciutkan menu';
            sidebarToggle.setAttribute('aria-label', label);
            sidebarToggle.setAttribute('title', label);
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
        // Saat ciut hanya ikon yang terlihat, jadi nama menu dipindah ke title
        // agar tetap muncul saat kursor diarahkan. Saat lebar title dilepas lagi
        // supaya tidak ada tooltip kembar di atas label yang sudah terbaca.
        sidebar?.querySelectorAll('.nav-item').forEach(el => {
            const span = el.querySelector('span');
            if (!span) return;
            if (collapsed) el.setAttribute('title', span.textContent.trim());
            else el.removeAttribute('title');
        });
    };
    sidebarToggle?.addEventListener('click', () => {
        const collapsed = document.documentElement.classList.toggle('sb-collapsed');
        try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        syncToggle();
        refreshSidebarParticles();
    });
    syncToggle();

    // ── Partikel latar sidebar ────────────────────────────────────────────────
    // Aturan ringannya sama dengan halaman login: tidak jalan di layar <=900px
    // (di sana sidebar cuma drawer yang sesekali dibuka), retina_detect off, dan
    // tanpa interaktivitas.
    //
    // Bedanya: sidebar bisa diciutkan. particles.js mengunci lebar kanvas saat
    // dibuat dan tidak menyesuaikannya ketika wadahnya berubah lebar — kanvas
    // 264px yang tertinggal di dalam rail 76px akan membuat sidebar bisa digeser
    // ke samping. Karena itu partikelnya dibuang saat menciut, lalu dibuat ulang
    // saat melebar.
    let sidebarParticlesOn = false;

    const sidebarParticlesAllowed = () =>
        document.getElementById('particles-sidebar')
        && typeof particlesJS === 'function'
        && window.matchMedia('(min-width: 901px)').matches
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        && !document.documentElement.classList.contains('sb-collapsed');

    const destroySidebarParticles = () => {
        if (!sidebarParticlesOn) return;
        try {
            (window.pJSDom || []).forEach(p => p.pJS.fn.vendors.destroypJS());
            window.pJSDom = [];
        } catch (e) { /* biarkan — bukan alasan untuk merusak halaman */ }
        sidebarParticlesOn = false;
    };

    const initSidebarParticles = () => {
        if (sidebarParticlesOn || !sidebarParticlesAllowed()) return;
        particlesJS('particles-sidebar', {
            particles: {
                number: { value: 46, density: { enable: true, value_area: 900 } },
                color: { value: ['#FFFFFF', '#FFDD87', '#F5B301'] },
                shape: { type: 'circle' },
                opacity: { value: 0.8, random: false, anim: { enable: false } },
                size: { value: 2.6, random: true, anim: { enable: false } },
                line_linked: { enable: true, distance: 130, color: '#8FB6F0', opacity: 0.28, width: 1 },
                move: { enable: true, speed: 1.1, direction: 'none', random: true, straight: false, out_mode: 'out', bounce: false }
            },
            interactivity: { detect_on: 'canvas', events: { onhover: { enable: false }, onclick: { enable: false }, resize: true } },
            retina_detect: false
        });
        sidebarParticlesOn = true;
    };

    function refreshSidebarParticles() {
        destroySidebarParticles();
        initSidebarParticles();
    }

    window.addEventListener('load', initSidebarParticles);

    // ── Mode terang / gelap ───────────────────────────────────────────────────
    // Atribut data-theme sudah dipasang skrip di <head> sebelum paint; di sini
    // hanya mengurus tombol & menyimpan pilihan. Sekali user menekan tombol,
    // pilihannya disimpan dan seterusnya menang atas setelan OS.
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeToggleIcon');
    const currentTheme = () => document.documentElement.getAttribute('data-theme') || 'light';
    const syncThemeButton = () => {
        const dark = currentTheme() === 'dark';
        if (themeIcon) themeIcon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        if (themeToggle) {
            const label = dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
            themeToggle.setAttribute('aria-label', label);
            themeToggle.setAttribute('title', label);
        }
    };
    themeToggle?.addEventListener('click', () => {
        const next = currentTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) {}
        syncThemeButton();
    });
    syncThemeButton();

    // Poll unread notification count every 30s
    const bell = document.getElementById('bell-count');
    if (bell) {
        const poll = async () => {
            try {
                const r = await fetch((window.BASE_PATH || '') + '/ajax/notifications/unread', { credentials: 'same-origin' });
                if (!r.ok) return;
                const j = await r.json();
                if (j.count > 0) {
                    bell.textContent = j.count;
                    bell.style.display = 'inline-block';
                } else {
                    bell.style.display = 'none';
                }
            } catch(_) {}
        };
        poll();
        setInterval(poll, 30000);
    }

    // Confirm forms — pakai modal custom yang lebih jelas & ramah, bukan confirm() bawaan browser
    document.querySelectorAll('form[data-confirm]').forEach(f => {
        f.addEventListener('submit', ev => {
            if (f.dataset.confirmed === '1') return; // sudah dikonfirmasi, lanjutkan submit
            ev.preventDefault();
            sbConfirm(f.dataset.confirm, () => {
                f.dataset.confirmed = '1';
                f.submit();
            });
        });
    });

    // Aktifkan tooltip Bootstrap untuk elemen dengan title (mis. tombol ikon-saja)
    if (window.bootstrap) {
        document.querySelectorAll('[title]').forEach(el => {
            if (!el.dataset.bsToggle) {
                new bootstrap.Tooltip(el, { trigger: 'hover focus' });
            }
        });
    }
});

// Modal konfirmasi custom — bahasa sederhana, tombol besar, jelas mana yang berbahaya
window.sbConfirm = function (message, onConfirm) {
    const backdrop = document.createElement('div');
    backdrop.className = 'sb-modal-backdrop';
    backdrop.innerHTML = `
        <div class="sb-modal">
            <div class="sb-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h5>Konfirmasi Tindakan</h5>
            <p>${message}</p>
            <div class="sb-modal-actions">
                <button type="button" class="btn btn-outline-navy" data-act="cancel">Batal</button>
                <button type="button" class="btn btn-danger" data-act="ok">Ya, Lanjutkan</button>
            </div>
        </div>`;
    document.body.appendChild(backdrop);
    requestAnimationFrame(() => backdrop.classList.add('show'));

    const close = () => { backdrop.classList.remove('show'); setTimeout(() => backdrop.remove(), 200); };
    backdrop.addEventListener('click', ev => { if (ev.target === backdrop) close(); });
    backdrop.querySelector('[data-act="cancel"]').addEventListener('click', close);
    backdrop.querySelector('[data-act="ok"]').addEventListener('click', () => { close(); onConfirm(); });
    document.addEventListener('keydown', function esc(ev) {
        if (ev.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });
};

// Simple toast
window.toast = function(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast-sb toast-sb-' + type;
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#0F172A;color:#fff;padding:12px 18px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:9999;font-size:14px;font-weight:500;';
    if (type === 'success') el.style.background = '#059669';
    if (type === 'error')   el.style.background = '#DC2626';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
};
