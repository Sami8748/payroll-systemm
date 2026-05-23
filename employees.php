<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['hr']);

$user = current_user();
$pdo = db();
$bankOptions = [
    'BBL' => 'Bangkok Bank (BBL)',
    'KBANK' => 'Kasikornbank (KBANK)',
    'KTB' => 'Krungthai Bank (KTB)',
    'SCB' => 'Siam Commercial Bank (SCB)',
    'BAY' => 'Bank of Ayudhya (BAY)',
    'TTB' => 'TMBThanachart Bank (TTB)',
    'GSB' => 'Government Savings Bank (GSB)',
    'BAAC' => 'BAAC',
    'UOB' => 'UOB Thailand',
    'CIMB' => 'CIMB Thai',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'resign_employee') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $resignationReason = trim((string)($_POST['resignation_reason'] ?? ''));

        if ($employeeId <= 0 || $endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        $empCheck = $pdo->prepare("SELECT id FROM employees WHERE id = :id AND is_active = 1 AND position != 'Manager'");
        $empCheck->execute(['id' => $employeeId]);
        if (!$empCheck->fetch()) {
            flash('error', t('employee_not_found'));
            header('Location: employees.php');
            exit;
        }

        $upd = $pdo->prepare('UPDATE employees SET end_date = :end_date, resignation_reason = :resignation_reason, is_active = 0 WHERE id = :id');
        $upd->execute([
            'end_date' => $endDate,
            'resignation_reason' => $resignationReason,
            'id' => $employeeId,
        ]);

        audit_log($user['id'], 'resign_employee', 'Resigned employee ID ' . $employeeId . ' end_date=' . $endDate);
        flash('success', t('employee_resigned'));
        header('Location: employees.php');
        exit;
    }

    if ($action === 'upload_employee_document') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $docType = trim((string)($_POST['doc_type'] ?? ''));
        $allowedDocTypes = ['national_id', 'bank_book', 'other'];

        $empCheck = $pdo->prepare("SELECT id FROM employees WHERE id = :id AND is_active = 1 AND position != 'Manager'");
        $empCheck->execute(['id' => $employeeId]);
        if ($employeeId <= 0 || !in_array($docType, $allowedDocTypes, true) || !$empCheck->fetch()) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            flash('error', t('doc_upload_failed'));
            header('Location: employees.php');
            exit;
        }

        $file = $_FILES['document'];
        if ((int)$file['size'] > 5 * 1024 * 1024) {
            flash('error', t('doc_too_large'));
            header('Location: employees.php');
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$file['tmp_name']);
        $allowedMimes = ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png'];
        if (!array_key_exists($mime, $allowedMimes)) {
            flash('error', t('doc_invalid_type'));
            header('Location: employees.php');
            exit;
        }

        $storedName = bin2hex(random_bytes(16)) . $allowedMimes[$mime];
        $destPath = employee_docs_storage_dir() . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $destPath)) {
            flash('error', t('doc_upload_failed'));
            header('Location: employees.php');
            exit;
        }

        $ins = $pdo->prepare('INSERT INTO employee_documents (employee_id, doc_type, original_name, stored_name, uploaded_by, uploaded_at) VALUES (:employee_id, :doc_type, :original_name, :stored_name, :uploaded_by, :uploaded_at)');
        $ins->execute([
            'employee_id' => $employeeId,
            'doc_type' => $docType,
            'original_name' => basename((string)$file['name']),
            'stored_name' => $storedName,
            'uploaded_by' => $user['id'],
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        audit_log($user['id'], 'upload_employee_document', 'Uploaded doc type=' . $docType . ' emp_id=' . $employeeId);
        flash('success', t('doc_uploaded'));
        header('Location: employees.php');
        exit;
    }

    if ($action === 'delete_employee_document') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $docStmt = $pdo->prepare("SELECT d.id, d.stored_name FROM employee_documents d JOIN employees e ON e.id = d.employee_id WHERE d.id = :id AND e.position != 'Manager'");
        $docStmt->execute(['id' => $docId]);
        $doc = $docStmt->fetch();

        if (!$doc || $docId <= 0) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        $filePath = employee_docs_storage_dir() . DIRECTORY_SEPARATOR . basename((string)$doc['stored_name']);
        if (is_file($filePath)) {
            unlink($filePath);
        }

        $del = $pdo->prepare('DELETE FROM employee_documents WHERE id = :id');
        $del->execute(['id' => $docId]);

        audit_log($user['id'], 'delete_employee_document', 'Deleted doc ID=' . $docId);
        flash('success', t('doc_deleted'));
        header('Location: employees.php');
        exit;
    }

    if ($action === 'create_employee') {
        $empCode = trim((string)($_POST['emp_code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $position = 'Staff';
        $initialBaseSalary = (float)($_POST['initial_base_salary'] ?? 0);
        $sickLeaveQuota = max(0, (int)($_POST['sick_leave_quota'] ?? 30));
        $annualLeaveQuota = max(0, (int)($_POST['annual_leave_quota'] ?? 6));
        $address = trim((string)($_POST['address'] ?? ''));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $bankAccount = trim((string)($_POST['bank_account'] ?? ''));
        $nationalId = trim((string)($_POST['national_id'] ?? ''));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($empCode === '' || $name === '' || $department === '' || $position === '' || $email === '' || $address === '' || $bankName === '' || $bankAccount === '' || $nationalId === '' || $startDate === '') {
            flash('error', t('required_fields_missing'));
            header('Location: employees.php');
            exit;
        }

        if (!isset($bankOptions[$bankName])) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if ($initialBaseSalary < 0) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if (!preg_match('/^\d{13}$/', $nationalId)) {
            flash('error', t('national_id_invalid'));
            header('Location: employees.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO employees
                (emp_code, name, department, position, initial_base_salary, address, bank_name, bank_account, national_id, start_date, email, line_user_id, is_manager, is_active, sick_leave_quota, annual_leave_quota, created_at)
                VALUES
                (:emp_code, :name, :department, :position, :initial_base_salary, :address, :bank_name, :bank_account, :national_id, :start_date, :email, :line_user_id, :is_manager, 1, :sick_leave_quota, :annual_leave_quota, :created_at)');
            $createdAt = date('Y-m-d H:i:s');
            $stmt->execute([
                'emp_code' => $empCode,
                'name' => $name,
                'department' => $department,
                'position' => $position,
                'initial_base_salary' => $initialBaseSalary,
                'address' => $address,
                'bank_name' => $bankName,
                'bank_account' => $bankAccount,
                'national_id' => $nationalId,
                'start_date' => $startDate,
                'email' => $email,
                'line_user_id' => '',
                'is_manager' => $position === 'Manager' ? 1 : 0,
                'sick_leave_quota' => $sickLeaveQuota,
                'annual_leave_quota' => $annualLeaveQuota,
                'created_at' => $createdAt,
            ]);

            $employeeId = (int)$pdo->lastInsertId();
            $historyStmt = $pdo->prepare("INSERT INTO employee_salary_history (employee_id, base_salary, effective_date, changed_by, changed_at)
                VALUES (:employee_id, :base_salary, :effective_date, :changed_by, :changed_at)");
            $historyStmt->execute([
                'employee_id' => $employeeId,
                'base_salary' => $initialBaseSalary,
                'effective_date' => $startDate,
                'changed_by' => (int)$user['id'],
                'changed_at' => $createdAt,
            ]);

            $pdo->commit();

            audit_log($user['id'], 'create_employee', 'Created employee ' . $empCode);
            flash('success', t('employee_created'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', t('employee_create_failed'));
        }

        header('Location: employees.php');
        exit;
    }

    if ($action === 'update_employee_contact') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $bankAccount = trim((string)($_POST['bank_account'] ?? ''));
        $initialBaseSalary = (float)($_POST['initial_base_salary'] ?? 0);
        $sickLeaveQuota = max(0, (int)($_POST['sick_leave_quota'] ?? 30));
        $annualLeaveQuota = max(0, (int)($_POST['annual_leave_quota'] ?? 6));
        $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));

        if ($employeeId <= 0 || $name === '' || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $bankName === '' || $bankAccount === '' || $initialBaseSalary < 0) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if (!isset($bankOptions[$bankName])) {
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
            flash('error', t('effective_date_invalid'));
            header('Location: employees.php');
            exit;
        }

        $currentStmt = $pdo->prepare("SELECT id, emp_code, initial_base_salary
            FROM employees
            WHERE id = :id
              AND is_active = 1
              AND position != 'Manager'");
        $currentStmt->execute(['id' => $employeeId]);
        $currentEmployee = $currentStmt->fetch();
        if (!$currentEmployee) {
            flash('error', t('employee_not_found'));
            header('Location: employees.php');
            exit;
        }

        $oldBaseSalary = (float)($currentEmployee['initial_base_salary'] ?? 0);
        $salaryChanged = abs($oldBaseSalary - $initialBaseSalary) > 0.00001;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE employees
                SET name = :name,
                    address = :address,
                    email = :email,
                    bank_name = :bank_name,
                    bank_account = :bank_account,
                    initial_base_salary = :initial_base_salary,
                    sick_leave_quota = :sick_leave_quota,
                    annual_leave_quota = :annual_leave_quota
                WHERE id = :id
                  AND is_active = 1
                  AND position != 'Manager'");
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'email' => $email,
                'bank_name' => $bankName,
                'bank_account' => $bankAccount,
                'initial_base_salary' => $initialBaseSalary,
                'sick_leave_quota' => $sickLeaveQuota,
                'annual_leave_quota' => $annualLeaveQuota,
                'id' => $employeeId,
            ]);

            if ($salaryChanged) {
                $historyStmt = $pdo->prepare('INSERT INTO employee_salary_history (employee_id, base_salary, effective_date, changed_by, changed_at)
                    VALUES (:employee_id, :base_salary, :effective_date, :changed_by, :changed_at)');
                $historyStmt->execute([
                    'employee_id' => $employeeId,
                    'base_salary' => $initialBaseSalary,
                    'effective_date' => $effectiveDate,
                    'changed_by' => (int)$user['id'],
                    'changed_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', t('invalid_input'));
            header('Location: employees.php');
            exit;
        }

        audit_log($user['id'], 'update_employee_contact', 'Updated employee contact ID ' . $employeeId . ($salaryChanged ? ' with salary effective ' . $effectiveDate : ''));
        flash('success', t('employee_updated'));
        header('Location: employees.php');
        exit;
    }
}

$listSql = 'SELECT id, emp_code, name, department, position, initial_base_salary, address, bank_name, bank_account, national_id, start_date, email, is_manager, is_active, sick_leave_quota, annual_leave_quota FROM employees WHERE is_active = 1';
$listSql .= " AND position != 'Manager'";
$listSql .= ' ORDER BY emp_code ASC';
$employees = $pdo->query($listSql)->fetchAll();

/* Resigned employees (last 90 days) */
$resignedStmt = $pdo->query(
    "SELECT id, emp_code, name, department, end_date, resignation_reason
     FROM employees
     WHERE is_active = 0
       AND end_date IS NOT NULL AND end_date != ''
       AND position != 'Manager'
     ORDER BY end_date DESC
     LIMIT 50"
);
$resignedEmployees = $resignedStmt->fetchAll();

/* Employee documents grouped by employee_id */
$docsStmt = $pdo->query(
    "SELECT d.id, d.employee_id, d.doc_type, d.original_name, d.stored_name, d.uploaded_at
     FROM employee_documents d
     JOIN employees e ON e.id = d.employee_id
     WHERE e.is_active = 1 AND e.position != 'Manager'
     ORDER BY d.uploaded_at DESC"
);
$allDocRows = $docsStmt->fetchAll();
$docsByEmployee = [];
foreach ($allDocRows as $docRow) {
    $docsByEmployee[(int)$docRow['employee_id']][] = $docRow;
}


$salaryHistorySql = "SELECT
                h.id,
                h.employee_id,
                h.base_salary,
                h.effective_date,
                h.changed_at,
                e.emp_code,
                e.name,
                u.full_name AS changed_by_name
        FROM employee_salary_history h
        JOIN employees e ON e.id = h.employee_id
        LEFT JOIN users u ON u.id = h.changed_by
        WHERE e.is_active = 1
            AND e.position != 'Manager'
        ORDER BY h.effective_date DESC, h.changed_at DESC, h.id DESC
        LIMIT 200";
$salaryHistory = $pdo->query($salaryHistorySql)->fetchAll();

require __DIR__ . '/partials_header.php';
?>
<h1 class="h3 mb-3"><?= e(t('employees_page')) ?></h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5"><?= e(t('create_employee')) ?></h2>
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="create_employee">
            <div class="col-md-2">
                <label class="form-label"><?= e(t('emp_code')) ?></label>
                <input class="form-control" type="text" name="emp_code" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('name')) ?></label>
                <input class="form-control" type="text" name="name" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('department')) ?></label>
                <input class="form-control" type="text" name="department" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('position')) ?></label>
                <input class="form-control" type="text" value="Staff" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('address')) ?></label>
                <input class="form-control" type="text" name="address" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('base_salary')) ?></label>
                <input class="form-control" type="number" name="initial_base_salary" min="0" step="0.01" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('sick_leave_quota')) ?></label>
                <input class="form-control" type="number" name="sick_leave_quota" min="0" max="365" value="30" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('annual_leave_quota')) ?></label>
                <input class="form-control" type="number" name="annual_leave_quota" min="0" max="365" value="6" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('bank_name')) ?></label>
                <select class="form-select" name="bank_name" required>
                    <option value=""><?= e(t('select_bank')) ?></option>
                    <?php foreach ($bankOptions as $bankCode => $bankLabel): ?>
                        <option value="<?= e($bankCode) ?>"><?= e($bankLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= e(t('bank_account')) ?></label>
                <input class="form-control" type="text" name="bank_account" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('national_id')) ?></label>
                <input class="form-control" type="text" name="national_id" minlength="13" maxlength="13" pattern="\d{13}" inputmode="numeric" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('start_date')) ?></label>
                <input class="form-control" type="date" name="start_date" required>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= e(t('email')) ?></label>
                <input class="form-control" type="email" name="email" required>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><?= e(t('save_employee')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5"><?= e(t('employee_list')) ?></h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('code')) ?></th>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('department')) ?></th>
                        <th><?= e(t('position')) ?></th>
                        <th><?= e(t('base_salary')) ?></th>
                        <th><?= e(t('address')) ?></th>
                        <th><?= e(t('bank_name')) ?></th>
                        <th><?= e(t('bank_account')) ?></th>
                        <th><?= e(t('national_id')) ?></th>
                        <th><?= e(t('start_date')) ?></th>
                        <th><?= e(t('email')) ?></th>
                        <th><?= e(t('action')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$employees): ?>
                        <tr><td colspan="12" class="text-center text-muted"><?= e(t('no_employees')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($employees as $e1): ?>
                        <tr>
                            <td><?= e($e1['emp_code']) ?></td>
                            <td><?= e($e1['name']) ?></td>
                            <td><?= e($e1['department']) ?></td>
                            <td><?= e((string)$e1['position']) ?></td>
                            <td><?= number_format((float)$e1['initial_base_salary'], 2) ?></td>
                            <td><?= e((string)$e1['address']) ?></td>
                            <td><?= e((string)($e1['bank_name'] ?? '-')) ?></td>
                            <td><?= e((string)$e1['bank_account']) ?></td>
                            <td><?= e((string)$e1['national_id']) ?></td>
                            <td><?= e((string)$e1['start_date']) ?></td>
                            <td><?= e($e1['email']) ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editEmployeeModal"
                                    data-employee-id="<?= (int)$e1['id'] ?>"
                                    data-emp-code="<?= e((string)$e1['emp_code']) ?>"
                                    data-name="<?= e((string)$e1['name']) ?>"
                                    data-department="<?= e((string)$e1['department']) ?>"
                                    data-base-salary="<?= e((string)(float)$e1['initial_base_salary']) ?>"
                                    data-address="<?= e((string)$e1['address']) ?>"
                                    data-bank-name="<?= e((string)($e1['bank_name'] ?? '')) ?>"
                                    data-bank-account="<?= e((string)$e1['bank_account']) ?>"
                                    data-email="<?= e((string)$e1['email']) ?>"
                                    data-start-date="<?= e((string)$e1['start_date']) ?>"
                                    data-sick-leave-quota="<?= (int)($e1['sick_leave_quota'] ?? 30) ?>"
                                    data-annual-leave-quota="<?= (int)($e1['annual_leave_quota'] ?? 6) ?>"
                                    title="Edit"
                                >
                                    &#9998;
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-warning btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#resignModal"
                                    data-employee-id="<?= (int)$e1['id'] ?>"
                                    data-name="<?= e((string)$e1['name']) ?>"
                                    title="<?= e(t('resign_employee')) ?>"
                                >&#128683;</button>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#docModal"
                                    data-employee-id="<?= (int)$e1['id'] ?>"
                                    data-name="<?= e((string)$e1['name']) ?>"
                                    title="<?= e(t('emp_docs_title')) ?>"
                                >&#128196;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= e(t('salary_history')) ?></h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('effective_date')) ?></th>
                        <th><?= e(t('employee')) ?></th>
                        <th><?= e(t('base_salary')) ?></th>
                        <th><?= e(t('updated_by')) ?></th>
                        <th><?= e(t('updated_at')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$salaryHistory): ?>
                        <tr><td colspan="5" class="text-center text-muted"><?= e(t('salary_history_empty')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($salaryHistory as $history): ?>
                        <tr>
                            <td><?= e((string)$history['effective_date']) ?></td>
                            <td><?= e((string)$history['emp_code'] . ' - ' . (string)$history['name']) ?></td>
                            <td><?= number_format((float)$history['base_salary'], 2) ?></td>
                            <td><?= e((string)($history['changed_by_name'] ?: 'System')) ?></td>
                            <td><?= e((string)$history['changed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.employee-edit-modal {
    max-width: 720px;
}

.employee-edit-summary {
    background: var(--bs-light);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    padding: 0.75rem;
}

.employee-edit-chip {
    display: inline-block;
    margin: 0.15rem 0.5rem 0.15rem 0;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    background: #eef4ff;
    border: 1px solid #c7d8ff;
    font-size: 0.875rem;
}
</style>

<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable employee-edit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEmployeeModalTitle">Edit Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_employee_contact">
                    <input type="hidden" name="employee_id" id="edit_employee_id" value="0">

                    <div class="employee-edit-summary mb-3">
                        <span class="employee-edit-chip"><strong><?= e(t('emp_code')) ?>:</strong> <span id="edit_emp_code"></span></span>
                        <span class="employee-edit-chip"><strong><?= e(t('name')) ?>:</strong> <span id="edit_name"></span></span>
                        <span class="employee-edit-chip"><strong><?= e(t('department')) ?>:</strong> <span id="edit_department"></span></span>
                        <span class="employee-edit-chip"><strong><?= e(t('start_date')) ?>:</strong> <span id="edit_start_date"></span></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?= e(t('name')) ?></label>
                            <input class="form-control" type="text" name="name" id="edit_name_input" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('base_salary')) ?></label>
                            <input class="form-control" type="number" name="initial_base_salary" id="edit_initial_base_salary" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('sick_leave_quota')) ?></label>
                            <input class="form-control" type="number" name="sick_leave_quota" id="edit_sick_leave_quota" min="0" max="365" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('annual_leave_quota')) ?></label>
                            <input class="form-control" type="number" name="annual_leave_quota" id="edit_annual_leave_quota" min="0" max="365" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('effective_date')) ?></label>
                            <input class="form-control" type="date" name="effective_date" id="edit_effective_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('address')) ?></label>
                            <input class="form-control" type="text" name="address" id="edit_address" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('bank_name')) ?></label>
                            <select class="form-select" name="bank_name" id="edit_bank_name" required>
                                <option value=""><?= e(t('select_bank')) ?></option>
                                <?php foreach ($bankOptions as $bankCode => $bankLabel): ?>
                                    <option value="<?= e($bankCode) ?>"><?= e($bankLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('bank_account')) ?></label>
                            <input class="form-control" type="text" name="bank_account" id="edit_bank_account" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('email')) ?></label>
                            <input class="form-control" type="email" name="email" id="edit_email" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resigned Employees Section -->
