<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    header('Location: ' . (must_change_password() ? 'change_password.php' : 'dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (login($username, $password)) {
        $user = current_user();
        if ($user) {
            audit_log($user['id'], 'login', 'User logged in');
        }
        header('Location: ' . (must_change_password() ? 'change_password.php' : 'dashboard.php'));
        exit;
    }

    flash('error', t('invalid_credentials'));
    header('Location: index.php');
    exit;
}

require __DIR__ . '/partials_header.php';
?>
<div class="login-page-wrapper">
    <div class="login-split">
        <aside class="login-hero">
            <span class="login-meta"><i class="bi bi-shield-lock"></i> Internal Payroll Platform</span>
            <h1 class="login-title mt-3 mb-2"><?= e(t('app_name')) ?></h1>
            <p class="mb-0"><?= e(t('login_subtitle')) ?></p>

            <ul class="login-points">
                <li><i class="bi bi-person-gear"></i> <?= e(t('it_admin')) ?>: User Management / System Logs</li>
                <li><i class="bi bi-people"></i> <?= e(t('hr')) ?>: Employee Records / Salary Entry</li>
                <li><i class="bi bi-wallet2"></i> <?= e(t('accounting')) ?>: Payment Approval / Send Payslip</li>
            </ul>
        </aside>

        <section class="login-form-pane">
            <div class="card border-0 bg-transparent shadow-none">
                <div class="card-body p-0">
                    <h2 class="h4 mb-2"><?= e(t('login_title')) ?></h2>
                    <p class="text-muted mb-4"><?= e(t('login_subtitle')) ?></p>
                    <form method="post" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label"><?= e(t('username')) ?></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= e(t('password')) ?></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100 login-submit" type="submit"><?= e(t('sign_in')) ?></button>
                    </form>
                    <hr>
                    <small class="text-muted"><?= e(t('setup_hint')) ?></small>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/partials_footer.php';
