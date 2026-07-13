<div class="page-header">
    <div>
        <h1>Kategori Alat</h1>
        <p class="subtitle">Grouping alat berdasarkan jenis untuk memudahkan pencarian — total <?= count($cats) ?> kategori.</p>
    </div>
    <a href="<?= BASE_PATH ?>/categories/create" class="btn btn-amber" data-testid="btn-new-category"><i class="fa-solid fa-plus"></i> Tambah Kategori</a>
</div>

<div class="card-sb" data-livetable>
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input type="search" data-ls-search class="form-control" placeholder="Cari nama atau deskripsi kategori... (langsung tampil)" data-testid="search-input"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sb align-middle" data-testid="categories-table">
            <thead><tr><th>Nama</th><th>Deskripsi</th><th>Jumlah Alat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
                <tr data-ls-row data-ls-text="<?= e(strtolower($c['name'].' '.($c['description'] ?? ''))) ?>" data-testid="category-row-<?= (int)$c['id'] ?>">
                    <td><strong><?= e($c['name']) ?></strong><?= audit_trail_info($c) ?></td>
                    <td class="small text-slate"><?= e($c['description'] ?: '—') ?></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$c['asset_count'] ?></span></td>
                    <td class="text-nowrap">
                        <a href="<?= BASE_PATH ?>/categories/<?= (int)$c['id'] ?>/edit" class="btn btn-sm btn-outline-navy" data-testid="btn-edit-category-<?= (int)$c['id'] ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                        <form method="POST" action="<?= BASE_PATH ?>/categories/<?= (int)$c['id'] ?>/delete" data-confirm="Hapus kategori &quot;<?= e($c['name']) ?>&quot;? (masih bisa dipulihkan lewat Riwayat Terhapus)" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <button class="btn btn-sm btn-outline-danger" data-testid="btn-delete-category-<?= (int)$c['id'] ?>"><i class="fa-regular fa-trash-can"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-ls-empty style="display:none;"><td colspan="4" class="text-center text-slate py-4">Tidak ada kategori yang cocok dengan pencarian.</td></tr>
            <?php if (empty($cats)): ?>
                <tr><td colspan="4" class="text-center text-slate py-4">Belum ada kategori. Klik "Tambah Kategori" untuk membuat.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
