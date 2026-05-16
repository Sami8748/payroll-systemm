<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_role(['hr']);

$user = current_user();
$pdo  = db();

/* ──────────────────────────────────────────────────────────────────────── */
/* POST handlers                                                            */
/* ──────────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    /* Record leave */
    if ($action === 'record_leave') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $leaveType  = trim((string)($_POST['leave_type'] ?? ''));
        $leaveDate  = trim((string)($_POST['leave_date'] ?? ''));
        $days       = (float)($_POST['days'] ?? 0);
        $note       = trim((string)($_POST['note'] ?? ''));

        $validTypes = ['sick', 'annual', 'other'];
        if ($employeeId <= 0 || !in_array($leaveType, $validTypes, true)) {
            flash('error', t('invalid_input'));
            header('Location: leave.php');
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaveDate)) {
            flash('error', t('leave_invalid_date'));
            header('Location: leave.php');
            exit;
        }
        if ($days <= 0) {
            flash('error', t('leave_invalid_days'));
            header('Location: leave.php');
            exit;
        }

        /* Confirm employee exists and is active non-manager */
        $empStmt = $pdo->prepare('SELECT id, name FROM employees WHERE id = :id AND is_active = 1 AND position != "Manager"');
        $empStmt->execute(['id' => $employeeId]);
        $emp = $empStmt->fetch();
        if (!$emp) {
            flash('error', t('employee_not_found'));
            header('Location: leave.php');
            exit;
        }

        try {
            $insertStmt = $pdo->prepare('INSERT INTO leave_records
                (employee_id, leave_type, leave_date, days, note, recorded_by, created_at)
                VALUES (:employee_id, :leave_type, :leave_date, :days, :note, :recorded_by, :created_at)');
            $insertStmt->execute([
                'employee_id' => $employeeId,
                'leave_type'  => $leaveType,
                'leave_date'  => $leaveDate,
                'days'        => $days,
                'note'        => $note,
                'recorded_by' => (int)$user['id'],
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            audit_log((int)$user['id'], 'record_leave', "Recorded {$leaveType} leave for employee ID {$employeeId} on {$leaveDate} ({$days} day(s))");
            flash('success', t('leave_recorded'));
        } catch (Throwable $e) {
            flash('error', t('leave_record_failed'));
        }

        header('Location: leave.php?emp=' . $employeeId);
        exit;
    }

    /* Delete leave record */
    if ($action === 'delete_leave') {
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        if ($leaveId <= 0) {
            flash('error', t('invalid_input'));
            header('Location: leave.php');
            exit;
        }

        /* Only allow deleting records that belong to non-manager employees */
        $checkStmt = $pdo->prepare('SELECT lr.id FROM leave_records lr
            JOIN employees e ON e.id = lr.employee_id
            WHERE lr.id = :id AND e.position != "Manager"');
        $checkStmt->execute(['id' => $leaveId]);
        if (!$checkStmt->fetch()) {
            flash('error', t('leave_delete_failed'));
            header('Location: leave.php');
            exit;
        }

        try {
            $delStmt = $pdo->prepare('DELETE FROM leave_records WHERE id = :id');
            $delStmt->execute(['id' => $leaveId]);
            audit_log((int)$user['id'], 'delete_leave', "Deleted leave record ID {$leaveId}");
            flash('success', t('leave_deleted'));
        } catch (Throwable $e) {
            flash('error', t('leave_delete_failed'));
        }

        $backEmp = (int)($_POST['back_employee_id'] ?? 0);
        header('Location: leave.php' . ($backEmp > 0 ? '?emp=' . $backEmp : ''));
        exit;
    }
}

/* ──────────────────────────────────────────────────────────────────────── */
/* Data for page                                                            */
/* ──────────────────────────────────────────────────────────────────────── */
$selectedEmpId = (int)($_GET['emp'] ?? 0);
$currentYear   = (int)date('Y');

/* Employee list (active non-manager) */
$empListStmt = $pdo->query('SELECT id, emp_code, name, department, sick_leave_quota, annual_leave_quota
    FROM employees
    WHERE is_active = 1 AND position != "Manager"
    ORDER BY emp_code ASC');
$empList = $empListStmt->fetchAll();

/* Build a quick map: employee_id → quota info */
$empQuotaMap = [];
foreach ($empList as $e) {
    $empQuotaMap[(int)$e['id']] = [
        'name'               => $e['name'],
        'emp_code'           => $e['emp_code'],
        'sick_leave_quota'   => (int)$e['sick_leave_quota'],
        'annual_leave_quota' => (int)$e['annual_leave_quota'],
    ];
}

/* Selected employee detail + usage */
$selectedEmp   = null;
$usedSick      = 0.0;
$usedAnnual    = 0.0;
$leaveHistory  = [];

if ($selectedEmpId > 0 && isset($empQuotaMap[$selectedEmpId])) {
    $selectedEmp = $empQuotaMap[$selectedEmpId];

    /* Usage this year */
    $usageStmt = $pdo->prepare('SELECT leave_type, COALESCE(SUM(days), 0) AS used
        FROM leave_records
        WHERE employee_id = :emp_id
          AND substr(leave_date, 1, 4) = :year
        GROUP BY leave_type');
    $usageStmt->execute(['emp_id' => $selectedEmpId, 'year' => (string)$currentYear]);
    foreach ($usageStmt->fetchAll() as $row) {
        if ($row['leave_type'] === 'sick')   { $usedSick   = (float)$row['used']; }
        if ($row['leave_type'] === 'annual') { $usedAnnual = (float)$row['used']; }
    }

    /* History */
    $histStmt = $pdo->prepare('SELECT lr.id, lr.leave_type, lr.leave_date, lr.days, lr.note, u.full_name AS recorded_by_name, lr.created_at
        FROM leave_records lr
        JOIN users u ON u.id = lr.recorded_by
        WHERE lr.employee_id = :emp_id
        ORDER BY lr.leave_date DESC, lr.id DESC
        LIMIT 100');
    $histStmt->execute(['emp_id' => $selectedEmpId]);
    $leaveHistory = $histStmt->fetchAll();
}

/* JSON payload for JS quota hint (all employees) */
$empJsonData = [];
foreach ($empList as $e) {
    $eId = (int)$e['id'];
    /* Per-employee year usage */
    $uStmt = $pdo->prepare('SELECT leave_type, COALESCE(SUM(days),0) AS used
        FROM leave_records
        WHERE employee_id = :emp_id AND substr(leave_date, 1, 4) = :year
        GROUP BY leave_type');
    $uStmt->execute(['emp_id' => $eId, 'year' => (string)$currentYear]);
    $uSick = 0.0; $uAnnual = 0.0;
    foreach ($uStmt->fetchAll() as $ur) {
        if ($ur['leave_type'] === 'sick')   { $uSick   = (float)$ur['used']; }
        if ($ur['leave_type'] === 'annual') { $uAnnual = (float)$ur['used']; }
    }
    $empJsonData[$eId] = [
        'name'               => $e['name'],
        'emp_code'           => $e['emp_code'],
        'sick_quota'         => (int)$e['sick_leave_quota'],
        'annual_quota'       => (int)$e['annual_leave_quota'],
        'sick_used'          => $uSick,
        'annual_used'        => $uAnnual,
    ];
}

require __DIR__ . '/partials_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= e(t('leave_record_title')) ?></h1>
</div>

<?php foreach (['success', 'error', 'warning'] as $ft): ?>
    <?php if ($msg = flash($ft)): ?>
        <div class="alert alert-<?= $ft === 'error' ? 'danger' : $ft ?> alert-dismissible fade show" role="alert">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="row g-4">
    <!-- ── Left column: Entry form ── -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">📋 <?= e(t('leave_management')) ?></h2>

                <form method="post" id="leaveForm">
                    <input type="hidden" name="action" value="record_leave">

                    <!-- Employee select -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= e(t('employee')) ?></label>
                        <select class="form-select" name="employee_id" id="empSelect" required>
                            <option value=""><?= e(t('leave_select_employee')) ?></option>
                            <?php foreach ($empList as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"
                                    <?= $selectedEmpId === (int)$e['id'] ? 'selected' : '' ?>>
                                    <?= e($e['emp_code'] . ' – ' . $e['name'] . ' (' . $e['department'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Quota alert (populated by JS) -->
                    <div id="quotaAlert" class="alert alert-info py-2 mb-3 d-none" role="alert">
                        <strong id="quotaEmpName"></strong><br>
                        <span id="quotaSickLine"></span><br>
                        <span id="quotaAnnualLine"></span>
                    </div>

                    <!-- Leave type -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= e(t('leave_type')) ?></label>
                        <select class="form-select" name="leave_type" required>
                            <option value="sick"><?= e(t('leave_type_sick')) ?></option>
                            <option value="annual"><?= e(t('leave_type_annual')) ?></option>
                            <option value="other"><?= e(t('leave_type_other')) ?></option>
                        </select>
                    </div>

                    <!-- Leave date -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= e(t('leave_date')) ?></label>
                        <input class="form-control" type="date" name="leave_date" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>

                    <!-- Days -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= e(t('leave_days')) ?></label>
                        <select class="form-select" name="days" required>
                            <option value="1" selected>1 <?= e(t('leave_days_unit')) ?> (เต็มวัน)</option>
                            <option value="0.5">0.5 <?= e(t('leave_days_unit')) ?> (ครึ่งวัน)</option>
                        </select>
                    </div>

                    <!-- Note -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('leave_note')) ?></label>
                        <input class="form-control" type="text" name="note" maxlength="255" placeholder="(ไม่บังคับ)">
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><?= e(t('save')) ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right column: Leave history of selected employee ── -->
    <div class="col-lg-7">
        <?php if ($selectedEmp): ?>
        <!-- Quota summary card -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">📊 สรุปโควต้าปี <?= $currentYear ?> — <?= e($selectedEmp['emp_code'] . ' ' . $selectedEmp['name']) ?></h2>
                <div class="row g-3 text-center">
                    <!-- Sick leave -->
                    <?php
                    $sickQ    = $selectedEmp['sick_leave_quota'];
                    $sickRem  = max(0, $sickQ - $usedSick);
                    $sickPct  = $sickQ > 0 ? min(100, round($usedSick / $sickQ * 100)) : 0;
                    ?>
                    <div class="col-6">
                        <div class="card border-warning h-100">
                            <div class="card-body py-2">
                                <div class="small fw-bold text-warning mb-1">🤒 <?= e(t('leave_type_sick')) ?></div>
                                <div class="h4 mb-0"><?= number_format($usedSick, 1) ?> / <?= $sickQ ?> <small class="text-muted fs-6"><?= e(t('leave_days_unit')) ?></small></div>
                                <div class="progress mt-2" style="height:8px">
                                    <div class="progress-bar <?= $sickPct >= 90 ? 'bg-danger' : 'bg-warning' ?>" style="width:<?= $sickPct ?>%"></div>
                                </div>
                                <div class="small text-muted mt-1"><?= e(t('leave_remaining')) ?>: <strong><?= number_format($sickRem, 1) ?> <?= e(t('leave_days_unit')) ?></strong></div>
                            </div>
                        </div>
                    </div>
                    <!-- Annual leave -->
                    <?php
                    $annQ    = $selectedEmp['annual_leave_quota'];
                    $annRem  = max(0, $annQ - $usedAnnual);
                    $annPct  = $annQ > 0 ? min(100, round($usedAnnual / $annQ * 100)) : 0;
                    ?>
                    <div class="col-6">
                        <div class="card border-info h-100">
                            <div class="card-body py-2">
                                <div class="small fw-bold text-info mb-1">🏖️ <?= e(t('leave_type_annual')) ?></div>
                                <div class="h4 mb-0"><?= number_format($usedAnnual, 1) ?> / <?= $annQ ?> <small class="text-muted fs-6"><?= e(t('leave_days_unit')) ?></small></div>
                                <div class="progress mt-2" style="height:8px">
                                    <div class="progress-bar <?= $annPct >= 90 ? 'bg-danger' : 'bg-info' ?>" style="width:<?= $annPct ?>%"></div>
                                </div>
                                <div class="small text-muted mt-1"><?= e(t('leave_remaining')) ?>: <strong><?= number_format($annRem, 1) ?> <?= e(t('leave_days_unit')) ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">📄 <?= e(t('leave_history')) ?></h2>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th><?= e(t('leave_date')) ?></th>
                                <th><?= e(t('leave_type')) ?></th>
                                <th><?= e(t('leave_days')) ?></th>
                                <th><?= e(t('leave_note')) ?></th>
                                <th><?= e(t('action')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$leaveHistory): ?>
                                <tr><td colspan="5" class="text-center text-muted"><?= e(t('leave_no_records')) ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($leaveHistory as $lr): ?>
                                <tr>
                                    <td><?= e((string)$lr['leave_date']) ?></td>
                                    <td>
                                        <?php
                                        $typeLabel = match($lr['leave_type']) {
                                            'sick'   => t('leave_type_sick'),
                                            'annual' => t('leave_type_annual'),
                                            default  => t('leave_type_other'),
                                        };
                                        $badgeCls = match($lr['leave_type']) {
                                            'sick'   => 'bg-warning text-dark',
                                            'annual' => 'bg-info text-dark',
                                            default  => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeCls ?>"><?= e($typeLabel) ?></span>
                                    </td>
                                    <td><?= number_format((float)$lr['days'], 1) ?></td>
                                    <td><?= e((string)$lr['note']) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('ยืนยันลบรายการนี้?')">
                                            <input type="hidden" name="action" value="delete_leave">
                                            <input type="hidden" name="leave_id" value="<?= (int)$lr['id'] ?>">
                                            <input type="hidden" name="back_employee_id" value="<?= $selectedEmpId ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">🗑</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle me-2"></i>เลือกพนักงานจากฟอร์มซ้ายมือเพื่อดูโควต้าและประวัติการลา
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    /* JSON data: employee quota + usage */
    const empData = <?= json_encode($empJsonData, JSON_UNESCAPED_UNICODE) ?>;

    const empSelect    = document.getElementById('empSelect');
    const quotaAlert   = document.getElementById('quotaAlert');
    const quotaEmpName = document.getElementById('quotaEmpName');
    const sickLine     = document.getElementById('quotaSickLine');
    const annualLine   = document.getElementById('quotaAnnualLine');
    const leaveForm    = document.getElementById('leaveForm');

    function updateQuotaHint() {
        const empId = parseInt(empSelect.value, 10);
        if (!empId || !empData[empId]) {
            quotaAlert.classList.add('d-none');
            return;
        }
        const d = empData[empId];
        const sickRem   = Math.max(0, d.sick_quota   - d.sick_used);
        const annualRem = Math.max(0, d.annual_quota  - d.annual_used);

        quotaEmpName.textContent = d.emp_code + ' – ' + d.name;
        sickLine.textContent   = '🤒 ลาป่วย: ใช้ไปแล้ว '  + d.sick_used.toFixed(1)   + '/' + d.sick_quota   + ' วัน  (เหลืออีก ' + sickRem.toFixed(1)   + ' วัน)';
        annualLine.textContent = '🏖️ พักร้อน: ใช้ไปแล้ว ' + d.annual_used.toFixed(1) + '/' + d.annual_quota + ' วัน  (เหลืออีก ' + annualRem.toFixed(1) + ' วัน)';

        quotaAlert.classList.remove('d-none');
        quotaAlert.className = quotaAlert.className.replace(/alert-\S+/g, '');
        const warnSick   = sickRem   <= 2 && d.sick_quota   > 0;
        const warnAnnual = annualRem <= 1 && d.annual_quota > 0;
        quotaAlert.classList.add('alert', warnSick || warnAnnual ? 'alert-warning' : 'alert-info');
    }

    empSelect.addEventListener('change', function () {
        updateQuotaHint();
        /* Navigate to show history on right panel */
        const empId = parseInt(empSelect.value, 10);
        if (empId) {
            const url = new URL(window.location.href);
            url.searchParams.set('emp', empId);
            window.history.replaceState(null, '', url.toString());
        }
    });

    /* Submit form → reload with emp param */
    leaveForm.addEventListener('submit', function () {
        const empId = parseInt(empSelect.value, 10);
        if (empId) {
            const hiddenEmp = document.createElement('input');
            hiddenEmp.type  = 'hidden';
            hiddenEmp.name  = '_redirect_emp';
            hiddenEmp.value = empId;
            /* handled server-side via POST redirect already */
        }
    });

    /* Init on page load */
    updateQuotaHint();
})();
</script>

<?php require __DIR__ . '/partials_footer.php'; ?>
