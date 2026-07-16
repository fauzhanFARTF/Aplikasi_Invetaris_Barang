<?php
declare(strict_types=1);
// Reset data per-manajemen — KHUSUS SUPERADMIN.
// Admin biasa TIDAK bisa mengakses endpoint ini (Auth::requireRole('superadmin')
// hanya meloloskan superadmin). Semua reset = hard delete (termasuk data yang
// sedang di-soft-delete), dengan urutan yang aman terhadap foreign key.

/** Lepas status alat yang tersangkut peminjaman (Booked/CheckedOut -> Available). */
function _reset_release_assets(PDO $pdo): void {
    $pdo->exec("UPDATE assets SET status='Available' WHERE status IN ('Booked','CheckedOut')");
}

function reset_loans(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
        $pdo->exec("DELETE FROM loans");        // loan_items ikut (CASCADE); repairs.loan_item_id -> NULL
        _reset_release_assets($pdo);            // alat yang dipinjam/dipesan kembali Tersedia
        log_audit('reset.loans', 'loan', null, ['count' => $n]);
        flash('success', "Reset peminjaman: $n acara dihapus permanen. Status alat yang dipinjam/dipesan dikembalikan ke Tersedia.");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/loans');
}

function reset_users(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetchColumn();
        $pdo->exec("DELETE FROM notifications");
        $pdo->exec("UPDATE repairs SET completed_by = NULL");           // FK RESTRICT -> lepaskan dulu
        $pdo->exec("DELETE FROM loans");                                 // loans.requester_id RESTRICT
        _reset_release_assets($pdo);
        // Foto user yang akan dihapus ikut dibersihkan dari disk.
        foreach ($pdo->query("SELECT photo FROM users WHERE role != 'superadmin' AND photo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $p) {
            delete_photo($p, 'users');
        }
        $pdo->exec("DELETE FROM users WHERE role != 'superadmin'");
        log_audit('reset.users', 'user', null, ['count' => $n]);
        flash('success', "Reset user: $n akun dihapus permanen (superadmin dipertahankan). Peminjaman terkait ikut terhapus.");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/users');
}

function reset_assets(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $pdo->exec("DELETE FROM loans");     // loan_items.asset_id RESTRICT
        $pdo->exec("DELETE FROM repairs");   // repairs.asset_id RESTRICT
        foreach ($pdo->query("SELECT photo FROM assets WHERE photo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $p) {
            // Hanya file hasil upload (asset_*) yang dihapus; fixture seed (cat_*.jpg) dibiarkan.
            if (str_starts_with(basename($p), 'asset_')) delete_photo($p, 'assets');
        }
        $pdo->exec("DELETE FROM assets");    // package_items ikut (CASCADE)
        log_audit('reset.assets', 'asset', null, ['count' => $n]);
        flash('success', "Reset alat: $n alat dihapus permanen (peminjaman & perbaikan terkait ikut terhapus).");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/inventory');
}

function reset_categories(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $pdo->exec("DELETE FROM categories");   // assets.category_id -> NULL (SET NULL)
        log_audit('reset.categories', 'category', null, ['count' => $n]);
        flash('success', "Reset kategori: $n kategori dihapus permanen (alat menjadi tanpa kategori).");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/categories');
}

function reset_packages(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
        $pdo->exec("DELETE FROM packages");     // package_items ikut (CASCADE)
        log_audit('reset.packages', 'package', null, ['count' => $n]);
        flash('success', "Reset paket: $n paket dihapus permanen.");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/packages');
}

function reset_repairs(): void {
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM repairs")->fetchColumn();
        $pdo->exec("DELETE FROM repairs");
        log_audit('reset.repairs', 'repair', null, ['count' => $n]);
        flash('success', "Reset perbaikan: $n catatan perbaikan dihapus permanen.");
    } catch (Throwable $e) { flash('error', 'Gagal reset: ' . $e->getMessage()); }
    redirect('/repairs');
}

/**
 * Tombol "Reset" merah di samping tombol tambah data — hanya dirender untuk
 * superadmin. Dipakai di tiap halaman manajemen.
 */
function reset_button(string $action, string $label, string $confirm): string {
    if (!Auth::hasRole('superadmin')) return '';
    return '<form method="POST" action="' . BASE_PATH . '/reset/' . e($action) . '" data-confirm="' . e($confirm) . '" style="display:inline;">'
         . '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">'
         . '<button type="submit" class="btn btn-danger" data-testid="btn-reset-' . e($action) . '"><i class="fa-solid fa-rotate"></i> ' . e($label) . '</button>'
         . '</form>';
}