<?php if ($resignedEmployees): ?>
<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h5 mb-3">🚪 <?= e(t('resigned_employees')) ?></h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th><?= e(t('code')) ?></th>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('department')) ?></th>
                        <th><?= e(t('end_date')) ?></th>
                        <th><?= e(t('resignation_reason')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resignedEmployees as $re): ?>
                        <tr>
                            <td><?= e((string)$re['emp_code']) ?></td>
                            <td><?= e((string)$re['name']) ?></td>
                            <td><?= e((string)$re['department']) ?></td>
                            <td><?= e((string)$re['end_date']) ?></td>
                            <td><?= e((string)($re['resignation_reason'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Resign Modal -->
<div class="modal fade" id="resignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">⚠️ <?= e(t('resign_employee')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="resign_employee">
                    <input type="hidden" name="employee_id" id="resign_employee_id" value="0">
                    <p class="text-muted mb-3"><?= e(t('name')) ?>: <strong id="resign_employee_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('end_date')) ?> <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" name="end_date" id="resign_end_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('resignation_reason')) ?></label>
                        <input class="form-control" type="text" name="resignation_reason" placeholder="เช่น ลาออกโดยสมัครใจ, ครบสัญญา...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button class="btn btn-warning" type="submit"><?= e(t('resign_employee')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Modal -->
<div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📄 <?= e(t('emp_docs_title')) ?>: <span id="doc_modal_emp_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="doc_modal_emp_id" value="0">

                <!-- Existing docs per employee shown via PHP -->
                <div id="doc_modal_list">
                    <?php foreach ($employees as $e1): ?>
                        <div class="doc-list-section d-none" data-emp-id="<?= (int)$e1['id'] ?>">
                            <?php $empDocs = $docsByEmployee[(int)$e1['id']] ?? []; ?>
                            <?php if ($empDocs): ?>
                                <table class="table table-sm mb-3">
                                    <thead><tr><th><?= e(t('type')) ?></th><th><?= e(t('name')) ?></th><th><?= e(t('updated_at')) ?></th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($empDocs as $doc): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $typeLabel = match((string)$doc['doc_type']) {
                                                        'national_id' => t('emp_doc_type_national_id'),
                                                        'bank_book' => t('emp_doc_type_bank_book'),
                                                        default => t('emp_doc_type_other'),
                                                    };
                                                    echo e($typeLabel);
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="download_employee_doc.php?id=<?= (int)$doc['id'] ?>" target="_blank"><?= e((string)$doc['original_name']) ?></a>
                                                </td>
                                                <td><?= e((string)$doc['uploaded_at']) ?></td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('ลบเอกสารนี้?')">
                                                        <input type="hidden" name="action" value="delete_employee_document">
                                                        <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit">✕</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted small mb-3">ยังไม่มีเอกสาร</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>
                <h6><?= e(t('upload_document')) ?></h6>
                <form method="post" enctype="multipart/form-data" class="row g-2">
                    <input type="hidden" name="action" value="upload_employee_document">
                    <input type="hidden" name="employee_id" id="doc_upload_emp_id" value="0">
                    <div class="col-md-4">
                        <select class="form-select" name="doc_type" required>
                            <option value="national_id"><?= e(t('emp_doc_type_national_id')) ?></option>
                            <option value="bank_book"><?= e(t('emp_doc_type_bank_book')) ?></option>
                            <option value="other"><?= e(t('emp_doc_type_other')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input class="form-control" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">PDF, JPG, PNG – สูงสุด 5MB</small>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><?= e(t('upload_document')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editEmployeeModal');
    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) {
            return;
        }

        const employeeId = button.getAttribute('data-employee-id') || '0';
        const empCode = button.getAttribute('data-emp-code') || '';
        const name = button.getAttribute('data-name') || '';
        const department = button.getAttribute('data-department') || '';
        const baseSalary = button.getAttribute('data-base-salary') || '0';
        const address = button.getAttribute('data-address') || '';
        const bankName = button.getAttribute('data-bank-name') || '';
        const bankAccount = button.getAttribute('data-bank-account') || '';
        const email = button.getAttribute('data-email') || '';
        const startDate = button.getAttribute('data-start-date') || '';

        const title = modal.querySelector('#editEmployeeModalTitle');
        const idInput = modal.querySelector('#edit_employee_id');
        const empCodeInput = modal.querySelector('#edit_emp_code');
        const nameInput = modal.querySelector('#edit_name');
        const nameEditInput = modal.querySelector('#edit_name_input');
        const departmentInput = modal.querySelector('#edit_department');
        const baseSalaryInput = modal.querySelector('#edit_initial_base_salary');
        const effectiveDateInput = modal.querySelector('#edit_effective_date');
        const addressInput = modal.querySelector('#edit_address');
        const bankNameInput = modal.querySelector('#edit_bank_name');
        const bankAccountInput = modal.querySelector('#edit_bank_account');
        const emailInput = modal.querySelector('#edit_email');
        const startDateInput = modal.querySelector('#edit_start_date');
        const sickLeaveQuotaInput = modal.querySelector('#edit_sick_leave_quota');
        const annualLeaveQuotaInput = modal.querySelector('#edit_annual_leave_quota');
        const today = '<?= date('Y-m-d') ?>';

        if (title) title.textContent = 'Edit Employee: ' + empCode + ' - ' + name;
        if (idInput) idInput.value = employeeId;
        if (empCodeInput) empCodeInput.textContent = empCode;
        if (nameInput) nameInput.textContent = name;
        if (nameEditInput) nameEditInput.value = name;
        if (departmentInput) departmentInput.textContent = department;
        if (baseSalaryInput) baseSalaryInput.value = Number(baseSalary).toFixed(2);
        if (effectiveDateInput) effectiveDateInput.value = today;
        if (addressInput) addressInput.value = address;
        if (bankNameInput) bankNameInput.value = bankName;
        if (bankAccountInput) bankAccountInput.value = bankAccount;
        if (emailInput) emailInput.value = email;
        if (startDateInput) startDateInput.textContent = startDate;
        const sickQuota = btn.getAttribute('data-sick-leave-quota') || '30';
        const annualQuota = btn.getAttribute('data-annual-leave-quota') || '6';
        if (sickLeaveQuotaInput) sickLeaveQuotaInput.value = sickQuota;
        if (annualLeaveQuotaInput) annualLeaveQuotaInput.value = annualQuota;
    });
});

