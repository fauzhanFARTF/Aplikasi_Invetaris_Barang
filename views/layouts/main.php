<?php
$user = Auth::user();
$currentPath = $currentPath ?? '/';
$unread = $user ? Notification::unreadCount((int)$user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> · <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset_url("/assets/css/app.css") ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_PREFIX ?>/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_PREFIX ?>/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ASSET_PREFIX ?>/assets/img/favicon-180.png">
    <link rel="shortcut icon" href="<?= ASSET_PREFIX ?>/assets/img/favicon.ico">
    <script>
        // Pasang kelas ciut SEBELUM halaman tergambar, supaya sidebar tidak
        // berkedip lebar-lalu-menyempit saat pindah halaman.
        try { if (localStorage.getItem('sidebarCollapsed') === '1') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}
        // Mode terang/gelap dipasang SEBELUM halaman tergambar, supaya tidak
        // berkedip putih dulu. Pilihan user (localStorage) menang atas setelan OS.
        try {
            var t = localStorage.getItem('theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop" data-testid="sidebar-backdrop"></div>
    <aside class="sidebar" id="sidebar" data-testid="sidebar">
        <div id="particles-sidebar" aria-hidden="true"></div>
        <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup menu" data-testid="btn-sidebar-close"><i class="fa-solid fa-xmark"></i></button>
        <div class="brand">
            <div class="brand-mark"><img src="<?= ASSET_PREFIX ?>/assets/img/logo-kominfo-icon.png" alt="Logo Kominfo"></div>
            <div class="brand-text">
                <div class="brand-title">SIMANTAP</div>
                <div class="brand-sub">Diskominfo · Kab. Tangerang</div>
            </div>
        </div>
        <!-- Barisnya sendiri, di luar .brand: kalau ikut baris brand, tombol ini
             memakan ruang teks dan "Diskominfo · Kab. Tangerang" pecah jadi 3 baris. -->
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Ciutkan menu" aria-expanded="true" title="Ciutkan menu" data-testid="btn-sidebar-toggle">
            <i class="fa-solid fa-angles-left"></i>
        </button>

        <div class="nav-section">Menu</div>
        <a href="<?= BASE_PATH ?>/dashboard" class="nav-item <?= active('/dashboard', $currentPath) ?>" data-testid="nav-dashboard"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <a href="<?= BASE_PATH ?>/loans" class="nav-item <?= active('/loans', $currentPath) ?>" data-testid="nav-loans"><i class="fa-solid fa-clipboard-list"></i><span>Peminjaman</span></a>

        <?php if (role_is('supervisor','admin')): ?>
            <a href="<?= BASE_PATH ?>/approvals" class="nav-item <?= active('/approvals', $currentPath) ?>" data-testid="nav-approvals"><i class="fa-solid fa-check-double"></i><span>Approval</span></a>
        <?php endif; ?>

        <?php if (role_is('admin_gudang','admin')): ?>
            <div class="nav-section">Gudang</div>
            <a href="<?= BASE_PATH ?>/checkout" class="nav-item <?= active('/checkout', $currentPath) ?>" data-testid="nav-checkout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Penyerahan</span></a>
            <a href="<?= BASE_PATH ?>/checkin" class="nav-item <?= active('/checkin', $currentPath) ?>" data-testid="nav-checkin"><i class="fa-solid fa-arrow-right-to-bracket"></i><span>Pengembalian</span></a>
            <a href="<?= BASE_PATH ?>/repairs" class="nav-item <?= active('/repairs', $currentPath) ?>" data-testid="nav-repairs"><i class="fa-solid fa-screwdriver-wrench"></i><span>Perbaikan</span></a>
        <?php endif; ?>

        <?php if (role_is('admin_gudang','admin','supervisor','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan')): ?>
            <div class="nav-section">Master Data</div>
            <?php if (role_is('admin_gudang','admin','supervisor','administrator_pembantu_manajemen_alat','pimpinan')): ?>
                <a href="<?= BASE_PATH ?>/inventory" class="nav-item <?= active('/inventory', $currentPath) ?>" data-testid="nav-inventory"><i class="fa-solid fa-boxes-stacked"></i><span>Alat / Aset</span></a>
            <?php endif; ?>
            <?php if (role_is('admin_gudang','admin','supervisor')): ?>
                <a href="<?= BASE_PATH ?>/packages" class="nav-item <?= active('/packages', $currentPath) ?>" data-testid="nav-packages"><i class="fa-solid fa-cubes"></i><span>Paket Alat</span></a>
            <?php endif; ?>
            <?php if (role_is('admin_gudang','admin','supervisor','administrator_pembantu_manajemen_kategori')): ?>
                <a href="<?= BASE_PATH ?>/categories" class="nav-item <?= active('/categories', $currentPath) ?>" data-testid="nav-categories"><i class="fa-solid fa-tags"></i><span>Kategori</span></a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (role_is('admin','administrator_pembantu_manajemen_user')): ?>
            <div class="nav-section">Administrasi</div>
            <a href="<?= BASE_PATH ?>/users" class="nav-item <?= active('/users', $currentPath) ?>" data-testid="nav-users"><i class="fa-solid fa-user-shield"></i><span>Manajemen User</span></a>
            <?php $pendingReg = pending_registration_count(); ?>
            <a href="<?= BASE_PATH ?>/registrations" class="nav-item <?= active('/registrations', $currentPath) ?>" data-testid="nav-registrations">
                <i class="fa-solid fa-user-check"></i><span>Verifikasi Pendaftaran</span>
                <?php if ($pendingReg): ?><span class="nav-badge" data-testid="nav-reg-count"><?= $pendingReg ?></span><?php endif; ?>
            </a>
            <?php if (role_is('admin')): ?>
                <a href="<?= BASE_PATH ?>/trash" class="nav-item <?= active('/trash', $currentPath) ?>" data-testid="nav-trash"><i class="fa-solid fa-trash-can"></i><span>Riwayat Terhapus</span></a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="nav-section">Akun</div>
        <a href="<?= BASE_PATH ?>/profile" class="nav-item <?= active('/profile', $currentPath) ?>" data-testid="nav-profile"><i class="fa-solid fa-user"></i><span>Profil Saya</span></a>
        <form method="POST" action="<?= BASE_PATH ?>/logout" style="margin-top:8px;">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button type="submit" class="nav-item" style="border:0;background:transparent;width:100%;text-align:left;cursor:pointer;color:#F87171;" data-testid="nav-logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Keluar</span>
            </button>
        </form>
    </aside>

    <div class="main-wrap">
        <header class="topbar">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu" data-testid="btn-menu-toggle"><i class="fa-solid fa-bars"></i></button>
                <div class="min-w-0">
                    <h1 class="page-title"><?= e($title ?? '') ?></h1>
                    <div class="breadcrumbs"><?= e(APP_NAME) ?></div>
                </div>
            </div>
            <div class="top-actions">
                <button type="button" class="bell theme-toggle" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode tampilan" data-testid="btn-theme-toggle">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </button>
                <a href="<?= BASE_PATH ?>/notifications" class="bell" data-testid="notif-bell" title="Notifikasi">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot" id="bell-count" style="<?= $unread ? '' : 'display:none;' ?>" data-testid="bell-count"><?= $unread ?></span>
                </a>
                <div class="dropdown">
                    <button class="user-chip" type="button" id="userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" data-testid="user-chip">
                        <div class="av"><?= e(strtoupper(mb_substr($user['name'],0,1))) ?></div>
                        <div class="who"><div><?= e($user['name']) ?></div><div class="role"><?= e(role_label($user['role'])) ?></div></div>
                        <i class="fa-solid fa-chevron-down user-chip-caret"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userMenuBtn" data-testid="user-menu">
                        <li class="px-3 py-2">
                            <div class="fw-semibold" style="font-size:13.5px;"><?= e($user['name']) ?></div>
                            <div class="text-slate" style="font-size:12px;"><?= e($user['email']) ?></div>
                            <div class="text-slate" style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;"><?= e(role_label($user['role'])) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>/profile" data-testid="menu-profile"><i class="fa-regular fa-user me-2"></i> Kelola Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="<?= BASE_PATH ?>/logout" class="m-0">
                                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <button type="submit" class="dropdown-item text-danger" data-testid="menu-logout"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="page-body">
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success autoclose" data-testid="flash-success"><i class="fa-solid fa-circle-check me-2"></i><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger autoclose" data-testid="flash-error"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($msg) ?></div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<script>window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url("/assets/js/particles.min.js") ?>"></script>
<script src="<?= asset_url("/assets/js/live-search.js") ?>"></script>
<script src="<?= asset_url("/assets/js/app.js") ?>"></script>
</body>
</html>
