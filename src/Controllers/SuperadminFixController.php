<?php
declare(strict_types=1);
/**
 * Koreksi data operasional oleh SUPERADMIN.
 *
 * Alur normal sengaja satu arah (serah -> kembali -> perbaikan) supaya jejaknya
 * jujur. Tapi kesalahan input tetap terjadi: barang salah discan, kondisi salah
 * pilih, rencana OPD berubah. Fungsi-fungsi di sini memberi superadmin jalan
 * untuk membatalkan/memperbaiki langkah tersebut — selalu dicatat di audit log,
 * dan status alat serta status acara ikut dikembalikan agar tidak menggantung.
 */

/** Ambil satu loan_item beserta alat & acaranya, atau hentikan dengan pesan. */
function _sa_item(int $itemId, string $backTo): array
{
    $stmt = db()->prepare("SELECT li.*, a.name AS asset_name, a.status AS asset_status,
                                  l.id AS loan_id, l.uuid AS loan_uuid, l.loan_code, l.loan_type, l.status AS loan_status
                           FROM loan_items li
                           JOIN assets a ON a.id = li.asset_id
                           JOIN loans l  ON l.id = li.loan_id AND l.deleted_at IS NULL
                           WHERE li.id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) { flash('error', 'Data barang tidak ditemukan.'); redirect($backTo); }
    return $item;
}

