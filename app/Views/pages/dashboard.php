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
        <p class="section-label"><span>01</span> Your shortcuts</p>
        <h2>Where would you like to go?</h2>
        <div class="dashboard-links">
            <a class="dashboard-link-card" href="/profile">
                <span class="dashboard-link-number">01</span>
                <span class="dashboard-link-copy"><strong>My profile</strong><span>Update your details and privacy settings.</span></span>
                <span class="dashboard-link-action">Open profile</span>
            </a>
            <a class="dashboard-link-card" href="/directory">
                <span class="dashboard-link-number">02</span>
                <span class="dashboard-link-copy"><strong>Member directory</strong><span>Find alumni and students by name, work, or class.</span></span>
                <span class="dashboard-link-action">Browse members</span>
            </a>
            <a class="dashboard-link-card" href="/events">
                <span class="dashboard-link-number">03</span>
                <span class="dashboard-link-copy"><strong>Events</strong><span>Discover events and manage your RSVPs.</span></span>
                <span class="dashboard-link-action">View events</span>
            </a>
            <a class="dashboard-link-card" href="/community">
                <span class="dashboard-link-number">04</span>
                <span class="dashboard-link-copy"><strong>Community</strong><span>Explore mentorship, opportunities, and meetups.</span></span>
                <span class="dashboard-link-action">Explore community</span>
            </a>
            <a class="dashboard-link-card" href="/memory-lane">
                <span class="dashboard-link-number">05</span>
                <span class="dashboard-link-copy"><strong>Memory Lane</strong><span>Revisit past events and shared memories.</span></span>
                <span class="dashboard-link-action">View memories</span>
            </a>
            <a class="dashboard-link-card" href="/notifications">
                <span class="dashboard-link-number">06</span>
                <span class="dashboard-link-copy"><strong>Notifications</strong><span>Check updates and requests for your account.</span></span>
                <span class="dashboard-link-action">View updates</span>
            </a>
            <?php if (\AlumniConnect\Security\Auth::hasRole($currentUser, 'admin')): ?>
                <a class="dashboard-link-card" href="/admin/operations">
                    <span class="dashboard-link-number">07</span>
                    <span class="dashboard-link-copy"><strong>Admin operations</strong><span>Review members, photos, and service activity.</span></span>
                    <span class="dashboard-link-action">Open admin tools</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
