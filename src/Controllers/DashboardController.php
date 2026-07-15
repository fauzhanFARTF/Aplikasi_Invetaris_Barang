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

    // My data (peminjaman terbaru)
    if (in_array($role, ['pemohon', 'inventory_staff'], true)) {
        $stmt = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.requester_id = ? AND l.deleted_at IS NULL ORDER BY l.created_at DESC LIMIT 8");
        $stmt->execute([$uid]);
        $myLoans = $stmt->fetchAll();
    } else {
        $myLoans = $pdo->query("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.deleted_at IS NULL ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
    }
    // Personel yang terlibat per peminjaman yang ditampilkan.
    $loanParticipants = [];
    $loanIds = array_map(fn($l) => (int)$l['id'], $myLoans);
    if ($loanIds) {
        $in = implode(',', array_fill(0, count($loanIds), '?'));
        $pst = $pdo->prepare("SELECT lp.loan_id, GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') AS names
                              FROM loan_participants lp JOIN users u ON u.id = lp.user_id
                              WHERE lp.loan_id IN ($in) GROUP BY lp.loan_id");
        $pst->execute($loanIds);
        foreach ($pst->fetchAll() as $r) { $loanParticipants[(int)$r['loan_id']] = $r['names']; }
    }

    // Recent activity
    $recentDamage = $pdo->query("SELECT r.*, a.name AS asset_name, a.bmn_number FROM repairs r JOIN assets a ON a.id = r.asset_id WHERE r.status != 'Completed' AND r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

    // Today's schedule
    $today = date('Y-m-d');
    $todayLoans = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.status IN ('Approved','CheckedOut') AND l.deleted_at IS NULL AND l.start_date <= ? AND l.end_date >= ? ORDER BY l.start_date ASC");
    $todayLoans->execute([$today, $today]);
    $todayLoans = $todayLoans->fetchAll();

    layout('main', 'dashboard/index', [
        'title' => 'Dashboard',
        'stats' => $stats,
        'myLoans' => $myLoans,
        'loanParticipants' => $loanParticipants,
        'recentDamage' => $recentDamage,
        'todayLoans' => $todayLoans,
        'currentPath' => '/dashboard',
    ]);
}
