<section class="admin-audit-section section-pad" aria-labelledby="admin-audit-log-title">
    <div class="directory-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Administrator tools</p>
        <h1 id="admin-audit-log-title">Audit<br><em>log.</em></h1>
        <p>Review recorded account, event, verification, and moderation actions. Sensitive metadata, passwords, and authentication tokens are not displayed.</p>
    </div>

    <div class="directory-results-heading" aria-live="polite">
        <p><?= \AlumniConnect\Support\View::escape($totalEntries) ?> recorded action<?= $totalEntries === 1 ? '' : 's' ?></p>
        <p>Newest actions appear first. Timestamps are UTC.</p>
    </div>

    <?php if ($entries === []): ?>
        <div class="directory-empty">
            <h2>No audit entries are recorded.</h2>
            <p>Administrative and event actions will appear here when recorded.</p>
        </div>
    <?php else: ?>
        <div class="admin-audit-table-wrap">
            <table class="admin-audit-table">
                <caption class="visually-hidden">Recent application audit entries</caption>
                <thead>
                    <tr>
                        <th scope="col">Time (UTC)</th>
                        <th scope="col">Action</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Target</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td data-label="Time (UTC)"><?= \AlumniConnect\Support\View::escape($entry['created_at']) ?></td>
                            <td data-label="Action"><strong><?= \AlumniConnect\Support\View::escape($entry['action']) ?></strong></td>
                            <td data-label="Actor"><?= \AlumniConnect\Support\View::escape($entry['actor_name'] ?? 'System or removed account') ?></td>
                            <td data-label="Target">
                                <?= \AlumniConnect\Support\View::escape($entry['target_type']) ?>
                                <?= $entry['target_id'] === null ? '' : '#' . \AlumniConnect\Support\View::escape($entry['target_id']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pageCount > 1): ?>
            <nav class="directory-pagination" aria-label="Audit log pages">
                <?php if ($page > 1): ?>
                    <a href="/admin/audit?page=<?= \AlumniConnect\Support\View::escape($page - 1) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= \AlumniConnect\Support\View::escape($page) ?> of <?= \AlumniConnect\Support\View::escape($pageCount) ?></span>
                <?php if ($page < $pageCount): ?>
                    <a href="/admin/audit?page=<?= \AlumniConnect\Support\View::escape($page + 1) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
