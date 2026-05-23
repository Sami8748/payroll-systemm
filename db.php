<?php

declare(strict_types=1);

/**
 * DATABASE CONNECTION + MIGRATION (MySQL - Aiven)
 * ใช้งานได้ทันที + ไม่ลบข้อมูลเดิม
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';

    $host = $config['db_host'];
    $port = $config['db_port'];
    $db   = $config['db_name'];
    $user = $config['db_user'];
    $pass = $config['db_pass'];
    $ssl  = $config['db_ssl'] ?? true;

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    // SSL (Aiven requirement)
    if ($ssl) {
        $pdo->exec("SET SESSION sql_require_primary_key=0");
    }

    run_migrations($pdo);

    return $pdo;
}

/**
 * ตรวจสอบและเพิ่ม column ถ้ายังไม่มี (MySQL safe)
 */
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = :table 
        AND COLUMN_NAME = :column
    ");

    $stmt->execute([
        'table' => $table,
        'column' => $column
    ]);

    if ($stmt->fetch()) {
        return;
    }

    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

/**
 * MIGRATION (สร้างตาราง + อัปเดต schema แบบไม่ทำข้อมูลหาย)
 */
function run_migrations(PDO $pdo): void
{
    // USERS
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role ENUM('admin_it','hr','accounting','ceo') NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB");

    add_column_if_missing($pdo, 'users', 'password_changed_at', "DATETIME NULL");

    // EMPLOYEES
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emp_code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        line_user_id VARCHAR(255) DEFAULT '',
        is_manager TINYINT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB");

    add_column_if_missing($pdo, 'employees', 'address', "TEXT");
    add_column_if_missing($pdo, 'employees', 'bank_name', "VARCHAR(255)");
    add_column_if_missing($pdo, 'employees', 'bank_account', "VARCHAR(100)");
    add_column_if_missing($pdo, 'employees', 'position', "VARCHAR(100) DEFAULT 'Staff'");
    add_column_if_missing($pdo, 'employees', 'national_id', "VARCHAR(50)");
    add_column_if_missing($pdo, 'employees', 'start_date', "DATE");
    add_column_if_missing($pdo, 'employees', 'initial_base_salary', "DOUBLE DEFAULT 0");

    add_column_if_missing($pdo, 'employees', 'end_date', "DATE NULL");
    add_column_if_missing($pdo, 'employees', 'resignation_reason', "TEXT NULL");
    add_column_if_missing($pdo, 'employees', 'sick_leave_quota', "INT NOT NULL DEFAULT 30");
    add_column_if_missing($pdo, 'employees', 'annual_leave_quota', "INT NOT NULL DEFAULT 6");

    // PAYROLL
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        base_salary DOUBLE NOT NULL,
        overtime DOUBLE DEFAULT 0,
        bonus DOUBLE DEFAULT 0,
        deductions DOUBLE DEFAULT 0,
        net_salary DOUBLE NOT NULL,
        status ENUM('draft','paid') DEFAULT 'draft',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_emp_month_year (employee_id, month, year)
    ) ENGINE=InnoDB");

    add_column_if_missing($pdo, 'payroll_runs', 'social_security_deduction', "DOUBLE DEFAULT 0");
    add_column_if_missing($pdo, 'payroll_runs', 'withholding_tax', "DOUBLE DEFAULT 0");
    add_column_if_missing($pdo, 'payroll_runs', 'late_deduction', "DOUBLE DEFAULT 0");
    add_column_if_missing($pdo, 'payroll_runs', 'absence_deduction', "DOUBLE DEFAULT 0");
    add_column_if_missing($pdo, 'payroll_runs', 'other_deductions', "DOUBLE DEFAULT 0");

    // AUDIT LOG
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB");
}