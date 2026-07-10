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
    foreach (_trash_entities() as $table => $meta) {
        $stmt = $pdo->query("SELECT t.id, t.{$meta['name_col']} AS label, t.deleted_at, u.name AS deleted_by_name
                              FROM $table t LEFT JOIN users u ON u.id = t.deleted_by
                              WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC");
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'type' => $table,
                'type_label' => $meta['label'],
                'id' => (int) $r['id'],
                'label' => $r['label'],
                'deleted_at' => $r['deleted_at'],
                'deleted_by_name' => $r['deleted_by_name'],
            ];
        }
    }
    usort($rows, fn($a, $b) => strcmp((string)$b['deleted_at'], (string)$a['deleted_at']));

    layout('main', 'trash/index', ['title' => 'Riwayat Terhapus', 'rows' => $rows, 'currentPath' => '/trash']);
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
