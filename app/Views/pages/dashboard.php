<section class="dashboard-section section-pad" aria-labelledby="dashboard-title">
    <div class="dashboard-heading">
        <p class="eyebrow"><span class="eyebrow-line"></span> Your community, in one place</p>
        <h1 id="dashboard-title">Welcome back,<br><em><?= \AlumniConnect\Support\View::escape($currentUser['full_name']) ?>.</em></h1>
        <p class="dashboard-email"><?= \AlumniConnect\Support\View::escape($currentUser['email']) ?></p>
    </div>
    <div class="dashboard-details">
        <div>
            <span class="detail-label">ACCOUNT ROLE<?= count($currentUser['roles']) === 1 ? '' : 'S' ?></span>
            <div class="role-list">
                <?php foreach ($currentUser['roles'] as $role): ?>
                    <span class="role-label"><?= \AlumniConnect\Support\View::escape($role['label']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (in_array('alumni', array_column($currentUser['roles'], 'slug'), true)): ?>
            <div>
                <span class="detail-label">ALUMNI VERIFICATION</span>
                <p class="verification-note">
                    <?= $currentUser['verification_status'] === 'verified'
                        ? 'Verified by an administrator.'
                        : 'Pending administrator review. Email access does not verify graduation.' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <div class="dashboard-next">
        <p class="section-label"><span>01</span> Building the network</p>
        <h2>Make your profile yours.</h2>
        <p>Add a short introduction and choose how your profile appears to other signed-in members. Search the member directory using names, professional details, departments, or graduating years.</p>
        <div class="dashboard-actions">
            <a class="text-link" href="/profile">Edit your profile <span aria-hidden="true">↗</span></a>
            <a class="text-link" href="/directory">Explore the directory <span aria-hidden="true">↗</span></a>
            <a class="text-link" href="/events">Find an event <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</section>