// ─────────────────────────── PENYERAHAN ───────────────────────────
/** Batalkan penyerahan satu barang: kembali jadi Dipesan, alat kembali Dipesan. */
function sa_checkout_undo(string $itemId): void
{
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $item = _sa_item((int) $itemId, '/checkout');
    $back = "/checkout/{$item['loan_uuid']}";

    if (!in_array($item['item_status'], ['CheckedOut', 'AtOpd'], true)) {
        flash('error', "Barang {$item['asset_name']} belum diserahkan, jadi tidak ada penyerahan yang bisa dibatalkan.");
        redirect($back);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE loan_items SET item_status='Reserved', checkout_by=NULL, checkout_at=NULL WHERE id=?")
            ->execute([(int) $itemId]);
        // Alat kembali ke 'Dipesan' — masih terikat acara ini, hanya belum keluar gudang.
        $pdo->prepare("UPDATE assets SET status='Booked' WHERE id=?")->execute([(int) $item['asset_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal membatalkan penyerahan: ' . $e->getMessage());
        redirect($back);
    }
    recompute_loan_status((int) $item['loan_id']);
    log_audit('superadmin.checkout_undo', 'loan_item', (int) $itemId, ['asset' => $item['asset_name'], 'loan' => $item['loan_code']]);
    flash('success', "Penyerahan {$item['asset_name']} dibatalkan. Alat kembali berstatus Dipesan.");
    redirect($back);
}

// ─────────────────────────── PENGEMBALIAN ───────────────────────────
/** Batalkan pengembalian satu barang: kembali jadi Dipinjam, tiket perbaikannya dibuang. */
function sa_checkin_undo(string $itemId): void
{
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $item = _sa_item((int) $itemId, '/checkin');
    $back = "/checkin/{$item['loan_uuid']}";

    if (!in_array($item['item_status'], ['ReturnedGood', 'ReturnedDamaged', 'ReturnedLost', 'InRepair', 'Restored'], true)) {
        flash('error', "Barang {$item['asset_name']} belum dikembalikan, jadi tidak ada pengembalian yang bisa dibatalkan.");
        redirect($back);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Tiket perbaikan yang lahir dari pengembalian ini ikut dibatalkan — kalau
        // dibiarkan, alat akan tercatat rusak untuk pengembalian yang sudah dianulir.
        $pdo->prepare("DELETE FROM repairs WHERE loan_item_id = ?")->execute([(int) $itemId]);
        $pdo->prepare("UPDATE loan_items SET item_status='CheckedOut', checkin_by=NULL, checkin_at=NULL,
                              return_condition=NULL, damage_note=NULL WHERE id=?")->execute([(int) $itemId]);
        $pdo->prepare("UPDATE assets SET status='CheckedOut' WHERE id=?")->execute([(int) $item['asset_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal membatalkan pengembalian: ' . $e->getMessage());
        redirect($back);
    }
    recompute_loan_status((int) $item['loan_id']);
    log_audit('superadmin.checkin_undo', 'loan_item', (int) $itemId, ['asset' => $item['asset_name'], 'loan' => $item['loan_code']]);
    flash('success', "Pengembalian {$item['asset_name']} dibatalkan. Alat kembali berstatus Dipinjam.");
    redirect($back);
}

// ─────────────────────────── BARANG DI OPD ───────────────────────────
/** Ubah rencana sebuah barang OPD: dikembalikan (dengan tanggal) atau tetap di OPD. */
function sa_opd_item_edit(string $itemId): void
{
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $item = _sa_item((int) $itemId, '/opd-items');

    if (!in_array($item['item_status'], ['CheckedOut', 'AtOpd'], true)) {
        flash('error', "Barang {$item['asset_name']} sudah tidak berada di OPD.");
        redirect('/opd-items');
    }

    $willReturn = !empty($_POST['will_return']) ? 1 : 0;
    $date = trim((string) ($_POST['expected_return_date'] ?? ''));
    if ($willReturn) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('error', 'Isi tanggal kembali untuk barang yang ditandai Dikembalikan.');
            redirect('/opd-items');
        }
    } else {
        $date = null;
    }

    // item_status & status alat ikut menyesuaikan: barang yang ditunggu kembali
    // berstatus Dipinjam (masuk Pengembalian), yang tetap di OPD berstatus Di OPD.
    $newItem  = $willReturn ? 'CheckedOut' : 'AtOpd';
    $newAsset = $willReturn ? 'CheckedOut' : 'AtOpd';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE loan_items SET will_return=?, expected_return_date=?, item_status=? WHERE id=?")
            ->execute([$willReturn, $date, $newItem, (int) $itemId]);
        $pdo->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$newAsset, (int) $item['asset_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal menyimpan perubahan: ' . $e->getMessage());
        redirect('/opd-items');
    }
    recompute_loan_status((int) $item['loan_id']);
    log_audit('superadmin.opd_item_edit', 'loan_item', (int) $itemId, ['will_return' => $willReturn, 'tanggal' => $date]);
    flash('success', $willReturn
        ? "{$item['asset_name']} ditandai akan dikembalikan pada " . fmt_date($date) . '.'
        : "{$item['asset_name']} ditandai tetap berada di OPD.");
    redirect('/opd-items');
}

/** Hapus barang dari OPD: dilepas dari acara dan alat kembali Tersedia. */
function sa_opd_item_delete(string $itemId): void
{
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $item = _sa_item((int) $itemId, '/opd-items');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM repairs WHERE loan_item_id = ?")->execute([(int) $itemId]);
        $pdo->prepare("DELETE FROM loan_items WHERE id = ?")->execute([(int) $itemId]);
        // Alat dilepas penuh — inilah yang mengembalikannya ke hitungan "Tersedia".
        $pdo->prepare("UPDATE assets SET status='Available' WHERE id=? AND status IN ('Booked','CheckedOut','AtOpd')")
            ->execute([(int) $item['asset_id']]);

        $sisa = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ?");
        $sisa->execute([(int) $item['loan_id']]);
        if ((int) $sisa->fetchColumn() === 0) {
            // Acara tanpa barang tersisa tidak punya arti lagi — tandai dibatalkan.
            $pdo->prepare("UPDATE loans SET status='Cancelled', updated_by=? WHERE id=?")
                ->execute([Auth::id(), (int) $item['loan_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal menghapus barang dari OPD: ' . $e->getMessage());
        redirect('/opd-items');
    }
    recompute_loan_status((int) $item['loan_id']);
    log_audit('superadmin.opd_item_delete', 'loan_item', (int) $itemId, ['asset' => $item['asset_name'], 'loan' => $item['loan_code']]);
    flash('success', "{$item['asset_name']} dilepas dari OPD dan kembali berstatus Tersedia.");
    redirect('/opd-items');
}

// ─────────────────────────── PERBAIKAN ───────────────────────────
/** Ubah keluhan & status sebuah tiket perbaikan. */
function sa_repair_edit(string $uuid): void
{
    Auth::requireRole('superadmin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('repairs', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ?");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) { flash('error', 'Data perbaikan tidak ditemukan.'); redirect('/repairs'); }

    $complaint = trim((string) ($_POST['complaint'] ?? ''));
    $status    = (string) ($_POST['status'] ?? '');
    if ($complaint === '') { flash('error', 'Keluhan tidak boleh kosong.'); redirect("/repairs/$uuid"); }
    if (!in_array($status, ['Open', 'FormPrinted', 'InRepair', 'Completed'], true)) {
        flash('error', 'Status perbaikan tidak valid.'); redirect("/repairs/$uuid");
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE repairs SET complaint=?, status=?, updated_by=? WHERE id=?")
            ->execute([$complaint, $status, Auth::id(), $id]);
        // Status alat mengikuti: selesai -> Tersedia lagi, selain itu masih Rusak.
        $pdo->prepare("UPDATE assets SET status=? WHERE id=?")
            ->execute([$status === 'Completed' ? 'Available' : 'Damaged', (int) $repair['asset_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal menyimpan perubahan: ' . $e->getMessage());
        redirect("/repairs/$uuid");
    }
    log_audit('superadmin.repair_edit', 'repair', $id, ['status' => $status]);
    flash('success', "Perbaikan {$repair['repair_code']} diperbarui.");
    redirect("/repairs/$uuid");
}
