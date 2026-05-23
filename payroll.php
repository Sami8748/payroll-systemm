<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['hr', 'accounting']);

$user = current_user();
$pdo = db();
$lineEnabled = is_line_delivery_enabled();

if ($user['role'] === 'accounting') {
    process_due_scheduled_sends();
}

$filterMonth = (int)($_GET['filter_month'] ?? 0);
$filterYear = (int)($_GET['filter_year'] ?? 0);

if ($filterMonth < 1 || $filterMonth > 12) {
    $filterMonth = 0;
}

if ($filterYear < 0) {
    $filterYear = 0;
}

$filterQueryParams = [];
if ($filterMonth > 0) {
    $filterQueryParams['filter_month'] = $filterMonth;
}
if ($filterYear > 0) {
    $filterQueryParams['filter_year'] = $filterYear;
}
$filterQuery = $filterQueryParams ? ('?' . http_build_query($filterQueryParams)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectQuery = (string)($_POST['redirect_query'] ?? '');
    if ($redirectQuery !== '' && !str_starts_with($redirectQuery, '?')) {
        $redirectQuery = '';
    }

    if ($action === 'create_payroll') {
        if ($user['role'] !== 'hr') {
            flash('error', t('only_hr_create_payroll'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $baseSalary = (float)($_POST['base_salary'] ?? 0);
        $overtime = (float)($_POST['overtime'] ?? 0);
        $bonus = (float)($_POST['bonus'] ?? 0);
        $lateDeduction = (float)($_POST['late_deduction'] ?? 0);
        $absenceDeduction = (float)($_POST['absence_deduction'] ?? 0);
        $welfareLoanDeduction = (float)($_POST['welfare_loan_deduction'] ?? 0);
        $manualOtherDeductions = (float)($_POST['other_deductions'] ?? 0);
        $severancePay = max(0.0, (float)($_POST['severance_pay'] ?? 0));
        $leaveEncashment = max(0.0, (float)($_POST['leave_encashment'] ?? 0));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $empStmt = $pdo->prepare('SELECT id, name, position, start_date, end_date FROM employees WHERE id = :id AND (is_active = 1 OR (is_active = 0 AND end_date IS NOT NULL AND end_date != ""))');
        $empStmt->execute(['id' => $employeeId]);
        $employee = $empStmt->fetch();

        if (!$employee) {
            flash('error', t('employee_not_found'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        if ($user['role'] === 'hr' && (string)$employee['position'] === 'Manager') {
            flash('error', t('hr_cannot_create_manager'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $serviceYears = calculate_service_years((string)($employee['start_date'] ?? ''), $month, $year);
        $progressiveRate = calculate_progressive_rate_percent($serviceYears);
        $adjustedBaseSalary = apply_progressive_rate($baseSalary, $progressiveRate);
        $proration = apply_first_month_proration($adjustedBaseSalary, (string)($employee['start_date'] ?? ''), $month, $year);
        /* Last month proration if employee resigned this month */
        $endDate = (string)($employee['end_date'] ?? '');
        if ($endDate !== '' && !$proration['applied']) {
            $lastProration = apply_last_month_proration($adjustedBaseSalary, $endDate, $month, $year);
            if ($lastProration['applied']) {
                $proration = $lastProration;
            }
        }
        $finalBaseSalary = (float)$proration['salary'];

        $otherDeductions = round($lateDeduction + $absenceDeduction + $welfareLoanDeduction + $manualOtherDeductions, 2);
        $socialSecurity = calculate_social_security_contribution($finalBaseSalary, $year);
        $withholdingTax = calculate_withholding_tax($finalBaseSalary, $overtime, $bonus, $year);
        $deductions = round($otherDeductions + $socialSecurity + $withholdingTax, 2);
        $net = round(calculate_net_salary($finalBaseSalary, $overtime, $bonus, $deductions) + $severancePay + $leaveEncashment, 2);

        try {
            $stmt = $pdo->prepare('INSERT INTO payroll_runs
                (employee_id, month, year, base_salary, overtime, bonus, late_deduction, absence_deduction, welfare_loan_deduction, other_deductions, social_security_deduction, withholding_tax, deductions, severance_pay, leave_encashment, net_salary, status, notes, created_by, created_at, updated_at)
                VALUES
                (:employee_id, :month, :year, :base_salary, :overtime, :bonus, :late_deduction, :absence_deduction, :welfare_loan_deduction, :other_deductions, :social_security_deduction, :withholding_tax, :deductions, :severance_pay, :leave_encashment, :net_salary, "draft", :notes, :created_by, :created_at, :updated_at)');

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                'employee_id' => $employeeId,
                'month' => $month,
                'year' => $year,
                'base_salary' => $finalBaseSalary,
                'overtime' => $overtime,
                'bonus' => $bonus,
                'late_deduction' => $lateDeduction,
                'absence_deduction' => $absenceDeduction,
                'welfare_loan_deduction' => $welfareLoanDeduction,
                'other_deductions' => $otherDeductions,
                'social_security_deduction' => $socialSecurity,
                'withholding_tax' => $withholdingTax,
                'deductions' => $deductions,
                'severance_pay' => $severancePay,
                'leave_encashment' => $leaveEncashment,
                'net_salary' => $net,
                'notes' => $notes,
                'created_by' => $user['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            audit_log($user['id'], 'create_payroll', 'Created payroll for employee ID ' . $employeeId . ' period ' . $month . '/' . $year);
            $socialSecurityMessage = t('social_security_included', [
                'amount' => number_format($socialSecurity, 2),
                'other' => number_format($otherDeductions, 2),
                'tax' => number_format($withholdingTax, 2),
            ]);
            $progressiveMessage = '';
            if ((bool)$proration['applied']) {
                $progressiveMessage = t('progressive_applied', [
                    'years' => $serviceYears,
                    'rate' => number_format($progressiveRate, 2),
                    'worked' => (int)$proration['days_worked'],
                    'days' => (int)$proration['days_in_month'],
                ]);
            } elseif ($progressiveRate > 0) {
                $progressiveMessage = t('progressive_applied_no_proration', [
                    'years' => $serviceYears,
                    'rate' => number_format($progressiveRate, 2),
                ]);
            }
            flash('success', t('payroll_created') . ' ' . $socialSecurityMessage . ' ' . $progressiveMessage);
        } catch (Throwable $e) {
            flash('error', t('payroll_create_failed'));
        }

        header('Location: payroll.php' . $redirectQuery);
        exit;
    }

    if ($action === 'mark_paid') {
        if ($user['role'] !== 'accounting') {
            flash('error', t('only_accounting_mark_paid'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE payroll_runs SET status = "paid", paid_at = :paid_at, paid_by = :paid_by, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'paid_at' => date('Y-m-d H:i:s'),
            'paid_by' => $user['id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $payrollId,
        ]);

        $genError = null;
        $generated = generate_encrypted_payslip_pdf($payrollId, $user['id'], $genError);

        audit_log($user['id'], 'mark_paid', 'Marked payroll ID ' . $payrollId . ' as paid');
        if ($generated !== null) {
            audit_log($user['id'], 'generate_payslip_pdf', 'Generated encrypted payslip for payroll ID ' . $payrollId);
            flash('success', t('payroll_marked_paid_pdf'));
        } else {
            flash('error', $genError ?? t('payslip_generate_failed'));
        }
        header('Location: payroll.php' . $redirectQuery);
        exit;
    }

    if ($action === 'generate_pdf') {
        if ($user['role'] !== 'accounting') {
            flash('error', t('only_accounting_generate_slip'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $row = get_payroll_detail($payrollId);

        if (!$row) {
            flash('error', t('payroll_not_found'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $genError = null;
        $generated = generate_encrypted_payslip_pdf($payrollId, $user['id'], $genError);
        if ($generated !== null) {
            audit_log($user['id'], 'generate_payslip_pdf', 'Generated encrypted payslip for payroll ID ' . $payrollId);
            flash('success', t('pdf_ready'));
        } else {
            flash('error', $genError ?? t('payslip_generate_failed'));
        }

        header('Location: payroll.php' . $redirectQuery);
        exit;
    }

    if ($action === 'send_slip') {
        if ($user['role'] !== 'accounting') {
            flash('error', t('only_accounting_send_slip'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $payrollId = (int)($_POST['payroll_id'] ?? 0);
        $channel = (string)($_POST['channel'] ?? 'email');

        if ($channel === 'line' && !$lineEnabled) {
            flash('error', 'LINE delivery is disabled in config.php (line_enabled=false).');
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $stmt = $pdo->prepare('SELECT pr.*, e.name, e.email, e.line_user_id, e.position
            FROM payroll_runs pr
            JOIN employees e ON e.id = pr.employee_id
            WHERE pr.id = :id');
        $stmt->execute(['id' => $payrollId]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('error', t('payroll_not_found'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $employee = [
            'name' => $row['name'],
            'email' => $row['email'],
            'line_user_id' => $row['line_user_id'],
        ];

        $ok = false;
        if ($channel === 'line') {
            $ok = send_payslip_line($employee, $row);
        } else {
            $ok = send_payslip_email($employee, $row);
            $channel = 'email';
        }

        if ($ok) {
            $up = $pdo->prepare('UPDATE payroll_runs SET slip_sent_at = :sent_at, slip_sent_by = :sent_by, slip_channel = :channel, updated_at = :updated_at WHERE id = :id');
            $up->execute([
                'sent_at' => date('Y-m-d H:i:s'),
                'sent_by' => $user['id'],
                'channel' => $channel,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $payrollId,
            ]);

            audit_log($user['id'], 'send_slip', 'Sent slip payroll ID ' . $payrollId . ' via ' . $channel);
            flash('success', t('payslip_sent_via', ['channel' => strtoupper($channel)]));
        } else {
            $reason = get_last_delivery_error();
            if ($reason !== '') {
                flash('error', t('payslip_send_failed', ['channel' => strtoupper($channel)]) . ' | ' . $reason);
            } else {
                flash('error', t('payslip_send_failed', ['channel' => strtoupper($channel)]));
            }
        }

        header('Location: payroll.php' . $redirectQuery);
        exit;
    }

    if ($action === 'bulk_send') {
        if ($user['role'] !== 'accounting') {
            flash('error', t('only_accounting_send_slip'));
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $channel = (string)($_POST['channel'] ?? 'email');
        $bulkMonth = (int)($_POST['bulk_filter_month'] ?? 0);
        $bulkYear = (int)($_POST['bulk_filter_year'] ?? 0);

        if ($channel === 'line' && !$lineEnabled) {
            flash('error', 'LINE delivery is disabled in config.php (line_enabled=false).');
            header('Location: payroll.php' . $redirectQuery);
            exit;
        }

        $bulkSql = 'SELECT pr.*, e.name, e.email, e.line_user_id
            FROM payroll_runs pr
            JOIN employees e ON e.id = pr.employee_id
            WHERE 1=1';
        $bulkParams = [];
        if ($bulkMonth >= 1 && $bulkMonth <= 12) {
            $bulkSql .= ' AND pr.month = :bulk_month';
            $bulkParams['bulk_month'] = $bulkMonth;
        }
        if ($bulkYear > 0) {
            $bulkSql .= ' AND pr.year = :bulk_year';
            $bulkParams['bulk_year'] = $bulkYear;
        }

        $bulkStmt = $pdo->prepare($bulkSql);
        $bulkStmt->execute($bulkParams);
        $bulkRows = $bulkStmt->fetchAll();

        $successCount = 0;
        $failedCount = 0;

        foreach ($bulkRows as $row) {
            $employee = [
                'name' => $row['name'],
                'email' => $row['email'],
                'line_user_id' => $row['line_user_id'],
            ];

            $ok = false;
            if ($channel === 'line') {
                $ok = send_payslip_line($employee, $row);
            } else {
                $ok = send_payslip_email($employee, $row);
                $channel = 'email';
            }

            if ($ok) {
                $up = $pdo->prepare('UPDATE payroll_runs SET slip_sent_at = :sent_at, slip_sent_by = :sent_by, slip_channel = :channel, updated_at = :updated_at WHERE id = :id');
                $up->execute([
                    'sent_at' => date('Y-m-d H:i:s'),
                    'sent_by' => $user['id'],
                    'channel' => $channel,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => (int)$row['id'],
                ]);
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        audit_log($user['id'], 'bulk_send_slip', 'Bulk sent slips via ' . $channel . ' success=' . $successCount . ' failed=' . $failedCount);
        flash('success', t('bulk_send_result', ['success' => $successCount, 'failed' => $failedCount, 'channel' => strtoupper($channel)]));
        header('Location: payroll.php' . $redirectQuery);
        exit;
    }
}

$employeeSql = "SELECT id, emp_code, name, department, position, initial_base_salary
FROM employees
WHERE (
    is_active = 1
    OR (
        is_active = 0
        AND end_date IS NOT NULL
        AND end_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    )
)";

if ($user['role'] === 'hr') {
    $employeeSql .= " AND position != 'Manager'";
}
$employeeSql .= ' ORDER BY name';
$employees = $pdo->query($employeeSql)->fetchAll();

$listSql = "SELECT pr.id, pr.month, pr.year, pr.base_salary, pr.overtime, pr.bonus,
    pr.late_deduction, pr.absence_deduction, pr.welfare_loan_deduction,
    pr.other_deductions, pr.social_security_deduction, pr.withholding_tax, pr.deductions, pr.net_salary,
        pr.status, pr.paid_at, pr.slip_sent_at, pr.slip_channel, pr.notes,
        pf.id AS payslip_file_id,
        e.emp_code, e.name, e.position
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    LEFT JOIN payslip_files pf ON pf.payroll_id = pr.id
    WHERE 1=1';
$listParams = [];
if ($user['role'] === 'hr') {
    $listSql .= " AND e.position != 'Manager'";
}

if ($filterMonth > 0) {
    $listSql .= ' AND pr.month = :filter_month';
    $listParams['filter_month'] = $filterMonth;
}

if ($filterYear > 0) {
    $listSql .= ' AND pr.year = :filter_year';
    $listParams['filter_year'] = $filterYear;
}

$listSql .= ' ORDER BY pr.year DESC, pr.month DESC, e.emp_code ASC';

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($listParams);
$payrolls = $listStmt->fetchAll();

$yearFilterSql = 'SELECT DISTINCT pr.year
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE 1=1';
$yearFilterParams = [];
if ($user['role'] === 'hr') {
    $yearFilterSql .= " AND e.position != 'Manager'";
}
$yearFilterSql .= " ORDER BY pr.year DESC";
$yearStmt = $pdo->prepare($yearFilterSql);
$yearStmt->execute($yearFilterParams);
$availableYears = array_map(static fn(array $r): int => (int)$r['year'], $yearStmt->fetchAll());
$availableYears = array_values(array_unique(array_merge($availableYears, [2024, 2025])));
rsort($availableYears);

$welfareYear = $filterYear > 0 ? $filterYear : (int)date('Y');
$welfareSql = "SELECT COALESCE(SUM(pr.base_salary), 0) AS annual_salary_base
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE pr.year = :year";
$welfareParams = ['year' => $welfareYear];
if ($user['role'] === 'hr') {
    $welfareSql .= " AND e.position != 'Manager'";
}
$welfareStmt = $pdo->prepare($welfareSql);
$welfareStmt->execute($welfareParams);
$annualSalaryBase = (float)($welfareStmt->fetch()['annual_salary_base'] ?? 0);
$welfareRate = welfare_fund_rate_percent();
$welfareContribution = calculate_welfare_fund_contribution($annualSalaryBase, $welfareRate);

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e($user['role'] === 'hr' ? t('salary_entry') : t('payment_approval')) ?></h1>

<?php if ($user['role'] === 'hr'): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('create_payroll_hr_only')) ?></h2>
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="create_payroll">
            <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
            <div class="col-md-4">
                <label class="form-label"><?= e(t('employee')) ?></label>
                <select class="form-select" name="employee_id" id="payroll-employee-select" required>
                    <option value=""><?= e(t('select_employee')) ?></option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>" data-base-salary="<?= e((string)(float)$emp['initial_base_salary']) ?>"><?= e($emp['emp_code'] . ' - ' . $emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('month')) ?></label>
                <input class="form-control" type="number" min="1" max="12" name="month" value="<?= (int)date('n') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('year')) ?></label>
                <input class="form-control" type="number" min="2000" max="2700" name="year" value="<?= (int)date('Y') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('base_salary')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="base_salary" id="payroll-base-salary" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('overtime')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="overtime" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('bonus')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="bonus" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('other_deductions')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="other_deductions" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('late_deduction')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="late_deduction" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('absence_deduction')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="absence_deduction" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('welfare_loan_deduction')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="welfare_loan_deduction" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('severance_pay')) ?> <span class="badge bg-warning text-dark" style="font-size:.7em">งวดสุดท้าย</span></label>
                <input class="form-control" type="number" step="0.01" min="0" name="severance_pay" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('leave_encashment')) ?></label>
                <input class="form-control" type="number" step="0.01" min="0" name="leave_encashment" value="0">
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= e(t('notes')) ?></label>
                <input class="form-control" type="text" name="notes" placeholder="<?= e(t('optional_note')) ?>">
            </div>
            <div class="col-12">
                <small class="text-muted"><?= e(t('social_security_policy_note')) ?></small>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?= e(t('save_payroll')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('welfare_fund_summary')) ?></h2>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small"><?= e(t('welfare_fund_year')) ?></div>
                <div class="fw-semibold"><?= (int)$welfareYear ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small"><?= e(t('annual_salary_base')) ?></div>
                <div class="fw-semibold"><?= number_format($annualSalaryBase, 2) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small"><?= e(t('welfare_fund_rate')) ?></div>
                <div class="fw-semibold"><?= number_format($welfareRate, 2) ?>%</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small"><?= e(t('welfare_fund_contribution_due')) ?></div>
                <div class="fw-bold fs-5"><?= number_format($welfareContribution, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('payroll_records')) ?></h2>
        <form method="get" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?= e(t('month')) ?></label>
                <select class="form-select" name="filter_month">
                    <option value="0"><?= e(t('all_months')) ?></option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('year')) ?></label>
                <select class="form-select" name="filter_year">
                    <option value="0"><?= e(t('all_years')) ?></option>
                    <?php foreach ($availableYears as $y): ?>
                        <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= e(t('filter')) ?></button>
                <a href="payroll.php" class="btn btn-outline-secondary"><?= e(t('clear_filter')) ?></a>
            </div>
        </form>
        <?php if ($user['role'] === 'accounting'): ?>
            <form method="post" class="row g-2 mb-3 align-items-end">
                <input type="hidden" name="action" value="bulk_send">
                <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
                <input type="hidden" name="bulk_filter_month" value="<?= (int)$filterMonth ?>">
                <input type="hidden" name="bulk_filter_year" value="<?= (int)$filterYear ?>">
                <div class="col-md-3">
                    <label class="form-label"><?= e(t('bulk_send_channel')) ?></label>
                    <select class="form-select" name="channel">
                        <option value="email">Email</option>
                        <?php if ($lineEnabled): ?>
                            <option value="line">LINE</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary" type="submit"><?= e(t('bulk_send')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('employee')) ?></th>
                        <th><?= e(t('period')) ?></th>
                        <th><?= e(t('base')) ?></th>
                        <th><?= e(t('ot')) ?></th>
                        <th><?= e(t('bonus')) ?></th>
                        <th><?= e(t('late_deduction')) ?></th>
                        <th><?= e(t('absence_deduction')) ?></th>
                        <th><?= e(t('welfare_loan_deduction')) ?></th>
                        <th><?= e(t('other_deductions')) ?></th>
                        <th><?= e(t('social_security')) ?></th>
                        <th><?= e(t('withholding_tax')) ?></th>
                        <th><?= e(t('deduction')) ?></th>
                        <th><?= e(t('net_salary')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th><?= e(t('payment_timestamp')) ?></th>
                        <th><?= e(t('slip')) ?></th>
                        <th><?= e(t('action')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$payrolls): ?>
                        <tr><td colspan="17" class="text-center text-muted\"><?= e(t('no_records')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($payrolls as $pr): ?>
                        <tr>
                            <td><?= e($pr['emp_code'] . ' - ' . $pr['name']) ?></td>
                            <td><?= (int)$pr['month'] ?>/<?= (int)$pr['year'] ?></td>
                            <td><?= number_format((float)$pr['base_salary'], 2) ?></td>
                            <td><?= number_format((float)$pr['overtime'], 2) ?></td>
                            <td><?= number_format((float)$pr['bonus'], 2) ?></td>
                            <td><?= number_format((float)$pr['late_deduction'], 2) ?></td>
                            <td><?= number_format((float)$pr['absence_deduction'], 2) ?></td>
                            <td><?= number_format((float)$pr['welfare_loan_deduction'], 2) ?></td>
                            <td><?= number_format((float)$pr['other_deductions'], 2) ?></td>
                            <td><?= number_format((float)$pr['social_security_deduction'], 2) ?></td>
                            <td><?= number_format((float)$pr['withholding_tax'], 2) ?></td>
                            <td><?= number_format((float)$pr['deductions'], 2) ?></td>
                            <td><strong><?= number_format((float)$pr['net_salary'], 2) ?></strong></td>
                            <td>
                                <span class="badge <?= $pr['status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= e(strtoupper($pr['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($pr['paid_at'])): ?>
                                    <?= e((string)$pr['paid_at']) ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= e(t('not_paid_yet')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pr['slip_sent_at']): ?>
                                    <span class="badge bg-info text-dark"><?= e(strtoupper((string)$pr['slip_channel'])) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?= e(t('not_sent')) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($pr['payslip_file_id'])): ?>
                                    <span class="badge bg-success"><?= e(t('pdf_ready')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'accounting' && $pr['status'] !== 'paid'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="payroll_id" value="<?= (int)$pr['id'] ?>">
                                        <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
                                        <button class="btn btn-success btn-sm" type="submit"><?= e(t('pay')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'accounting'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="generate_pdf">
                                        <input type="hidden" name="payroll_id" value="<?= (int)$pr['id'] ?>">
                                        <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
                                        <button class="btn btn-outline-dark btn-sm" type="submit"><?= e(t('generate_pdf')) ?></button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="send_slip">
                                        <input type="hidden" name="payroll_id" value="<?= (int)$pr['id'] ?>">
                                        <input type="hidden" name="channel" value="email">
                                        <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
                                        <button class="btn btn-outline-primary btn-sm" type="submit">Email</button>
                                    </form>
                                    <?php if ($lineEnabled): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="send_slip">
                                            <input type="hidden" name="payroll_id" value="<?= (int)$pr['id'] ?>">
                                            <input type="hidden" name="channel" value="line">
                                            <input type="hidden" name="redirect_query" value="<?= e($filterQuery) ?>">
                                            <button class="btn btn-outline-success btn-sm" type="submit">LINE</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-secondary mt-4 mb-0">
    <?= e(t('portal_not_available')) ?>
</div>

<?php if ($user['role'] === 'hr'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const employeeSelect = document.getElementById('payroll-employee-select');
    const baseSalaryInput = document.getElementById('payroll-base-salary');

    if (!employeeSelect || !baseSalaryInput) {
        return;
    }

    employeeSelect.addEventListener('change', function () {
        const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
        const salary = selectedOption ? selectedOption.getAttribute('data-base-salary') : '';
        if (salary !== null && salary !== '') {
            baseSalaryInput.value = Number(salary).toFixed(2);
        }
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials_footer.php';
