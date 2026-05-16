<?php
$pdo = new PDO('sqlite:c:/xampp/htdocs/New3/storage/payroll.sqlite');
$stmt = $pdo->query("SELECT username, role FROM users WHERE lower(username)=lower('ceo01')");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
if (!$rows) {
    echo "NO_ROWS" . PHP_EOL;
} else {
    foreach ($rows as $row) {
        echo $row['username'] . '|' . $row['role'] . PHP_EOL;
    }
}
