<?php
$db = new PDO('sqlite:' . __DIR__ . '/storage/payroll.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$employees = $db->prepare("SELECT id, emp_code, name, national_id FROM employees WHERE name LIKE :name");
$employees->execute([':name' => '%Thanapoom%']);
$empRows = $employees->fetchAll(PDO::FETCH_ASSOC);

echo "[EMPLOYEES]\n";
foreach ($empRows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

$empIds = array_column($empRows, 'id');
if (!$empIds) {
    exit;
}

$inEmp = implode(',', array_fill(0, count($empIds), '?'));
$payStmt = $db->prepare("SELECT id, employee_id, month, year, status, paid_at, slip_sent_at, updated_at FROM payroll_runs WHERE employee_id IN ($inEmp) ORDER BY year, month, id");
$payStmt->execute($empIds);
$payRows = $payStmt->fetchAll(PDO::FETCH_ASSOC);

echo "[PAYROLL_ROWS]\n";
foreach ($payRows as $r) {
    echo json_encode(['id'=>$r['id'],'month'=>$r['month'],'year'=>$r['year'],'status'=>$r['status'],'paid_at'=>$r['paid_at'],'slip_sent_at'=>$r['slip_sent_at'],'updated_at'=>$r['updated_at'],'employee_id'=>$r['employee_id']], JSON_UNESCAPED_UNICODE) . "\n";
}

$payIds = array_column($payRows, 'id');
if ($payIds) {
    $inPay = implode(',', array_fill(0, count($payIds), '?'));
    $fileStmt = $db->prepare("SELECT payroll_id, file_name, generated_at, updated_at FROM payslip_files WHERE payroll_id IN ($inPay) ORDER BY payroll_id");
    $fileStmt->execute($payIds);
    $fileRows = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[PAYSLIP_FILES]\n";
    foreach ($fileRows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
?>
