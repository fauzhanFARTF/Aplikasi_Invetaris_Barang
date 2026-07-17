<?php
declare(strict_types=1);
// Dashboard controller — role-aware summary

function dashboard_index(): void {
    Auth::requireLogin();
    $role = Auth::role();
    $uid  = Auth::id();
    $pdo  = db();

    $stats = [
        'total_assets'   => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status != 'Retired' AND deleted_at IS NULL")->fetchColumn(),
        'available'      => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available' AND deleted_at IS NULL")->fetchColumn(),
        'checked_out'    => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'CheckedOut' AND deleted_at IS NULL")->fetchColumn(),
        'damaged'        => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Damaged' AND deleted_at IS NULL")->fetchColumn(),
        'pending_approvals' => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Pending' AND deleted_at IS NULL")->fetchColumn(),
        'active_loans'   => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('Approved','CheckedOut') AND deleted_at IS NULL")->fetchColumn(),
        // Jumlah BARANG (alat) pada peminjaman menunggu approval & yang telah disetujui.
        'items_pending'  => (int) $pdo->query("SELECT COUNT(*) FROM loan_items li JOIN loans l ON l.id = li.loan_id WHERE l.status = 'Pending' AND l.deleted_at IS NULL")->fetchColumn(),
        'items_approved' => (int) $pdo->query("SELECT COUNT(*) FROM loan_items li JOIN loans l ON l.id = li.loan_id WHERE l.status = 'Approved' AND l.deleted_at IS NULL")->fetchColumn(),
    ];

    // My data (peminjaman terbaru) — requester murni hanya lihat miliknya sendiri.
    if (role_is_requester()) {
        $stmt = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.requester_id = ? AND l.deleted_at IS NULL ORDER BY l.created_at DESC LIMIT 8");
        $stmt->execute([$uid]);
        $myLoans = $stmt->fetchAll();
    } else {
        $myLoans = $pdo->query("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.deleted_at IS NULL ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
    }
    // Recent activity
    $recentDamage = $pdo->query("SELECT r.*, a.name AS asset_name, a.bmn_number FROM repairs r JOIN assets a ON a.id = r.asset_id WHERE r.status != 'Completed' AND r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

    // Barang yang sedang keluar ke OPD (tanpa batas waktu). Belum dikembalikan =
    // peminjaman OPD yang masih Approved/CheckedOut. Ditampilkan ke pengelola,
    // bukan ke Personel Luar. Kolom loan_type mungkin belum ada saat migrasi awal.
    $opdOut = [];
    if (!is_personal_borrower()) {
        try {
            $stmt = $pdo->query("SELECT l.uuid, l.loan_code, l.event_name AS opd_name, l.event_location, l.start_date,
                                        u.name AS requester_name,
                                        (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id) AS item_count
                                 FROM loans l JOIN users u ON u.id = l.requester_id
                                 WHERE l.loan_type = 'opd' AND l.status IN ('Approved','CheckedOut')
                                   AND l.deleted_at IS NULL
                                 ORDER BY l.start_date DESC, l.id DESC LIMIT 20");
            $opdOut = $stmt->fetchAll();
        } catch (Throwable $e) { $opdOut = []; }
    }

    // Kartu jadwal tidak ditampilkan untuk Personel Luar (pemohon murni) — peminjamannya
    // bersifat pribadi, jadi tidak perlu melihat agenda seluruh dinas. Query-nya pun
    // dilewati agar datanya tidak ikut terkirim ke halaman.
    $showSchedule  = !is_personal_borrower();
    $scheduleLoans = [];
    $pastLoans     = [];
    if ($showSchedule) {
        // Jadwal hari ini & selanjutnya (acara yang masih berlangsung / akan datang).
        $today = date('Y-m-d');
        $scheduleLoans = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id
                                        WHERE l.status IN ('Pending','Approved','CheckedOut') AND l.deleted_at IS NULL AND l.end_date >= ?
                                        ORDER BY l.start_date ASC, l.start_time ASC LIMIT 40");
        $scheduleLoans->execute([$today]);
        $scheduleLoans = $scheduleLoans->fetchAll();

        // Jadwal yang telah lewat (acara yang sudah selesai berlangsung).
        $pastLoans = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id
                                    WHERE l.status IN ('Approved','CheckedOut','Returned','Completed') AND l.deleted_at IS NULL AND l.end_date < ?
                                    ORDER BY l.end_date DESC, l.start_time DESC LIMIT 20");
        $pastLoans->execute([$today]);
        $pastLoans = $pastLoans->fetchAll();
    }

    // Personel yang terlibat untuk semua peminjaman yang ditampilkan.
    $loanParticipants = [];
    $loanIds = array_values(array_unique(array_merge(
        array_map(fn($l) => (int)$l['id'], $myLoans),
        array_map(fn($l) => (int)$l['id'], $scheduleLoans),
        array_map(fn($l) => (int)$l['id'], $pastLoans)
    )));
    if ($loanIds) {
        $in = implode(',', array_fill(0, count($loanIds), '?'));
        $pst = $pdo->prepare("SELECT lp.loan_id, GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') AS names
                              FROM loan_participants lp JOIN users u ON u.id = lp.user_id
                              WHERE lp.loan_id IN ($in) GROUP BY lp.loan_id");
        $pst->execute($loanIds);
        foreach ($pst->fetchAll() as $r) { $loanParticipants[(int)$r['loan_id']] = $r['names']; }
    }

    layout('main', 'dashboard/index', [
        'title' => 'Dashboard',
        'stats' => $stats,
        'myLoans' => $myLoans,
        'loanParticipants' => $loanParticipants,
        'recentDamage' => $recentDamage,
        'scheduleLoans' => $scheduleLoans,
        'pastLoans' => $pastLoans,
        'showSchedule' => $showSchedule,
        'opdOut' => $opdOut,
        'currentPath' => '/dashboard',
    ]);
}
