<?php
// Reusable pagination control.
// Expects $page, $totalPages, $totalCount, $perPage to already be in scope
// (they arrive automatically since this is include()'d from inside a view
// that layout() rendered with extract()).
if (($totalPages ?? 1) > 1):
    $rangeStart = ($page - 1) * $perPage + 1;
    $rangeEnd = min($totalCount, $page * $perPage);
    $windowStart = max(1, $page - 2);
    $windowEnd = min($totalPages, $page + 2);
?>
<nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3" aria-label="Navigasi halaman">
    <div class="text-slate small">Menampilkan <?= $rangeStart ?>–<?= $rangeEnd ?> dari <?= $totalCount ?> data</div>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(page_url(max(1, $page - 1))) ?>">&laquo; Sebelumnya</a>
        </li>
        <?php if ($windowStart > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e(page_url(1)) ?>">1</a></li>
            <?php if ($windowStart > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(page_url($i)) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($windowEnd < $totalPages): ?>
            <?php if ($windowEnd < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= e(page_url($totalPages)) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(page_url(min($totalPages, $page + 1))) ?>">Berikutnya &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
