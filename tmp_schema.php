<?php
$db = new PDO('sqlite:' . __DIR__ . '/storage/payroll.sqlite');
$tables = ['employees','payroll_runs','payslip_files'];
foreach ($tables as $t) {
    echo "[$t]\n";
    foreach ($db->query("PRAGMA table_info($t)") as $r) {
        echo $r['name'] . "\n";
    }
}
?>
