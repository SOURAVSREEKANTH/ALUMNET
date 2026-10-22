<section class="admin-audit-section section-pad" aria-labelledby="admin-audit-title">
    <div class="directory-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Administrator tools</p>
        <h1 id="admin-audit-title">Account<br><em>audit.</em></h1>
        <p>Review registrations, profile details, account status, and assigned roles. Account changes are CSRF-protected and audited. Passwords and password hashes are never displayed.</p>
    </div>

    <form class="directory-filters admin-audit-filters" action="/admin/users" method="get">
        <div class="field-group">
            <label for="account-query">Find an account</label>
            <input id="account-query" name="q" type="search" maxlength="80" value="<?= \AlumniConnect\Support\View::escape($query) ?>" placeholder="Name or email">
        </div>
        <button class="directory-submit" type="submit">Search <span aria-hidden="true">↗</span></button>
    </form>

    <?php if ($auditError !== null): ?>
        <p class="form-message directory-message" role="alert"><?= \AlumniConnect\Support\View::escape($auditError) ?></p>
    <?php endif; ?>

    <div class="directory-results-heading" aria-live="polite">
        <p><?= \AlumniConnect\Support\View::escape($totalAccounts) ?> account<?= $totalAccounts === 1 ? '' : 's' ?> found</p>
        <p>Registration is saved together with the assigned role and initial profile.</p>
    </div>

    <?php if ($accounts === []): ?>
        <div class="directory-empty">
            <h2>No matching accounts.</h2>
            <p>Try searching with another name or email address.</p>
        </div>
    <?php else: ?>
        <div class="admin-audit-table-wrap">
            <table class="admin-audit-table">
                <caption class="visually-hidden">Registered account and profile audit information</caption>
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Role and status</th>
                        <th scope="col">Profile details</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Last sign-in</th>
                        <th scope="col">Account controls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $assignedRoleSlugs = array_filter(explode(',', (string) ($account['role_slugs'] ?? '')));
                        $availableRoles = array_filter(
                            $roles,
                            static fn (array $role): bool => !in_array($role['slug'], $assignedRoleSlugs, true)
                        );
                        ?>
                        <tr>
                            <td data-label="Member">
                                <strong><?= \AlumniConnect\Support\View::escape($account['full_name']) ?></strong>
                                <span><?= \AlumniConnect\Support\View::escape($account['email']) ?></span>
                                <small>ID <?= \AlumniConnect\Support\View::escape($account['id']) ?></small>
                            </td>
                            <td data-label="Role and status">
                                <strong><?= \AlumniConnect\Support\View::escape($account['role_labels'] ?? 'No role assigned') ?></strong>
                                <span><?= \AlumniConnect\Support\View::escape(ucfirst($account['status'])) ?></span>
                                <?php if ($account['verification_status'] !== null): ?>
                                    <small>Alumni: <?= \AlumniConnect\Support\View::escape(ucfirst($account['verification_status'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Profile details">
                                <strong><?= \AlumniConnect\Support\View::escape($account['profile_status']) ?></strong>
                                <?php if ($account['job_title'] !== null || $account['company'] !== null): ?>
                                    <span><?= \AlumniConnect\Support\View::escape(implode(' · ', array_filter([$account['job_title'], $account['company']]))) ?></span>
                                <?php endif; ?>
                                <?php if ($account['department_name'] !== null || $account['graduation_year'] !== null): ?>
                                    <span><?= \AlumniConnect\Support\View::escape(implode(' · ', array_filter([$account['department_name'], $account['graduation_year'] === null ? null : (string) $account['graduation_year']]))) ?></span>
                                <?php endif; ?>
                                <?php if ($account['profile_visibility'] !== null): ?>
                                    <small>Visibility: <?= \AlumniConnect\Support\View::escape($account['profile_visibility']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Registered"><?= \AlumniConnect\Support\View::escape($account['created_at']) ?></td>
                            <td data-label="Last sign-in"><?= $account['last_login_at'] === null ? 'Never' : \AlumniConnect\Support\View::escape($account['last_login_at']) ?></td>
                            <td class="admin-account-controls" data-label="Account controls">
                                <?php if ((int) $account['id'] === (int) $currentUser['id']): ?>
                                    <small>Your own account status and roles cannot be changed here.</small>
                                <?php else: ?>
                                    <form action="/admin/account-status" method="post">
                                        <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                        <input type="hidden" name="user_id" value="<?= \AlumniConnect\Support\View::escape($account['id']) ?>">
                                        <button class="event-rsvp-button <?= $account['status'] === 'active' ? 'event-rsvp-secondary' : '' ?>" type="submit" name="status" value="<?= $account['status'] === 'active' ? 'suspended' : 'active' ?>">
                                            <?= $account['status'] === 'active' ? 'Suspend account' : 'Reactivate account' ?>
                                        </button>
                                    </form>
                                    <details class="admin-role-management">
                                        <summary>Manage roles</summary>
                                        <?php if ($availableRoles !== []): ?>
                                            <form action="/admin/account-role" method="post">
                                                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                                <input type="hidden" name="user_id" value="<?= \AlumniConnect\Support\View::escape($account['id']) ?>">
                                                <input type="hidden" name="action" value="grant">
                                                <label class="visually-hidden" for="grant-role-<?= \AlumniConnect\Support\View::escape($account['id']) ?>">Role to grant</label>
                                                <select id="grant-role-<?= \AlumniConnect\Support\View::escape($account['id']) ?>" name="role" required>
                                                    <?php foreach ($availableRoles as $role): ?>
                                                        <option value="<?= \AlumniConnect\Support\View::escape($role['slug']) ?>"><?= \AlumniConnect\Support\View::escape($role['label']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="event-rsvp-button" type="submit">Grant role</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php foreach ($roles as $role): ?>
                                            <?php if (in_array($role['slug'], $assignedRoleSlugs, true)): ?>
                                                <form action="/admin/account-role" method="post">
                                                    <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                                                    <input type="hidden" name="user_id" value="<?= \AlumniConnect\Support\View::escape($account['id']) ?>">
                                                    <input type="hidden" name="role" value="<?= \AlumniConnect\Support\View::escape($role['slug']) ?>">
                                                    <input type="hidden" name="action" value="revoke">
                                                    <button class="event-rsvp-button event-rsvp-secondary" type="submit">Remove <?= \AlumniConnect\Support\View::escape($role['label']) ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pageCount > 1): ?>
            <nav class="directory-pagination" aria-label="Account audit pages">
                <?php if ($page > 1): ?>
                    <a href="/admin/users?<?= \AlumniConnect\Support\View::escape(http_build_query(['q' => $query, 'page' => $page - 1])) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= \AlumniConnect\Support\View::escape($page) ?> of <?= \AlumniConnect\Support\View::escape($pageCount) ?></span>
                <?php if ($page < $pageCount): ?>
                    <a href="/admin/users?<?= \AlumniConnect\Support\View::escape(http_build_query(['q' => $query, 'page' => $page + 1])) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
