<?php
$pdo = new PDO('sqlite:c:/xampp/htdocs/New3/storage/payroll.sqlite');
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $r) {
    echo $r['name'] . PHP_EOL;
}
