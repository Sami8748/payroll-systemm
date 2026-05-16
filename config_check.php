<?php


declare(strict_types=1); /*การประกาศ strict_types เป็น true เพื่อเปิดใช้งานการตรวจสอบประเภทข้อมูลอย่างเข้มงวดใน PHP ซึ่งจะช่วยให้โค้ดมีความปลอดภัยและลดข้อผิดพลาดที่เกิดจากการใช้ประเภทข้อมูลที่ไม่ถูกต้อง*/

require_once __DIR__ . '/functions.php';
require_role(['admin_it']);

ensure_composer_autoload();

$user = current_user();
$config = app_config();
$lineEnabled = is_line_delivery_enabled();
$testEmailInput = '';
$testLineInput = '';

$ssoCap = (float)get_global_constant('sso_wage_ceiling_be_2569_onward', 17500.0);
$taxBrackets = get_global_constant('tax_brackets', default_tax_brackets());
if (!is_array($taxBrackets) || !$taxBrackets) {
    $taxBrackets = default_tax_brackets();
}
usort($taxBrackets, static function ($a, $b): int {
    $upperA = is_array($a) ? (float)($a['upper'] ?? 0.0) : 0.0;
    $upperB = is_array($b) ? (float)($b['upper'] ?? 0.0) : 0.0;
    return $upperA <=> $upperB;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_constants') {
        $ssoCapInput = (float)($_POST['sso_cap'] ?? 0);
        $upperLimits = $_POST['tax_upper_limit'] ?? [];
        $ratePercents = $_POST['tax_rate_percent'] ?? [];

        $parsedBrackets = [];
        if (is_array($upperLimits) && is_array($ratePercents)) {
            $count = min(count($upperLimits), count($ratePercents));
            for ($i = 0; $i < $count; $i++) {
                $upper = (float)$upperLimits[$i];
                $ratePercent = (float)$ratePercents[$i];
                if ($upper <= 0 || $ratePercent < 0) {
                    continue;
                }

                $parsedBrackets[] = [
                    'upper' => $upper,
                    'rate' => round($ratePercent / 100.0, 6),
                ];
            }
        }

        usort($parsedBrackets, static fn(array $a, array $b): int => $a['upper'] <=> $b['upper']);

        if ($ssoCapInput <= 0 || !$parsedBrackets) {
            flash('error', 'Invalid global constants input. Please provide valid SSO cap and tax brackets.');
            header('Location: config_check.php');
            exit;
        }

        set_global_constant('sso_wage_ceiling_be_2569_onward', $ssoCapInput, (int)$user['id']);
        set_global_constant('tax_brackets', $parsedBrackets, (int)$user['id']);
        audit_log((int)$user['id'], 'update_global_constants', 'Updated SSO cap and tax brackets from Config Check');

        flash('success', t('constants_saved'));
        header('Location: config_check.php');
        exit;
    }

    if ($action === 'test_email') {
        $testEmailInput = trim((string)($_POST['test_email'] ?? ''));
        $error = null;
        $ok = send_test_email($testEmailInput, $error);

        if ($ok) {
            flash('success', 'Test Email sent successfully to ' . $testEmailInput);
        } else {
            flash('error', 'Test Email failed: ' . ($error ?? 'Unknown error'));
        }

        header('Location: config_check.php');
        exit;
    }

    if ($action === 'test_line') {
        if (!$lineEnabled) {
            flash('error', 'LINE delivery is disabled in config.php (line_enabled=false).');
            header('Location: config_check.php');
            exit;
        }

        $testLineInput = trim((string)($_POST['test_line_user_id'] ?? ''));
        $error = null;
        $ok = send_test_line($testLineInput, $error);

        if ($ok) {
            flash('success', 'Test LINE push sent successfully to ' . $testLineInput);
        } else {
            flash('error', 'Test LINE failed: ' . ($error ?? 'Unknown error'));
        }

        header('Location: config_check.php');
        exit;
    }
}

$checks = [];

$addCheck = static function (string $title, bool $ok, string $detail) use (&$checks): void {
    $checks[] = [
        'title' => $title,
        'ok' => $ok,
        'detail' => $detail,
    ];
};

$requiredSmtp = ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_username', 'smtp_password'];
$missingSmtp = [];
foreach ($requiredSmtp as $key) {
    $value = (string)($config[$key] ?? '');
    if (trim($value) === '') {
        $missingSmtp[] = $key;
    }
}
$addCheck(
    'SMTP configuration',
    count($missingSmtp) === 0,
    count($missingSmtp) === 0 ? 'Ready' : ('Missing: ' . implode(', ', $missingSmtp))
);

$smtpReachable = false;
$smtpDetail = 'Skipped (smtp_host or smtp_port missing)';
if (trim((string)$config['smtp_host']) !== '' && (int)$config['smtp_port'] > 0) {
    $errno = 0;
    $errstr = '';
    $conn = @fsockopen((string)$config['smtp_host'], (int)$config['smtp_port'], $errno, $errstr, 5);
    if (is_resource($conn)) {
        fclose($conn);
        $smtpReachable = true;
        $smtpDetail = 'Reachable';
    } else {
        $smtpDetail = 'Not reachable: ' . $errstr;
    }
}
$addCheck('SMTP host connectivity', $smtpReachable, $smtpDetail);

$autoloadReady = is_file(__DIR__ . '/vendor/autoload.php');
$addCheck('Composer autoload', $autoloadReady, $autoloadReady ? 'vendor/autoload.php found' : 'vendor/autoload.php missing');

