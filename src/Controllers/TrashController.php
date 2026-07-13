<?php
declare(strict_types=1);
// Riwayat Terhapus (trash) — lihat & pulihkan data yang sudah di-soft-delete
// dari seluruh entitas utama.

function _trash_entities(): array {
    return [
        'users'      => ['label' => 'User', 'name_col' => 'name'],
        'categories' => ['label' => 'Kategori', 'name_col' => 'name'],
        'assets'     => ['label' => 'Alat', 'name_col' => 'name'],
        'packages'   => ['label' => 'Paket', 'name_col' => 'name'],
        'loans'      => ['label' => 'Peminjaman', 'name_col' => 'loan_code'],
        'repairs'    => ['label' => 'Perbaikan', 'name_col' => 'repair_code'],
    ];
}

function trash_index(): void {
    Auth::requireRole('admin');
    $pdo = db();
    $rows = [];
    $restoredRows = [];
    foreach (_trash_entities() as $table => $meta) {
        // deleted_by TIDAK di-null-kan saat restore (lihat restore_record()), jadi baris
        // yang sudah dipulihkan pun tetap menyimpan jejak siapa yang dulu menghapusnya.
        $stmt = $pdo->query("SELECT t.id, t.{$meta['name_col']} AS label, t.deleted_at, t.restored_at,
                              du.name AS deleted_by_name, ru.name AS restored_by_name
                              FROM $table t
                              LEFT JOIN users du ON du.id = t.deleted_by
                              LEFT JOIN users ru ON ru.id = t.restored_by
                              WHERE t.deleted_at IS NOT NULL OR t.restored_at IS NOT NULL");
        foreach ($stmt->fetchAll() as $r) {
            $base = [
                'type' => $table,
                'type_label' => $meta['label'],
                'id' => (int) $r['id'],
                'label' => $r['label'],
                'deleted_at' => $r['deleted_at'],
                'deleted_by_name' => $r['deleted_by_name'],
                'restored_at' => $r['restored_at'],
                'restored_by_name' => $r['restored_by_name'],
            ];
            if ($r['deleted_at']) $rows[] = $base;
            if ($r['restored_at']) $restoredRows[] = $base;
        }
    }
    usort($rows, fn($a, $b) => strcmp((string)$b['deleted_at'], (string)$a['deleted_at']));
    usort($restoredRows, fn($a, $b) => strcmp((string)$b['restored_at'], (string)$a['restored_at']));

    layout('main', 'trash/index', [
        'title' => 'Riwayat Terhapus',
        'rows' => $rows,
        'restoredRows' => $restoredRows,
        'currentPath' => '/trash',
    ]);
}

function trash_restore(string $type, string $id): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $entities = _trash_entities();
    if (!isset($entities[$type])) {
        flash('error', 'Jenis data tidak valid.');
        redirect('/trash');
    }
    restore_record($type, (int) $id);
    log_audit('trash.restore', $type, $id);
    flash('success', 'Data berhasil dipulihkan.');
    redirect('/trash');
}

/**
 * Hapus PERMANEN satu baris dari Riwayat Terhapus (hard delete, tak bisa dipulihkan).
 * Hanya boleh untuk baris yang memang sudah di-soft-delete. Jika baris masih
 * direferensikan data lain (FK RESTRICT — mis. aset yang pernah dipinjam, user yang
 * punya riwayat), DELETE gagal dan ditangkap dengan pesan ramah — datanya tetap aman
 * di Riwayat Terhapus.
 */
function trash_purge(string $type, string $id): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $entities = _trash_entities();
    if (!isset($entities[$type])) {
        flash('error', 'Jenis data tidak valid.');
        redirect('/trash');
    }
    $id = (int) $id;
    $pdo = db();

    // Guard: hanya baris yang benar-benar ada di Riwayat Terhapus yang boleh di-purge.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM $type WHERE id = ? AND deleted_at IS NOT NULL");
    $chk->execute([$id]);
    if (!(int) $chk->fetchColumn()) {
        flash('error', 'Data tidak ditemukan di Riwayat Terhapus.');
        redirect('/trash');
    }

    // Untuk assets/users, ambil nama file foto agar bisa dibersihkan dari disk setelah
    // baris benar-benar terhapus.
    $photoDir = $type === 'assets' ? 'assets' : ($type === 'users' ? 'users' : null);
    $photo = null;
    if ($photoDir) {
        $st = $pdo->prepare("SELECT photo FROM $type WHERE id = ?");
        $st->execute([$id]);
        $photo = $st->fetchColumn() ?: null;
    }

    try {
        $pdo->prepare("DELETE FROM $type WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // Umumnya pelanggaran foreign key (SQLSTATE 23000).
        flash('error', 'Tidak bisa dihapus permanen karena data ini masih direferensikan oleh data lain (mis. peminjaman / perbaikan). Data tetap aman di Riwayat Terhapus.');
        redirect('/trash');
    }

    if ($photoDir && $photo) { delete_photo($photo, $photoDir); }
    log_audit('trash.purge', $type, $id);
    flash('success', 'Data berhasil dihapus permanen.');
    redirect('/trash');
}
