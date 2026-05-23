<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$pdo = db();

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('dashboard')) ?></h1>
<?php if ($user['role'] === 'admin_it'): ?>
<?php
$totals = $pdo->query('SELECT COUNT(*) AS users_count FROM users')->fetch();
$employeeCount = $pdo->query('SELECT COUNT(*) AS employee_count FROM employees WHERE is_active = 1')->fetch();
$activeHr = $pdo->query('SELECT COUNT(*) AS c FROM users WHERE role = "hr" AND is_active = 1')->fetch();
$activeAcc = $pdo->query('SELECT COUNT(*) AS c FROM users WHERE role = "accounting" AND is_active = 1')->fetch();
$itViewStmt = $pdo->query('SELECT name, department, position, is_active FROM employees ORDER BY name ASC LIMIT 20');
$itEmployees = $itViewStmt->fetchAll();
?>
<div class="row g-3 mb-4 dashboard-stagger-row">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('active_users')) ?></div>
                <div class="h4 mb-0"><?= (int)$totals['users_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('active_employees')) ?></div>
                <div class="h4 mb-0"><?= (int)$employeeCount['employee_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('hr')) ?></div>
                <div class="h4 mb-0"><?= (int)$activeHr['c'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('accounting')) ?></div>
                <div class="h4 mb-0"><?= (int)$activeAcc['c'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= e(t('it_visible_staff')) ?></h2>
            <a href="system_logs.php" class="btn btn-outline-info btn-sm"><?= e(t('system_logs')) ?></a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('department')) ?></th>
                        <th><?= e(t('position')) ?></th>
                        <th><?= e(t('status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$itEmployees): ?>
                        <tr><td colspan="4" class="text-center text-muted"><?= e(t('no_employees')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($itEmployees as $emp): ?>
                        <tr>
                            <td><?= e($emp['name']) ?></td>
                            <td><?= e($emp['department']) ?></td>
                            <td><?= e((string)$emp['position']) ?></td>
                            <td><?= (int)$emp['is_active'] === 1 ? e(t('active')) : e(t('inactive')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    <?= e(t('it_financial_blind_notice')) ?>
</div>

<?php elseif ($user['role'] === 'ceo'): ?>
    <!-- CEO Dashboard: Executive Summary with Read-Only Access -->
    <?php
    $employeeCount = $pdo->query('SELECT COUNT(*) AS employee_count FROM employees WHERE is_active = 1')->fetch();

    $payrollSummaryStmt = $pdo->query(
        'SELECT
            COUNT(*) AS payroll_count,
            SUM(CASE WHEN pr.status = "paid" THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN pr.status = "pending" THEN 1 ELSE 0 END) AS pending_count,
            COALESCE(SUM(pr.net_salary), 0) AS total_net,
            COALESCE(SUM(CASE WHEN pr.status = "paid" THEN pr.net_salary ELSE 0 END), 0) AS paid_total
        FROM payroll_runs pr'
    );
    $payrollSummary = $payrollSummaryStmt->fetch();

    $latestStmt = $pdo->query(
        'SELECT pr.id, pr.month, pr.year, pr.net_salary, pr.status, e.emp_code, e.name
        FROM payroll_runs pr
        JOIN employees e ON e.id = pr.employee_id
        ORDER BY pr.updated_at DESC
        LIMIT 15'
    );
    $latest = $latestStmt->fetchAll();

    /* Department salary breakdown */
    $deptSalaryStmt = $pdo->query(
        'SELECT e.department, COALESCE(SUM(pr.net_salary), 0) AS total
         FROM payroll_runs pr
         JOIN employees e ON e.id = pr.employee_id
         WHERE pr.status = "paid"
         GROUP BY e.department
         ORDER BY total DESC'
    );
    $deptSalary = $deptSalaryStmt->fetchAll();

    /* Monthly trend: last 6 paid months */
    $monthlyTrendStmt = $pdo->query(
        'SELECT pr.month, pr.year, COALESCE(SUM(pr.net_salary), 0) AS total
         FROM payroll_runs pr
         WHERE pr.status = "paid"
         GROUP BY pr.year, pr.month
         ORDER BY pr.year DESC, pr.month DESC
         LIMIT 6'
    );
    $monthlyTrend = array_reverse($monthlyTrendStmt->fetchAll());

    $currentMonthTotal = 0.0;
    $lastMonthTotal = 0.0;
    $changePercent = null;
    if (count($monthlyTrend) >= 2) {
        $currentMonthTotal = (float)$monthlyTrend[count($monthlyTrend) - 1]['total'];
        $lastMonthTotal = (float)$monthlyTrend[count($monthlyTrend) - 2]['total'];
        if ($lastMonthTotal > 0) {
            $changePercent = round((($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1);
        }
    } elseif (count($monthlyTrend) === 1) {
        $currentMonthTotal = (float)$monthlyTrend[0]['total'];
    }

    ?>

    <div class="row g-3 mb-4 dashboard-stagger-row">
        <div class="col-md-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <div class="text-muted small"><?= e(t('active_employees')) ?></div>
                    <div class="h4 mb-0"><?= (int)$employeeCount['employee_count'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <div class="text-muted small"><?= e(t('payroll_records')) ?></div>
                    <div class="h4 mb-0"><?= (int)$payrollSummary['payroll_count'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="text-muted small"><?= e(t('paid_records')) ?></div>
                    <div class="h4 mb-0"><?= (int)$payrollSummary['paid_count'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <div class="text-muted small"><?= e(t('pending_records')) ?></div>
                    <div class="h4 mb-0"><?= (int)$payrollSummary['pending_count'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= e(t('ceo_total_salary_summary')) ?></h2>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-muted small"><?= e(t('ceo_total_salary_paid')) ?></div>
                            <div class="h6">฿<?= number_format((float)$payrollSummary['paid_total'], 2) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small"><?= e(t('ceo_total_outstanding')) ?></div>
                            <div class="h6">฿<?= number_format((float)($payrollSummary['total_net'] - $payrollSummary['paid_total']), 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= e(t('ceo_payroll_status')) ?></h2>
                    <div class="progress mb-2" role="progressbar" aria-valuenow="<?= (int)$payrollSummary['payroll_count'] > 0 ? intval(((int)$payrollSummary['paid_count'] / (int)$payrollSummary['payroll_count']) * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: <?= (int)$payrollSummary['payroll_count'] > 0 ? intval(((int)$payrollSummary['paid_count'] / (int)$payrollSummary['payroll_count']) * 100) : 0 ?>%"></div>
                    </div>
                    <small class="text-muted"><?= e(t('ceo_processed_of_total', [
                        'paid' => (string)(int)$payrollSummary['paid_count'],
                        'total' => (string)(int)$payrollSummary['payroll_count'],
                    ])) ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">📋 <?= e(t('ceo_recent_payroll_activity')) ?></h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th><?= e(t('employee')) ?></th>
                            <th><?= e(t('month_year')) ?></th>
                            <th><?= e(t('net_salary')) ?></th>
                            <th><?= e(t('status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$latest): ?>
                            <tr><td colspan="4" class="text-center text-muted"><?= e(t('no_payroll_records')) ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($latest as $row): ?>
                            <tr>
                                <td><?= e($row['emp_code'] . ' - ' . $row['name']) ?></td>
                                <td><?= (int)$row['month'] ?>/<?= (int)$row['year'] ?></td>
                                <td>฿<?= number_format((float)$row['net_salary'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $row['status'] === 'paid' ? 'bg-success' : ($row['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                        <?= e(
                                            $row['status'] === 'paid'
                                                ? t('status_paid')
                                                : ($row['status'] === 'pending' ? t('status_pending') : t('status_draft'))
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong><?= e(t('ceo_dashboard_notice_title')) ?></strong> <?= e(t('ceo_dashboard_notice')) ?>
    </div>

    <!-- CEO Charts -->
    <?php
    $deptLabels = json_encode(array_column($deptSalary, 'department'));
    $deptData   = json_encode(array_map(static fn(array $r): float => (float)$r['total'], $deptSalary));
    $trendLabels = json_encode(array_map(static fn(array $r): string => $r['month'] . '/' . $r['year'], $monthlyTrend));
    $trendData   = json_encode(array_map(static fn(array $r): float => (float)$r['total'], $monthlyTrend));
    ?>
    <div class="row g-3 mb-4 mt-1">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">📊 <?= e(t('ceo_dept_salary_chart')) ?></h2>
                    <?php if ($deptSalary): ?>
                        <canvas id="deptSalaryChart" height="220"></canvas>
                    <?php else: ?>
                        <p class="text-muted"><?= e(t('ceo_no_data')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">📈 <?= e(t('ceo_monthly_trend')) ?></h2>
                    <?php if ($monthlyTrend): ?>
                        <canvas id="monthlyTrendChart" height="220"></canvas>
                        <div class="row g-2 mt-2 text-center">
                            <div class="col-4">
                                <div class="text-muted small"><?= e(t('ceo_current_month_total')) ?></div>
                                <div class="fw-bold">฿<?= number_format($currentMonthTotal, 0) ?></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small"><?= e(t('ceo_last_month_total')) ?></div>
                                <div class="fw-bold">฿<?= number_format($lastMonthTotal, 0) ?></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small"><?= e(t('ceo_change_pct')) ?></div>
                                <?php if ($changePercent !== null): ?>
                                    <div class="fw-bold <?= $changePercent >= 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $changePercent >= 0 ? '+' : '' ?><?= $changePercent ?>%
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">—</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?= e(t('ceo_no_data')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        var deptCtx = document.getElementById('deptSalaryChart');
        if (deptCtx) {
            new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: <?= $deptLabels ?>,
                    datasets: [{
                        label: '฿',
                        data: <?= $deptData ?>,
                        backgroundColor: [
                            'rgba(59,130,246,0.75)', 'rgba(16,185,129,0.75)',
                            'rgba(245,158,11,0.75)', 'rgba(239,68,68,0.75)',
                            'rgba(139,92,246,0.75)', 'rgba(236,72,153,0.75)',
                            'rgba(20,184,166,0.75)', 'rgba(251,146,60,0.75)'
                        ],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: function(v){ return '฿' + Number(v).toLocaleString(); } } }
                    }
                }
            });
        }

        var trendCtx = document.getElementById('monthlyTrendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?= $trendLabels ?>,
                    datasets: [{
                        label: 'Net Salary',
                        data: <?= $trendData ?>,
                        borderColor: 'rgb(59,130,246)',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: function(v){ return '฿' + Number(v).toLocaleString(); } } }
                    }
                }
            });
        }
    })();
    </script>

<?php elseif ($user['role'] === 'hr' || $user['role'] === 'accounting'): ?>
<?php
$clause = hr_filter_clause($user);

$sqlEmployees = 'SELECT COUNT(*) AS employee_count FROM employees e WHERE e.is_active = 1';
if ($user['role'] === 'hr') {
    $sqlEmployees .= "AND e.position != 'Manager'";
}
$employeeCount = $pdo->query($sqlEmployees)->fetch();

$payrollSummaryStmt = $pdo->query(
    'SELECT
        COUNT(*) AS payroll_count,
        SUM(CASE WHEN pr.status = "paid" THEN 1 ELSE 0 END) AS paid_count,
        COALESCE(SUM(pr.net_salary), 0) AS total_net
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE 1=1 ' . $clause
);
$payrollSummary = $payrollSummaryStmt->fetch();

$latestStmt = $pdo->query(
    'SELECT pr.id, pr.month, pr.year, pr.net_salary, pr.status, e.emp_code, e.name
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE 1=1 ' . $clause . '
    ORDER BY pr.updated_at DESC
    LIMIT 10'
);
$latest = $latestStmt->fetchAll();

$workAnniversaries = [];
if ($user['role'] === 'hr') {
    $anniversaryStmt = $pdo->query(
        'SELECT e.id, e.emp_code, e.name, e.department, e.start_date
        FROM employees e
        WHERE e.is_active = 1
          AND e.position != "Manager"
          AND IFNULL(e.start_date, "") != ""'
    );
    $anniversaryRows = $anniversaryStmt->fetchAll();

    $today = new DateTimeImmutable('today');
    $windowEnd = $today->modify('+30 days');

    foreach ($anniversaryRows as $emp) {
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$emp['start_date']);
        if (!$startDate) {
            continue;
        }

        $monthDay = $startDate->format('m-d');
        $currentYear = (int)$today->format('Y');
        $nextAnniversary = DateTimeImmutable::createFromFormat('!Y-m-d', $currentYear . '-' . $monthDay);
        if (!$nextAnniversary) {
            continue;
        }

        if ($nextAnniversary < $today) {
            $nextAnniversary = $nextAnniversary->modify('+1 year');
        }

        if ($nextAnniversary > $windowEnd) {
            continue;
        }

        $serviceYears = (int)$startDate->diff($nextAnniversary)->y;
        if ($serviceYears <= 0) {
            continue;
        }

        $workAnniversaries[] = [
            'emp_code' => $emp['emp_code'],
            'name' => $emp['name'],
            'department' => $emp['department'],
            'start_date' => $emp['start_date'],
            'anniversary_date' => $nextAnniversary->format('Y-m-d'),
            'years' => $serviceYears,
            'is_today' => $nextAnniversary->format('Y-m-d') === $today->format('Y-m-d'),
        ];
    }

    usort($workAnniversaries, static fn(array $a, array $b): int => strcmp($a['anniversary_date'], $b['anniversary_date']));
}
?>
<div class="row g-3 mb-4 dashboard-stagger-row">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('active_employees')) ?></div>
                <div class="h4 mb-0"><?= (int)$employeeCount['employee_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('payroll_records')) ?></div>
                <div class="h4 mb-0"><?= (int)$payrollSummary['payroll_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('paid_records')) ?></div>
                <div class="h4 mb-0"><?= (int)$payrollSummary['paid_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small"><?= e(t('net_total')) ?></div>
                <div class="h4 mb-0"><?= number_format((float)$payrollSummary['total_net'], 2) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'hr'): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= e(t('work_anniversary')) ?></h2>
            <span class="badge bg-info text-dark"><?= count($workAnniversaries) ?> <?= e(t('anniversary_upcoming')) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('employee')) ?></th>
                        <th><?= e(t('department')) ?></th>
                        <th><?= e(t('start_date')) ?></th>
                        <th><?= e(t('years_of_service')) ?></th>
                        <th><?= e(t('status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$workAnniversaries): ?>
                        <tr><td colspan="5" class="text-center text-muted"><?= e(t('no_anniversary_30_days')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($workAnniversaries as $row): ?>
                        <tr>
                            <td><?= e($row['emp_code'] . ' - ' . $row['name']) ?></td>
                            <td><?= e($row['department']) ?></td>
                            <td><?= e($row['start_date']) ?></td>
                            <td><?= (int)$row['years'] ?></td>
                            <td>
                                <span class="badge <?= $row['is_today'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= e($row['is_today'] ? t('anniversary_today') : t('anniversary_upcoming')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= e(t('latest_payroll')) ?></h2>
            <?php if ($user['role'] !== 'ceo'): ?>
                <a href="payroll.php" class="btn btn-primary btn-sm"><?= e(t('manage_payroll')) ?></a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('employee')) ?></th>
                        <th><?= e(t('month_year')) ?></th>
                        <th><?= e(t('net_salary')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <?php if ($user['role'] !== 'ceo'): ?>
                            <th><?= e(t('action')) ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$latest): ?>
                        <tr><td colspan="<?= $user['role'] === 'ceo' ? 4 : 5 ?>" class="text-center text-muted"><?= e(t('no_payroll_records')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($latest as $row): ?>
                        <tr>
                            <td><?= e($row['emp_code'] . ' - ' . $row['name']) ?></td>
                            <td><?= (int)$row['month'] ?>/<?= (int)$row['year'] ?></td>
                            <td><?= number_format((float)$row['net_salary'], 2) ?></td>
                            <td>
                                <span class="badge <?= $row['status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= e(strtoupper($row['status'])) ?>
                                </span>
                            </td>
                            <?php if ($user['role'] !== 'ceo'): ?>
                                <td><a href="payroll.php?view=<?= (int)$row['id'] ?>" class="btn btn-outline-primary btn-sm"><?= e(t('open')) ?></a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'hr'): ?>
    <div class="alert alert-warning mt-4">
        <?= e(t('msg_hr_non_manager_only')) ?>
    </div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials_footer.php';
