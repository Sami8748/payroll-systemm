<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['accounting']);

$user = current_user();
$pdo = db();

$processedInfo = process_due_scheduled_sends();
if (($processedInfo['processed_jobs'] ?? 0) > 0) {
    flash('success', t('run_summary') . ': ' . (int)$processedInfo['processed_jobs'] . ' jobs');
}

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}
if ($year < 2000 || $year > 2700) {
    $year = (int)date('Y');
}

$downloadFile = trim((string)($_GET['file'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate_ssf') {
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));

        try {
            $file = generate_social_security_report_csv($month, $year);
            audit_log((int)$user['id'], 'generate_ssf_report', 'Generated SSF report ' . $file);
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year . '&file=' . urlencode($file));
            exit;
        } catch (Throwable $e) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }

    if ($action === 'generate_tax') {
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));

        try {
            $file = generate_tax_report_csv($month, $year);
            audit_log((int)$user['id'], 'generate_tax_report', 'Generated PND1 report ' . $file);
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year . '&file=' . urlencode($file));
            exit;
        } catch (Throwable $e) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }

    if ($action === 'generate_tax_rd_prep') {
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));

        try {
            $file = generate_tax_report_rd_prep_txt($month, $year);
            audit_log((int)$user['id'], 'generate_tax_report_rd_prep', 'Generated PND1 RD Prep report ' . $file);
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year . '&file=' . urlencode($file));
            exit;
        } catch (Throwable $e) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }

    if ($action === 'generate_sso_609') {
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));

        try {
            $file = generate_sso_6_09_csv($month, $year);
            audit_log((int)$user['id'], 'generate_sso_609_report', 'Generated SSO 6-09 report ' . $file);
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year . '&file=' . urlencode($file));
            exit;
        } catch (Throwable $e) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }

    if ($action === 'create_schedule') {
        $month = (int)($_POST['month'] ?? date('n'));
        $year = (int)($_POST['year'] ?? date('Y'));
        $channel = (string)($_POST['channel'] ?? 'email');
        $sendAtRaw = trim((string)($_POST['send_at'] ?? ''));

        if (!in_array($channel, ['email', 'line'], true)) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }

        if ($channel === 'line' && !is_line_delivery_enabled()) {
            flash('error', 'LINE delivery is disabled in config.php (line_enabled=false).');
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }

        $sendAtTs = strtotime($sendAtRaw);
        if ($sendAtTs === false) {
            flash('error', t('invalid_input'));
            header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
            exit;
        }

        $sendAt = date('Y-m-d H:i:s', $sendAtTs);
        create_scheduled_send($month, $year, $channel, $sendAt, (int)$user['id']);
        audit_log((int)$user['id'], 'create_scheduled_send', 'Created schedule for ' . $month . '/' . $year . ' at ' . $sendAt . ' channel=' . $channel);

        flash('success', t('schedule_created'));
        header('Location: accounting_tools.php?month=' . $month . '&year=' . $year);
        exit;
    }
}

$schedulesStmt = $pdo->query('SELECT s.*, u.username AS created_by_username
    FROM scheduled_sends s
    LEFT JOIN users u ON u.id = s.created_by
    ORDER BY s.id DESC
    LIMIT 50');
$schedules = $schedulesStmt->fetchAll();

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('accounting_tools')) ?></h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('government_compliance_reports')) ?></h2>
        <form method="post" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label"><?= e(t('month')) ?></label>
                <input class="form-control" type="number" min="1" max="12" name="month" value="<?= (int)$month ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('year')) ?></label>
                <input class="form-control" type="number" min="2000" max="2700" name="year" value="<?= (int)$year ?>" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit" name="action" value="generate_ssf"><?= e(t('generate_social_security_report')) ?></button>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary" type="submit" name="action" value="generate_tax"><?= e(t('generate_tax_report')) ?></button>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary" type="submit" name="action" value="generate_tax_rd_prep">Generate Tax Report (RD Prep TXT)</button>
            </div>
        </form>

        <?php if ($downloadFile !== ''): ?>
            <div class="alert alert-success mt-3 mb-0">
                <?= e(t('report_generated')) ?>
                <a href="download_report.php?file=<?= urlencode($downloadFile) ?>"><?= e(t('download_generated_report')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">🚪 <?= e(t('sso_609_title')) ?></h2>
        <p class="text-muted small mb-3">ออกรายงานพนักงานที่มีวันลาออก (end_date) ตรงกับเดือน/ปีที่เลือก เพื่อส่งประกันสังคม สปส. 6-09</p>
        <form method="post" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label"><?= e(t('month')) ?></label>
                <input class="form-control" type="number" min="1" max="12" name="month" value="<?= (int)$month ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('year')) ?></label>
                <input class="form-control" type="number" min="2000" max="2700" name="year" value="<?= (int)$year ?>" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-warning" type="submit" name="action" value="generate_sso_609"><?= e(t('generate_sso_609')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('payday_settings')) ?></h2>
        <p class="text-muted mb-3"><?= e(t('scheduled_sending')) ?></p>
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="create_schedule">
            <div class="col-md-2">
                <label class="form-label"><?= e(t('month')) ?></label>
                <input class="form-control" type="number" min="1" max="12" name="month" value="<?= (int)$month ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('year')) ?></label>
                <input class="form-control" type="number" min="2000" max="2700" name="year" value="<?= (int)$year ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('channel')) ?></label>
                <select class="form-select" name="channel">
                    <option value="email">Email</option>
                    <?php if (is_line_delivery_enabled()): ?>
                        <option value="line">LINE</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('schedule_send_at')) ?></label>
                <input class="form-control" type="datetime-local" name="send_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+1 day 09:00'))) ?>" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit"><?= e(t('scheduled_sending')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('scheduled_jobs')) ?></h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= e(t('period')) ?></th>
                        <th><?= e(t('channel')) ?></th>
                        <th><?= e(t('send_time')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th><?= e(t('run_summary')) ?></th>
                        <th><?= e(t('processed_time')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$schedules): ?>
                        <tr><td colspan="7" class="text-center text-muted"><?= e(t('no_records')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><?= (int)$s['id'] ?></td>
                            <td><?= (int)$s['month'] ?>/<?= (int)$s['year'] ?></td>
                            <td><?= e(strtoupper((string)$s['channel'])) ?></td>
                            <td><?= e((string)$s['send_at']) ?></td>
                            <td>
                                <span class="badge <?= $s['status'] === 'completed' ? 'bg-success' : ($s['status'] === 'failed' ? 'bg-danger' : 'bg-secondary') ?>">
                                    <?= e(t((string)$s['status'])) ?>
                                </span>
                            </td>
                            <td><?= (int)$s['success_count'] ?> / <?= (int)$s['failed_count'] ?></td>
                            <td><?= e((string)($s['processed_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials_footer.php';
