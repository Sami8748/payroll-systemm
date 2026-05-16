<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['admin_it']);

$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        $fullName = trim((string)($_POST['full_name'] ?? ''));

        if ($username === '' || $password === '' || $fullName === '' || !in_array($role, ['admin_it', 'hr', 'accounting', 'ceo'], true)) {
            flash('error', t('invalid_input'));
            header('Location: user_admin.php');
            exit;
        }

        if (!password_meets_policy($password)) {
            flash('error', t('password_policy_failed'));
            header('Location: user_admin.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, is_active, password_changed_at, created_at)
                VALUES (:username, :password_hash, :role, :full_name, 1, :password_changed_at, :created_at)');
            $stmt->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'full_name' => $fullName,
                'password_changed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            audit_log($user['id'], 'create_user', 'Created user ' . $username . ' role ' . $role);
            flash('success', t('user_created'));
        } catch (Throwable $e) {
            flash('error', t('user_create_failed'));
        }

        header('Location: user_admin.php');
        exit;
    }

    if ($action === 'reset_password') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($targetUserId <= 0) {
            flash('error', t('invalid_password_input'));
            header('Location: user_admin.php');
            exit;
        }

        if (!password_meets_policy($newPassword)) {
            flash('error', t('password_policy_failed'));
            header('Location: user_admin.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = :password_changed_at WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'id' => $targetUserId,
        ]);

        audit_log($user['id'], 'reset_password', 'Reset password for user ID ' . $targetUserId);
        flash('success', t('password_reset_complete'));
        header('Location: user_admin.php');
        exit;
    }

    if ($action === 'toggle_active') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId === $user['id']) {
            flash('error', t('cannot_disable_current_user'));
            header('Location: user_admin.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);

        audit_log($user['id'], 'toggle_active', 'Toggled active status for user ID ' . $targetUserId);
        flash('success', t('user_status_updated'));
        header('Location: user_admin.php');
        exit;
    }

    if ($action === 'remove_user') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId === $user['id']) {
            flash('error', t('cannot_disable_current_user'));
            header('Location: user_admin.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);

        audit_log($user['id'], 'remove_user', 'Removed user ID ' . $targetUserId);
        flash('success', t('user_removed'));
        header('Location: user_admin.php');
        exit;
    }

    if ($action === 'backup_db') {
        $config = app_config();
        $dbPath = (string)$config['db_path'];
        $backupDir = __DIR__ . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $backupFile = $backupDir . '/payroll_backup_' . date('Ymd_His') . '.sqlite';
        if (!is_file($dbPath)) {
            flash('error', t('backup_failed'));
            header('Location: user_admin.php');
            exit;
        }

        if (@copy($dbPath, $backupFile)) {
            audit_log($user['id'], 'backup_db', 'Database backup created: ' . basename($backupFile));
            flash('success', t('backup_success') . ' ' . basename($backupFile));
        } else {
            flash('error', t('backup_failed'));
        }

        header('Location: user_admin.php');
        exit;
    }
}

$users = $pdo->query('SELECT id, username, role, full_name, is_active, created_at FROM users ORDER BY id ASC')->fetchAll();

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('it_admin_user_management')) ?></h1>

<div class="card shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <h2 class="h5 mb-1"><?= e(t('password_policy')) ?></h2>
            <p class="text-muted mb-0"><?= e(t('password_policy_hint')) ?></p>
        </div>
        <form method="post" class="m-0">
            <input type="hidden" name="action" value="backup_db">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-download me-1"></i><?= e(t('database_backup')) ?></button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5"><?= e(t('create_internal_user')) ?></h2>
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="create_user">
            <div class="col-md-3">
                <label class="form-label"><?= e(t('username')) ?></label>
                <input class="form-control" type="text" name="username" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('password')) ?></label>
                <input class="form-control" type="password" name="password" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('full_name')) ?></label>
                <input class="form-control" type="text" name="full_name" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('role')) ?></label>
                <select class="form-select" name="role" required>
                    <option value="admin_it"><?= e(t('it_admin')) ?></option>
                    <option value="hr"><?= e(t('hr')) ?></option>
                    <option value="accounting"><?= e(t('accounting')) ?></option>
                    <option value="ceo"><?= e(t('ceo')) ?></option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit"><?= e(t('create')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5"><?= e(t('existing_users')) ?></h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= e(t('username')) ?></th>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('role')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th><?= e(t('reset_password')) ?></th>
                        <th><?= e(t('enable_disable')) ?></th>
                        <th><?= e(t('delete_user')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= e($u['username']) ?></td>
                            <td><?= e($u['full_name']) ?></td>
                            <td><?= e(role_label($u['role'])) ?></td>
                            <td>
                                <span class="badge <?= (int)$u['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int)$u['is_active'] === 1 ? e(t('active')) : e(t('inactive')) ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input class="form-control form-control-sm" type="password" name="new_password" placeholder="<?= e(t('new_password')) ?>" required>
                                    <button class="btn btn-warning btn-sm" type="submit"><?= e(t('reset')) ?></button>
                                </form>
                            </td>
                            <td>
                                <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                    <span class="text-muted"><?= e(t('current_user')) ?></span>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-outline-secondary btn-sm" type="submit"><?= e(t('toggle')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><?= e(t('delete_user')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials_footer.php';
