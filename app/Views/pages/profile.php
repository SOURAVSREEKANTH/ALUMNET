<?php
$selectedDepartment = (string) ($formValues['department_id'] ?? '');
$selectedBatch = (string) ($formValues['batch_id'] ?? '');
$selectedVisibility = (string) ($formValues['profile_visibility'] ?? 'members');
$contactSharing = (string) ($formValues['contact_sharing'] ?? '0') === '1';
?>
<section class="profile-section section-pad" aria-labelledby="profile-title">
    <div class="profile-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Your profile, your choice</p>
        <h1 id="profile-title">Tell your<br><em>own story.</em></h1>
        <p>Profile details help signed-in members find relevant people. Your email stays hidden unless you explicitly allow contact sharing.</p>
    </div>

    <div class="profile-panel">
        <?php if ($formError !== null): ?>
            <p class="form-message" role="alert"><?= \AlumniConnect\Support\View::escape($formError) ?></p>
        <?php endif; ?>

        <form class="member-form" action="/profile" method="post">
            <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">

            <div class="profile-form-grid">
                <div class="field-group field-full">
                    <label for="full-name">Full name</label>
                    <input id="full-name" name="full_name" type="text" autocomplete="name" minlength="2" maxlength="100" required value="<?= \AlumniConnect\Support\View::escape($formValues['full_name'] ?? '') ?>">
                </div>

                <div class="field-group field-full">
                    <label for="email">Account email</label>
                    <input id="email" type="email" value="<?= \AlumniConnect\Support\View::escape($profile['email']) ?>" readonly aria-describedby="email-hint">
                    <p class="form-hint" id="email-hint">Email changes are unavailable until an email-verification workflow is added.</p>
                </div>

                <div class="field-group">
                    <label for="department">Department</label>
                    <select id="department" name="department_id">
                        <option value="">Choose later</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= \AlumniConnect\Support\View::escape($department['id']) ?>" <?= (string) $department['id'] === $selectedDepartment ? 'selected' : '' ?>>
                                <?= \AlumniConnect\Support\View::escape($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($departments === []): ?>
                        <p class="form-hint">Department choices have not been configured for this institution yet.</p>
                    <?php endif; ?>
                </div>

                <div class="field-group">
                    <label for="batch">Graduating year</label>
                    <select id="batch" name="batch_id">
                        <option value="">Choose later</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= \AlumniConnect\Support\View::escape($batch['id']) ?>" <?= (string) $batch['id'] === $selectedBatch ? 'selected' : '' ?>>
                                <?= \AlumniConnect\Support\View::escape($batch['department_name'] . ' — ' . $batch['graduation_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Choose a year that belongs to the selected department.</p>
                </div>

                <div class="field-group field-full">
                    <label for="headline">Headline <span class="label-hint">Up to 160 characters</span></label>
                    <input id="headline" name="headline" type="text" maxlength="160" value="<?= \AlumniConnect\Support\View::escape($formValues['headline'] ?? '') ?>" placeholder="e.g. Product designer building education tools">
                </div>

                <div class="field-group">
                    <label for="job-title">Job title</label>
                    <input id="job-title" name="job_title" type="text" maxlength="160" value="<?= \AlumniConnect\Support\View::escape($formValues['job_title'] ?? '') ?>">
                </div>

                <div class="field-group">
                    <label for="company">Company or organization</label>
                    <input id="company" name="company" type="text" maxlength="160" value="<?= \AlumniConnect\Support\View::escape($formValues['company'] ?? '') ?>">
                </div>

                <div class="field-group field-full">
                    <label for="biography">About you <span class="label-hint">Up to 2,000 characters</span></label>
                    <textarea id="biography" name="biography" rows="6" maxlength="2000"><?= \AlumniConnect\Support\View::escape($formValues['biography'] ?? '') ?></textarea>
                </div>

                <fieldset class="field-group field-full privacy-options">
                    <legend>Profile visibility</legend>
                    <label class="choice-row" for="visibility-private">
                        <input id="visibility-private" type="radio" name="profile_visibility" value="private" <?= $selectedVisibility === 'private' ? 'checked' : '' ?>>
                        <span><strong>Private</strong><small>Your profile is not listed in the member directory.</small></span>
                    </label>
                    <label class="choice-row" for="visibility-members">
                        <input id="visibility-members" type="radio" name="profile_visibility" value="members" <?= $selectedVisibility === 'members' ? 'checked' : '' ?>>
                        <span><strong>Members</strong><small>Visible to signed-in AlumniConnect accounts.</small></span>
                    </label>
                    <label class="choice-row" for="visibility-public">
                        <input id="visibility-public" type="radio" name="profile_visibility" value="public" <?= $selectedVisibility === 'public' ? 'checked' : '' ?>>
                        <span><strong>Public listing</strong><small>Listed in the signed-in directory. Anonymous profile pages are not part of this phase.</small></span>
                    </label>
                </fieldset>

                <div class="field-group field-full">
                    <input type="hidden" name="contact_sharing" value="0">
                    <label class="choice-row" for="contact-sharing">
                        <input id="contact-sharing" type="checkbox" name="contact_sharing" value="1" <?= $contactSharing ? 'checked' : '' ?>>
                        <span><strong>Allow members to see my account email</strong><small>Off by default. You can change this setting at any time.</small></span>
                    </label>
                </div>
            </div>

            <button class="auth-submit" type="submit">Save profile <span aria-hidden="true">↗</span></button>
        </form>

        <?php if (in_array('alumni', array_column($currentUser['roles'], 'slug'), true)): ?>
            <p class="profile-verification">
                Alumni verification:
                <strong><?= ($profile['verification_status'] ?? 'pending') === 'verified' ? 'Verified' : 'Pending administrator review' ?></strong>.
                Editing your profile does not change verification status.
            </p>
        <?php endif; ?>
    </div>
</section>
