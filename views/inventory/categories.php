<div class="page-header">
    <div>
        <h1>Kategori Alat</h1>
        <p class="subtitle">Grouping alat berdasarkan jenis untuk memudahkan pencarian.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-sb">
            <div class="card-title">Tambah Kategori</div>
            <form method="POST" action="<?= BASE_PATH ?>/categories/create">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <div class="mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required data-testid="input-cat-name"></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><input type="text" name="description" class="form-control"></div>
                <button class="btn btn-primary w-100" data-testid="btn-add-category"><i class="fa-solid fa-plus"></i> Tambah</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-sb" data-livetable>
            <div class="row g-2 mb-3">
                <div class="col-12"><input type="search" data-ls-search class="form-control" placeholder="Cari nama atau deskripsi kategori... (langsung tampil)" data-testid="search-input"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sb align-middle">
                    <thead><tr><th>Nama</th><th>Deskripsi</th><th>Jumlah Alat</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cats as $c): ?>
                        <tr data-ls-row data-ls-text="<?= e(strtolower($c['name'].' '.$c['description'])) ?>">
                            <td><strong><?= e($c['name']) ?></strong></td>
                            <td class="small text-slate"><?= e($c['description'] ?: '—') ?></td>
                            <td><span class="badge bg-info text-dark"><?= (int)$c['asset_count'] ?></span></td>
                            <td>
                                <?php if (Auth::role() === 'admin' && (int)$c['asset_count'] === 0): ?>
                                    <form method="POST" action="<?= BASE_PATH ?>/categories/<?= (int)$c['id'] ?>/delete" data-confirm="Hapus kategori ini?" style="display:inline;">
                                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-ls-empty style="display:none;"><td colspan="4" class="text-center text-slate py-4">Tidak ada kategori yang cocok dengan pencarian.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
