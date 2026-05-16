<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['admin_it']);

$pdo = db();

// IT can review technical/system access events only, without payroll-related actions.
$safeActions = [
    'login',
    'logout',
    'create_user',
    'reset_password',
    'toggle_active',
    'remove_user',
    'create_employee',
];

$placeholders = implode(',', array_fill(0, count($safeActions), '?'));
$stmt = $pdo->prepare(
    'SELECT al.created_at, u.full_name, u.username, al.action, al.details
     FROM audit_logs al
     JOIN users u ON u.id = al.user_id
     WHERE al.action IN (' . $placeholders . ')
     ORDER BY al.created_at DESC
     LIMIT 200'
);
$stmt->execute($safeActions);
$logs = $stmt->fetchAll();

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('system_logs_title')) ?></h1>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('log_time')) ?></th>
                        <th><?= e(t('log_user')) ?></th>
                        <th><?= e(t('log_action')) ?></th>
                        <th><?= e(t('log_details')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted"><?= e(t('no_logs')) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php
                                $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$log['created_at']);
                                if ($dt) {
                                    $buddhistYear = (int)$dt->format('Y') + 543;
                                    echo e($dt->format('d/m/') . $buddhistYear . $dt->format(' H:i:s'));
                                } else {
                                    echo e((string)$log['created_at']);
                                }
                            ?></td>
                            <td><?= e($log['full_name'] . ' (' . $log['username'] . ')') ?></td>
                            <td><?= e((string)$log['action']) ?></td>
                            <td><?= e((string)$log['details']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials_footer.php';
