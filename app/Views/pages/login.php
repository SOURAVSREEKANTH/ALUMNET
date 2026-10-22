<section class="auth-section section-pad" aria-labelledby="auth-title">
    <div class="auth-layout">
        <div class="auth-story">
            <p class="eyebrow"><span class="eyebrow-line"></span> Welcome back</p>
            <h1 id="auth-title">Pick up<br>where you <em>left off.</em></h1>
            <p>Sign in to reconnect with your college community.</p>
        </div>
        <div class="auth-form-panel">
            <?php if (isset($formError) && $formError !== null): ?>
                <p class="form-message" role="alert"><?= \AlumniConnect\Support\View::escape($formError) ?></p>
            <?php endif; ?>
            <form class="auth-form" action="/login" method="post">
                <input type="hidden" name="_csrf" value="<?= \AlumniConnect\Support\View::escape(\AlumniConnect\Security\Csrf::token()) ?>">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required value="<?= \AlumniConnect\Support\View::escape($email ?? '') ?>">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" required>
                <button class="auth-submit" type="submit">Sign in <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">New to AlumniConnect? <a href="/register">Create an account</a></p>
        </div>
    </div>
</section>