document.addEventListener('DOMContentLoaded', function () {
    /* Resign modal JS */
    const resignModal = document.getElementById('resignModal');
    if (resignModal) {
        resignModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('resign_employee_id').value = btn.getAttribute('data-employee-id') || '0';
            const nameEl = document.getElementById('resign_employee_name');
            if (nameEl) nameEl.textContent = btn.getAttribute('data-name') || '';
            const endInput = document.getElementById('resign_end_date');
            if (endInput) endInput.value = '<?= date('Y-m-d') ?>';
        });
    }

    /* Document modal JS */
    const docModal = document.getElementById('docModal');
    if (docModal) {
        docModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const empId = btn.getAttribute('data-employee-id') || '0';
            const empName = btn.getAttribute('data-name') || '';
            const nameEl = document.getElementById('doc_modal_emp_name');
            if (nameEl) nameEl.textContent = empName;
            const uploadIdEl = document.getElementById('doc_upload_emp_id');
            if (uploadIdEl) uploadIdEl.value = empId;
            /* Show the correct doc list */
            document.querySelectorAll('.doc-list-section').forEach(function (el) {
                el.classList.add('d-none');
            });
            const target = document.querySelector('.doc-list-section[data-emp-id="' + empId + '"]');
            if (target) target.classList.remove('d-none');
        });
    }
});
</script>
<?php require __DIR__ . '/partials_footer.php';