$phpMailerReady = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
$addCheck('PHPMailer class', $phpMailerReady, $phpMailerReady ? 'Detected' : 'Missing class PHPMailer\\PHPMailer\\PHPMailer');

$tcpdfReady = class_exists('TCPDF');
$addCheck('TCPDF class', $tcpdfReady, $tcpdfReady ? 'Detected' : 'Missing class TCPDF');

$addCheck('LINE delivery mode', $lineEnabled, $lineEnabled ? 'Enabled' : 'Disabled (Email-only mode)');

if ($lineEnabled) {
    $lineToken = trim((string)($config['line_channel_access_token'] ?? ''));
    $lineTokenReady = $lineToken !== '';
    $addCheck('LINE channel access token', $lineTokenReady, $lineTokenReady ? 'Configured' : 'Missing line_channel_access_token in config.php');

    $lineApiBase = trim((string)($config['line_api_base'] ?? ''));
    $lineApiOk = str_starts_with($lineApiBase, 'https://api.line.me/');
    $addCheck('LINE API endpoint', $lineApiOk, $lineApiOk ? $lineApiBase : 'Invalid endpoint: ' . $lineApiBase);
}

$curlReady = extension_loaded('curl');
$addCheck(
    'PHP cURL extension',
    $lineEnabled ? $curlReady : true,
    $lineEnabled
        ? ($curlReady ? 'Enabled' : 'Enable curl extension in php.ini')
        : 'Skipped (LINE disabled)'
);

$ssoCapCheckOk = $ssoCap > 0;
$addCheck(
    'SSO cap constant',
    $ssoCapCheckOk,
    $ssoCapCheckOk ? ('Current: ' . number_format($ssoCap, 2) . ' THB') : 'Invalid SSO cap value'
);

$taxBracketsCheckOk = is_array($taxBrackets) && count($taxBrackets) > 0;
$addCheck(
    'Tax brackets constant',
    $taxBracketsCheckOk,
    $taxBracketsCheckOk ? ('Rows: ' . count($taxBrackets)) : 'No valid tax bracket rows'
);

$allReady = true;
foreach ($checks as $check) {
    if (!$check['ok']) {
        $allReady = false;
        break;
    }
}

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3">Config Check</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <h2 class="h5 mb-1">Email Delivery Readiness</h2>
            <p class="text-muted mb-0">Use this checklist before sending payslips.</p>
        </div>
        <a class="btn btn-primary" href="config_check.php?run=1">Run Check</a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Manual Test Delivery</h2>
        <div class="row g-3">
            <div class="col-lg-6">
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="test_email">
                    <div class="col-12">
                        <label class="form-label">Test Email Address</label>
                        <input type="email" name="test_email" class="form-control" placeholder="example@domain.com" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Test Email</button>
                    </div>
                </form>
            </div>
            <?php if ($lineEnabled): ?>
                <div class="col-lg-6">
                    <form method="post" class="row g-2">
                        <input type="hidden" name="action" value="test_line">
                        <div class="col-12">
                            <label class="form-label">Test LINE User ID</label>
                            <input type="text" name="test_line_user_id" class="form-control" placeholder="LINE destination / user id" required>
                            <small class="text-muted">Use the destination id from your LINE messaging flow.</small>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Test LINE</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="col-lg-6">
                    <div class="alert alert-info mb-0">LINE delivery is currently disabled. Set line_enabled to true in config.php if you want to enable it later.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('global_constants')) ?></h2>
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="save_constants">

            <div class="col-lg-4">
                <label class="form-label"><?= e(t('sso_cap_update')) ?></label>
                <input type="number" step="0.01" min="0" name="sso_cap" class="form-control" value="<?= e((string)$ssoCap) ?>" required>
                <small class="text-muted">Example: 17500</small>
            </div>

            <div class="col-12">
                <label class="form-label"><?= e(t('tax_rates_update')) ?></label>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50%;"><?= e(t('upper_limit')) ?></th>
                                <th style="width: 50%;"><?= e(t('rate_percent')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxBrackets as $row): ?>
                                <?php
                                    $upper = is_array($row) ? (float)($row['upper'] ?? 0) : 0.0;
                                    $ratePercent = is_array($row) ? ((float)($row['rate'] ?? 0.0) * 100.0) : 0.0;
                                ?>
                                <tr>
                                    <td><input type="number" step="0.01" min="0" name="tax_upper_limit[]" class="form-control" value="<?= e((string)$upper) ?>" required></td>
                                    <td><input type="number" step="0.01" min="0" name="tax_rate_percent[]" class="form-control" value="<?= e((string)$ratePercent) ?>" required></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">Edit rows in ascending income range order. Rate is percent (e.g., 5 for 5%).</small>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit"><?= e(t('save_constants')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="alert <?= $allReady ? 'alert-success' : 'alert-warning' ?> mb-3">
            <?= $allReady ? ($lineEnabled ? 'System is ready for Email and LINE payslip delivery.' : 'System is ready for Email payslip delivery.') : 'System is NOT ready yet. Please fix failed checklist items below.' ?>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Item</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td>
                                <span class="badge <?= $check['ok'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $check['ok'] ? 'PASS' : 'FAIL' ?>
                                </span>
                            </td>
                            <td><?= e($check['title']) ?></td>
                            <td><?= e($check['detail']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$allReady): ?>
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5">Quick Fix</h2>
        <ol class="mb-0">
            <li>Set SMTP values in config.php: smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password</li>
            <?php if ($lineEnabled): ?>
                <li>Set line_channel_access_token in config.php</li>
                <li>Update employee line_user_id to a real LINE destination id used by your bot flow</li>
            <?php endif; ?>
        </ol>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials_footer.php';
