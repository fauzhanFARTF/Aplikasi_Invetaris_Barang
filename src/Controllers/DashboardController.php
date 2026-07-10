<?php
declare(strict_types=1);
// Dashboard controller — role-aware summary

function dashboard_index(): void {
    Auth::requireLogin();
    $role = Auth::role();
    $uid  = Auth::id();
    $pdo  = db();

    $stats = [
        'total_assets'   => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status != 'Retired'")->fetchColumn(),
        'available'      => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn(),
        'checked_out'    => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'CheckedOut'")->fetchColumn(),
        'damaged'        => (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Damaged'")->fetchColumn(),
        'pending_approvals' => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Pending'")->fetchColumn(),
        'active_loans'   => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('Approved','CheckedOut')")->fetchColumn(),
    ];

    // My data
    if ($role === 'pemohon') {
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE requester_id = ? ORDER BY created_at DESC LIMIT 8");
        $stmt->execute([$uid]);
        $myLoans = $stmt->fetchAll();
    } else {
        $myLoans = $pdo->query("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
    }

    // Recent activity
    $recentDamage = $pdo->query("SELECT r.*, a.name AS asset_name, a.bmn_number FROM repairs r JOIN assets a ON a.id = r.asset_id WHERE r.status != 'Completed' ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

    // Today's schedule
    $today = date('Y-m-d');
    $todayLoans = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.status IN ('Approved','CheckedOut') AND l.start_date <= ? AND l.end_date >= ? ORDER BY l.start_date ASC");
    $todayLoans->execute([$today, $today]);
    $todayLoans = $todayLoans->fetchAll();

    layout('main', 'dashboard/index', [
        'title' => 'Dashboard',
        'stats' => $stats,
        'myLoans' => $myLoans,
        'recentDamage' => $recentDamage,
        'todayLoans' => $todayLoans,
        'currentPath' => '/dashboard',
    ]);
}
