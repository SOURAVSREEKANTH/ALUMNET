<section class="auth-section section-pad" aria-labelledby="auth-title">
    <div class="auth-layout">
        <div class="auth-story">
            <p class="eyebrow"><span class="eyebrow-line"></span> Find your people</p>
            <h1 id="auth-title">Make room<br>for what’s <em>next.</em></h1>
            <p>Create an account to join your college community. Alumni accounts begin with verification pending.</p>
        </div>
        <div class="auth-form-panel">
            <?php if (isset($formError) && $formError !== null): ?>
                <p class="form-message" role="alert"><?= \AlumniConnect\Support\View::escape($formError) ?></p>
            <?php endif; ?>
            <form class="auth-form" action="/register" method="post">
                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                <label for="full-name">Full name</label>
                <input id="full-name" name="full_name" type="text" autocomplete="name" minlength="2" maxlength="100" required value="<?= \AlumniConnect\Support\View::escape($fullName ?? '') ?>">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= \AlumniConnect\Support\View::escape($email ?? '') ?>">
                <label for="account-type">I am joining as</label>
                <select id="account-type" name="account_type" required>
                    <option value="student">Current student</option>
                    <option value="alumni">Alumnus / alumna</option>
                </select>
                <p class="form-hint">Alumni status is reviewed separately. Email access alone does not verify graduation.</p>
                <label for="password">Password <span class="label-hint">12 characters minimum</span></label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
                <label for="password-confirmation">Confirm password</label>
                <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
                <button class="auth-submit" type="submit">Create account <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Already a member? <a href="/login">Sign in</a></p>
        </div>
    </div>
</section>
