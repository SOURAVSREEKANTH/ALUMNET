<section class="directory-section section-pad" aria-labelledby="directory-title">
    <div class="directory-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Find your people</p>
        <h1 id="directory-title">The member<br><em>directory.</em></h1>
        <p>Search profiles shared with signed-in members. Suggestions are ranked only by a shared graduating year and department, then shared department; no hidden traits are inferred.</p>
    </div>

    <form class="directory-filters" action="/directory" method="get">
        <div class="field-group directory-search">
            <label for="directory-query">Search members</label>
            <input id="directory-query" name="q" type="search" maxlength="80" value="<?= \AlumniConnect\Support\View::escape($query) ?>" placeholder="Name, work, or department">
        </div>
        <div class="field-group">
            <label for="filter-department">Department</label>
            <select id="filter-department" name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= \AlumniConnect\Support\View::escape($department['id']) ?>" <?= (int) $selectedDepartment === (int) $department['id'] ? 'selected' : '' ?>>
                        <?= \AlumniConnect\Support\View::escape($department['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-group">
            <label for="filter-year">Graduating year</label>
            <select id="filter-year" name="year">
                <option value="">All years</option>
                <?php foreach ($graduationYears as $year): ?>
                    <option value="<?= \AlumniConnect\Support\View::escape($year) ?>" <?= (int) $selectedYear === (int) $year ? 'selected' : '' ?>>
                        <?= \AlumniConnect\Support\View::escape($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="directory-submit" type="submit">Search <span aria-hidden="true">↗</span></button>
    </form>

    <?php if ($directoryError !== null): ?>
        <p class="form-message directory-message" role="alert"><?= \AlumniConnect\Support\View::escape($directoryError) ?></p>
    <?php else: ?>
        <div class="directory-results-heading" aria-live="polite">
            <p><?= \AlumniConnect\Support\View::escape($totalMembers) ?> member<?= $totalMembers === 1 ? '' : 's' ?> found</p>
            <p>Private profiles and your own account are not listed.</p>
        </div>

        <?php if ($members === []): ?>
            <div class="directory-empty">
                <h2>No matching members yet.</h2>
                <p>Try another search or update your profile. Members appear here only when their visibility is set to Members or Public listing.</p>
            </div>
        <?php else: ?>
            <div class="member-grid">
                <?php foreach ($members as $member): ?>
                    <article class="member-card">
                        <div class="member-card-top">
                            <p class="member-role"><?= \AlumniConnect\Support\View::escape($member['role_labels'] ?? 'Member') ?></p>
                            <?php if ($member['verification_status'] === 'pending'): ?>
                                <p class="member-verification">Alumni verification pending</p>
                            <?php elseif ($member['verification_status'] === 'rejected'): ?>
                                <p class="member-verification">Alumni verification not approved</p>
                            <?php endif; ?>
                            <?php if ($member['match_reason'] !== null): ?>
                                <p class="member-match">Suggested · <?= \AlumniConnect\Support\View::escape($member['match_reason']) ?></p>
                            <?php endif; ?>
                        </div>
                        <h2><?= \AlumniConnect\Support\View::escape($member['full_name']) ?></h2>
                        <?php if ($member['headline'] !== null && $member['headline'] !== ''): ?>
                            <p class="member-headline"><?= \AlumniConnect\Support\View::escape($member['headline']) ?></p>
                        <?php endif; ?>
                        <?php if ($member['job_title'] !== null || $member['company'] !== null): ?>
                            <p class="member-work">
                                <?= \AlumniConnect\Support\View::escape(implode(' · ', array_filter([$member['job_title'], $member['company']]))) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($member['department_name'] !== null || $member['graduation_year'] !== null): ?>
                            <p class="member-study">
                                <?= \AlumniConnect\Support\View::escape(implode(' · ', array_filter([$member['department_name'], $member['graduation_year'] === null ? null : (string) $member['graduation_year']]))) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($member['biography'] !== null && $member['biography'] !== ''): ?>
                            <p class="member-biography"><?= nl2br(\AlumniConnect\Support\View::escape($member['biography'])) ?></p>
                        <?php endif; ?>
                        <?php if ($member['contact_email'] !== null): ?>
                            <a class="member-contact" href="mailto:<?= \AlumniConnect\Support\View::escape($member['contact_email']) ?>">Contact by email <span aria-hidden="true">↗</span></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pageCount > 1): ?>
                <nav class="directory-pagination" aria-label="Directory pages">
                    <?php if ($page > 1): ?>
                        <a href="/directory?<?= \AlumniConnect\Support\View::escape(http_build_query(['q' => $query, 'department' => $selectedDepartment, 'year' => $selectedYear, 'page' => $page - 1])) ?>">← Previous</a>
                    <?php endif; ?>
                    <span>Page <?= \AlumniConnect\Support\View::escape($page) ?> of <?= \AlumniConnect\Support\View::escape($pageCount) ?></span>
                    <?php if ($page < $pageCount): ?>
                        <a href="/directory?<?= \AlumniConnect\Support\View::escape(http_build_query(['q' => $query, 'department' => $selectedDepartment, 'year' => $selectedYear, 'page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
