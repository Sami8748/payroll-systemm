<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
if (!$user) {
    header('Location: index.php');
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword !== $confirmPassword) {
        flash('error', t('password_confirm_mismatch'));
        header('Location: change_password.php');
        exit;
    }

    if (!password_meets_policy($newPassword)) {
        flash('error', t('password_policy_failed'));
        header('Location: change_password.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, (string)$row['password_hash'])) {
        flash('error', t('current_password_invalid'));
        header('Location: change_password.php');
        exit;
    }

    $upd = $pdo->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = :password_changed_at WHERE id = :id');
    $upd->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'password_changed_at' => date('Y-m-d H:i:s'),
        'id' => (int)$user['id'],
    ]);

    $_SESSION['user']['password_expired'] = false;
    audit_log((int)$user['id'], 'change_password', 'User changed own password');

    flash('success', t('password_changed_success'));
    header('Location: dashboard.php');
    exit;
}

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('change_password')) ?></h1>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (must_change_password()): ?>
            <div class="alert alert-warning"><?= e(t('password_expired_notice')) ?></div>
        <?php endif; ?>

        <p class="text-muted mb-3"><?= e(t('password_policy_hint')) ?></p>

        <form method="post" class="row g-3" autocomplete="off">
            <div class="col-md-4">
                <label class="form-label"><?= e(t('current_password')) ?></label>
                <input class="form-control" type="password" name="current_password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('new_password')) ?></label>
                <input class="form-control" type="password" name="new_password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= e(t('confirm_password')) ?></label>
                <input class="form-control" type="password" name="confirm_password" required>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><?= e(t('change_password')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials_footer.php';
