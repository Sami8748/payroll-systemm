<?php

declare(strict_types=1);

/*ฟังก์ชันในไฟล์นี้เกี่ยวข้องกับการจัดการฐานข้อมูลของระบบ Payroll System โดยมีฟังก์ชันหลักคือ db() ที่ใช้เชื่อมต่อและจัดการฐานข้อมูล SQLite และฟังก์ชัน run_migrations() ที่ใช้สร้างตารางและปรับโครงสร้างฐานข้อมูลให้ตรงกับความต้องการของระบบ รวมถึงฟังก์ชัน add_column_if_missing() ที่ช่วยเพิ่มคอลัมน์ในตารางหากยังไม่มี เพื่อให้ระบบสามารถทำงานได้อย่างถูกต้องและมีประสิทธิภาพ*/
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    /*ฟังก์ชันนี้ใช้ตรวจสอบว่าคอลัมน์ที่ระบุมีอยู่ในตารางหรือไม่ หากไม่มีจะทำการเพิ่มคอลัมน์นั้นเข้าไปในตาราง โดยใช้คำสั่ง ALTER TABLE ของ SQL เพื่อเพิ่มคอลัมน์ใหม่ตามที่กำหนดในพารามิเตอร์ $definition ซึ่งประกอบด้วยชื่อคอลัมน์และประเภทข้อมูลของคอลัมน์นั้นๆ*/
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    /*ตรวจสอบว่าคอลัมน์ที่ต้องการเพิ่มมีอยู่ในตารางหรือไม่ โดยการวนลูปผ่านคอลัมน์ที่ดึงมาจากฐานข้อมูลและเปรียบเทียบชื่อคอลัมน์กับชื่อคอลัมน์ที่ต้องการเพิ่ม หากพบว่ามีคอลัมน์นั้นอยู่แล้วจะทำการ return ออกจากฟังก์ชันเพื่อไม่ให้เพิ่มคอลัมน์ซ้ำอีกครั้ง*/
    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }

    /*ถ้าคอลัมน์ที่ต้องการเพิ่มไม่มีอยู่ในตาราง จะทำการเพิ่มคอลัมน์นั้นเข้าไปในตารางโดยใช้คำสั่ง ALTER TABLE ของ SQL ซึ่งจะเพิ่มคอลัมน์ใหม่ตามที่กำหนดในพารามิเตอร์ $definition เช่น ชื่อคอลัมน์และประเภทข้อมูลของคอลัมน์นั้นๆ เพื่อให้โครงสร้างของตารางตรงกับความต้องการของระบบและสามารถใช้งานได้อย่างถูกต้อง*/
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

/*ฟังก์ชันนี้ใช้สร้างตารางและปรับโครงสร้างฐานข้อมูลให้ตรงกับความต้องการของระบบ โดยจะทำการตรวจสอบและสร้างตารางต่างๆ ที่จำเป็นสำหรับระบบ Payroll System เช่น ตาราง users, employees, payroll_runs, audit_logs, payslip_files, scheduled_sends, global_constants และ employee_salary_history รวมถึงการเพิ่มคอลัมน์ใหม่ในตารางที่มีอยู่แล้วหากยังไม่มี เพื่อให้ระบบสามารถทำงานได้อย่างถูกต้องและมีประสิทธิภาพ*/
function run_migrations(PDO $pdo): void
{
    /*ตารางเก็บข้อมูลผู้ใช้ระบบ เช่น ชื่อผู้ใช้ รหัสผ่านที่ถูกแฮช บทบาท ชื่อเต็ม สถานะการทำงาน และวันที่สร้างบัญชี*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ("admin_it", "hr", "accounting", "ceo")), /*บทบาทของผู้ใช้ที่กำหนดไว้ล่วงหน้า เช่น admin_it, hr, accounting, ceo*/
        full_name TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1, /*สถานะการทำงานของผู้ใช้ (1 = ทำงานอยู่, 0 = ถูกไล่ออก)*/
        created_at TEXT NOT NULL /*วันที่สร้างบัญชีผู้ใช้*/
    )');/*ตารางเก้บประวัติส่วนตัวของผู้ใช้ เช่น รหัสผ่านหมดอายุหรือไม่ และวันที่เปลี่ยนรหัสผ่านล่าสุด เพื่อใช้ในการบังคับให้ผู้ใช้เปลี่ยนรหัสผ่านทุก 90 วัน*/

    $usersCreateSqlStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users' LIMIT 1");
    $usersCreateSql = (string)($usersCreateSqlStmt ? $usersCreateSqlStmt->fetchColumn() : '');
    if ($usersCreateSql !== '' && stripos($usersCreateSql, '"ceo"') === false) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $legacyUsersExistsStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users_old' LIMIT 1");
            $legacyUsersExists = (string)($legacyUsersExistsStmt ? $legacyUsersExistsStmt->fetchColumn() : '');
            if ($legacyUsersExists === '') {
                $pdo->exec('ALTER TABLE users RENAME TO users_old');
            }
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ("admin_it", "hr", "accounting", "ceo")),
                full_name TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                password_changed_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )');
            $pdo->exec('INSERT INTO users (id, username, password_hash, role, full_name, is_active, password_changed_at, created_at)
                SELECT id, username, password_hash, role, full_name, is_active, password_changed_at, created_at
                FROM users_old');
            $pdo->exec('PRAGMA foreign_keys = ON');
        } catch (Throwable $e) {
            $pdo->exec('PRAGMA foreign_keys = ON');
            throw $e;
        }
    }

    add_column_if_missing($pdo, 'users', 'password_changed_at', 'TEXT DEFAULT NULL');
    $pdo->exec('UPDATE users
        SET password_changed_at = created_at
        WHERE IFNULL(password_changed_at, "") = ""');

    $ensureCeoStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $ensureCeoStmt->execute(['username' => 'ceo01']);
    if (!$ensureCeoStmt->fetch(PDO::FETCH_ASSOC)) {
        $insertCeoStmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, is_active, password_changed_at, created_at)
            VALUES (:username, :password_hash, :role, :full_name, 1, :password_changed_at, :created_at)');
        $now = date('Y-m-d H:i:s');
        $insertCeoStmt->execute([
            'username' => 'ceo01',
            'password_hash' => password_hash('CEO@1234', PASSWORD_DEFAULT),
            'role' => 'ceo',
            'full_name' => 'Chief Executive Officer',
            'password_changed_at' => $now,
            'created_at' => $now,
        ]);
    }

    /*ตารางเก็บข้อมูลพนักงาน เช่น รหัสพนักงาน ชื่อ แผนก อีเมล และวันที่สร้างบัญชี*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        emp_code TEXT NOT NULL UNIQUE, /*รหัสพนักงานที่ไม่ซ้ำกัน*/
        name TEXT NOT NULL, /*ชื่อพนักงาน*/
        department TEXT NOT NULL,
        email TEXT NOT NULL,
        line_user_id TEXT DEFAULT "",
        is_manager INTEGER NOT NULL DEFAULT 0, /*สถานะการเป็นผู้จัดการ (1 = เป็นผู้จัดการ, 0 = ไม่เป็นผู้จัดการ)*/
        is_active INTEGER NOT NULL DEFAULT 1, /*สถานะการทำงานของพนักงาน (1 = ทำงานอยู่, 0 = ถูกไล่ออก)*/
        created_at TEXT NOT NULL /*วันที่สร้างบัญชีพนักงาน*/
    )');

/*เพิ่มคอลัมน์ใหม่ในตาราง employees เพื่อเก็บข้อมูลเพิ่มเติมเกี่ยวกับพนักงาน เช่น ที่อยู่ ชื่อธนาคาร หมายเลขบัญชีธนาคาร ตำแหน่งงาน หมายเลขบัตรประชาชน วันที่เริ่มงาน และเงินเดือนฐานเริ่มต้น เพื่อให้ระบบสามารถจัดการข้อมูลพนักงานได้อย่างครบถ้วนและมีประสิทธิภาพมากขึ้น*/
    add_column_if_missing($pdo, 'employees', 'address', 'TEXT NOT NULL DEFAULT ""'); /*ที่อยู่ของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'bank_name', 'TEXT NOT NULL DEFAULT ""'); /*ชื่อธนาคารของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'bank_account', 'TEXT NOT NULL DEFAULT ""'); /*หมายเลขบัญชีธนาคารของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'position', 'TEXT NOT NULL DEFAULT "Staff"'); /*ตำแหน่งงานของพนักงาน เช่น Staff หรือ Manager*/
    add_column_if_missing($pdo, 'employees', 'national_id', 'TEXT NOT NULL DEFAULT ""');/*หมายเลขบัตรประชาชนของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'start_date', 'TEXT NOT NULL DEFAULT ""'); /*วันที่เริ่มงานของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'initial_base_salary', 'REAL NOT NULL DEFAULT 0'); /*เงินเดือนฐานเริ่มต้นของพนักงาน*/
    add_column_if_missing($pdo, 'employees', 'end_date', 'TEXT DEFAULT NULL'); /*วันที่สิ้นสุดการทำงาน (ลาออก)*/
    add_column_if_missing($pdo, 'employees', 'resignation_reason', 'TEXT DEFAULT NULL'); /*เหตุผลการลาออก*/
    add_column_if_missing($pdo, 'employees', 'sick_leave_quota', 'INTEGER NOT NULL DEFAULT 30'); /*โควต้าลาป่วยต่อปี (วัน)*/
    add_column_if_missing($pdo, 'employees', 'annual_leave_quota', 'INTEGER NOT NULL DEFAULT 6'); /*โควต้าลาพักร้อนต่อปี (วัน)*/
        $pdo->exec('UPDATE employees /*ถ้าพนักงานมีวันที่เริ่มงานอยู่แล้ว แต่ยังไม่มีข้อมูลในคอลัมน์ start_date ก็จะเอาวันที่เริ่มงานมาใส่ในคอลัมน์ start_date เพื่อให้ข้อมูลครบถ้วนและถูกต้องมากขึ้น*/
                SET start_date = substr(created_at, 1, 10) /*เอาแค่ส่วนวันที่จาก created_at มาใส่ใน start_date*/
                WHERE IFNULL(start_date, "") = "" /*ตรวจสอบว่าคอลัมน์ start_date ยังไม่มีข้อมูลหรือเป็นค่าว่างอยู่เท่านั้น*/
                    AND IFNULL(created_at, "") != ""'); /*ถ้าพนักงานมีสถานะเป็นผู้จัดการ (is_manager = 1) แต่ยังไม่มีข้อมูลในคอลัมน์ position หรือมีค่าเป็นค่าว่างหรือ "Staff" ก็จะตั้งค่าตำแหน่งงานเป็น "Manager" เพื่อให้ข้อมูลตรงกับสถานะการเป็นผู้จัดการของพนักงาน และถ้าพนักงานไม่มีสถานะเป็นผู้จัดการ (is_manager = 0) แต่ยังไม่มีข้อมูลในคอลัมน์ position หรือมีค่าเป็นค่าว่าง ก็จะตั้งค่าตำแหน่งงานเป็น "Staff" เพื่อให้ข้อมูลตรงกับสถานะการไม่เป็นผู้จัดการของพนักงาน*/

/*อัปเดตตำแหน่งงานของพนักงานในตาราง employees โดยตรวจสอบสถานะการเป็นผู้จัดการ (is_manager) และค่าตำแหน่งงาน (position) ของพนักงาน หากพนักงานมีสถานะเป็นผู้จัดการแต่ยังไม่มีข้อมูลตำแหน่งงานหรือมีค่าเป็นค่าว่างหรือ "Staff" จะตั้งค่าตำแหน่งงานเป็น "Manager" และถ้าพนักงานไม่มีสถานะเป็นผู้จัดการแต่ยังไม่มีข้อมูลตำแหน่งงานหรือมีค่าเป็นค่าว่าง จะตั้งค่าตำแหน่งงานเป็น "Staff" เพื่อให้ข้อมูลตรงกับสถานะการเป็นผู้จัดการหรือไม่เป็นผู้จัดการของพนักงาน*/
    $pdo->exec('UPDATE employees SET position = "Manager" WHERE is_manager = 1 AND (position IS NULL OR position = "" OR position = "Staff")');
    $pdo->exec('UPDATE employees SET position = "Staff" WHERE is_manager = 0 AND (position IS NULL OR position = "")');

    /*ตารางเก็บข้อมูลการรันเงินเดือนของพนักงานในแต่ละเดือนในหน้าบันทึกเงินเดือน เช่น เงินเดือนฐาน ค่าล่วงเวลา โบนัส หักต่างๆ และสถานะการจ่ายเงินเดือน เพื่อให้ระบบสามารถจัดการและติดตามข้อมูลการจ่ายเงินเดือนของพนักงานได้อย่างมีประสิทธิภาพและถูกต้อง*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        month INTEGER NOT NULL,
        year INTEGER NOT NULL,
        base_salary REAL NOT NULL,
        overtime REAL NOT NULL DEFAULT 0,
        bonus REAL NOT NULL DEFAULT 0,
        deductions REAL NOT NULL DEFAULT 0, /*ยอดรวมของการหักเงินเดือนทั้งหมด*/
        net_salary REAL NOT NULL, /*ยอดเงินเดือนสุทธิหลังหักต่างๆ*/
        status TEXT NOT NULL CHECK(status IN ("draft", "paid")) DEFAULT "draft",/*สถานะของการรันเงินเดือน (draft = ร่าง, paid = จ่ายแล้ว)*/
        paid_at TEXT, /*วันที่จ่ายเงินเดือน*/
        paid_by INTEGER, /*ผู้จ่ายเงินเดือน*/
        slip_sent_at TEXT, /*วันที่ส่งสลิปเงินเดือน*/
        slip_sent_by INTEGER, /*ผู้ส่งสลิปเงินเดือน*/
        slip_channel TEXT, /*ช่องทางการส่งสลิปเงินเดือน*/
        notes TEXT DEFAULT "", /*หมายเหตุเพิ่มเติม*/
        created_by INTEGER NOT NULL, /*ผู้สร้างข้อมูล*/
        created_at TEXT NOT NULL, /*วันที่สร้างข้อมูล*/
        updated_at TEXT NOT NULL, /*วันที่อัปเดตข้อมูลล่าสุด*/
        FOREIGN KEY(employee_id) REFERENCES employees(id), /*ความสัมพันธ์ระหว่างตาราง payroll_runs กับตาราง employees โดยใช้ employee_id เป็นคีย์ต่างประเทศที่เชื่อมโยงกับ id ของพนักงานในตาราง employees เพื่อให้สามารถดึงข้อมูลพนักงานที่เกี่ยวข้องกับการรันเงินเดือนได้อย่างถูกต้อง*/
        FOREIGN KEY(paid_by) REFERENCES users(id), /*ความสัมพันธ์ระหว่างตาราง payroll_runs กับตาราง users โดยใช้ paid_by เป็นคีย์ต่างประเทศที่เชื่อมโยงกับ id ของผู้ใช้ในตาราง users เพื่อให้สามารถดึงข้อมูลผู้จ่ายเงินเดือนที่เกี่ยวข้องได้*/
        FOREIGN KEY(slip_sent_by) REFERENCES users(id), /*ความสัมพันธ์ระหว่างตาราง payroll_runs กับตาราง users โดยใช้ slip_sent_by เป็นคีย์ต่างประเทศที่เชื่อมโยงกับ id ของผู้ใช้ในตาราง users เพื่อให้สามารถดึงข้อมูลผู้ส่งสลิปเงินเดือนที่เกี่ยวข้องได้*/
        FOREIGN KEY(created_by) REFERENCES users(id), /*ความสัมพันธ์ระหว่างตาราง payroll_runs กับตาราง users โดยใช้ created_by เป็นคีย์ต่างประเทศที่เชื่อมโยงกับ id ของผู้ใช้ในตาราง users เพื่อให้สามารถดึงข้อมูลผู้สร้างข้อมูลที่เกี่ยวข้องได้*/                         
        UNIQUE(employee_id, month, year) /*ไม่ให้มีการรันเงินเดือนซ้ำกันสำหรับพนักงานคนเดียวกันในเดือนและปีเดียวกัน */
    )');

/*เพิ่มคอลัมน์ใหม่ในตาราง payroll_runs เพื่อแยกประเภทของการหักเงินเดือนออกเป็นหลายๆ ประเภท เช่น หักอื่นๆ หักประกันสังคม หักภาษี ณ ที่จ่าย หักค่ามาสาย หักขาดงาน และหักเงินกู้สวัสดิการ เพื่อให้ระบบสามารถจัดการและติดตามข้อมูลการหักเงินเดือนของพนักงานได้อย่างละเอียดและถูกต้องมากขึ้น*/
/*REAL NOT NULL DEFAULT 0 คือ ประเภทข้อมูลที่ใช้เก็บตัวเลขทศนิยมที่ไม่อนุญาตให้เป็นค่า NULL และมีค่าเริ่มต้นเป็น 0 ไว้ก่อน เพื่อให้แน่ใจว่าข้อมูลการหักเงินเดือนจะถูกเก็บอย่างถูกต้องและไม่มีค่าที่ไม่สมเหตุสมผลในฐานข้อมูล*/
        add_column_if_missing($pdo, 'payroll_runs', 'other_deductions', 'REAL NOT NULL DEFAULT 0'); /*ยอดรวมการหักอื่นๆ*/
        add_column_if_missing($pdo, 'payroll_runs', 'social_security_deduction', 'REAL NOT NULL DEFAULT 0');/*ยอดหักประกันสังคม*/
        add_column_if_missing($pdo, 'payroll_runs', 'withholding_tax', 'REAL NOT NULL DEFAULT 0'); /*ยอดหักภาษี ณ ที่จ่าย*/
        add_column_if_missing($pdo, 'payroll_runs', 'late_deduction', 'REAL NOT NULL DEFAULT 0'); /*ยอดหักค่ามาสาย*/
        add_column_if_missing($pdo, 'payroll_runs', 'absence_deduction', 'REAL NOT NULL DEFAULT 0'); /*ยอดหักขาดงาน*/
        add_column_if_missing($pdo, 'payroll_runs', 'welfare_loan_deduction', 'REAL NOT NULL DEFAULT 0'); /*ยอดหักเงินกู้สวัสดิการ*/
        add_column_if_missing($pdo, 'payroll_runs', 'severance_pay', 'REAL NOT NULL DEFAULT 0'); /*เงินชดเชยการเลิกจ้าง*/
        add_column_if_missing($pdo, 'payroll_runs', 'leave_encashment', 'REAL NOT NULL DEFAULT 0'); /*เงินคืนวันลาพักร้อนที่ไม่ได้ใช้*/

        /*ถ้าพบว่ามีการหักเงินเดือนในคอลัมน์ deductions แต่ยังไม่มีข้อมูลในคอลัมน์อื่นๆ ที่แยกประเภทการหักเงินเดือนออกมา จะทำการย้ายยอดหักเงินเดือนจากคอลัมน์ deductions ไปยังคอลัมน์ other_deductions เพื่อให้ข้อมูลการหักเงินเดือนมีความถูกต้องและสอดคล้องกับโครงสร้างฐานข้อมูลใหม่ที่แยกประเภทการหักเงินเดือนออกเป็นหลายๆ ประเภท*/
        $pdo->exec('UPDATE payroll_runs 
            SET other_deductions = deductions 
            WHERE IFNULL(other_deductions, 0) = 0 /*ตรวจสอบว่าคอลัมน์ other_deductions ยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(social_security_deduction, 0) = 0 /*ตรวจสอบว่าประกันสังคมยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(withholding_tax, 0) = 0 /*ตรวจสอบว่าภาษี ณ ที่จ่ายยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(late_deduction, 0) = 0 /*ตรวจสอบว่าค่ามาสายยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(absence_deduction, 0) = 0 /*ตรวจสอบว่าขาดงานยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(welfare_loan_deduction, 0) = 0 /*ตรวจสอบว่าเงินกู้สวัสดิการยังไม่มีข้อมูลหรือเป็นค่า 0 อยู่เท่านั้น*/
                AND IFNULL(deductions, 0) > 0'); /*ตรวจสอบว่ามีการหักเงินเดือนในคอลัมน์ deductions มากกว่า 0 อยู่เท่านั้น เพื่อให้แน่ใจว่ามีข้อมูลการหักเงินเดือนที่ต้องย้ายไปยังคอลัมน์ other_deductions เท่านั้นที่จะถูกอัปเดต*/

/*ตารางเก็บข้อมูลการกระทำต่างๆ ของผู้ใช้ในระบบ เช่น การเข้าสู่ระบบ การเปลี่ยนรหัสผ่าน และการจัดการข้อมูลพนักงาน เพื่อให้ระบบสามารถติดตามและตรวจสอบกิจกรรมของผู้ใช้ได้อย่างมีประสิทธิภาพและปลอดภัย*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, /*รหัสของบันทึกการกระทำที่ไม่ซ้ำกัน*/
        user_id INTEGER NOT NULL, /*รหัสของผู้ใช้ที่กระทำ*/
        action TEXT NOT NULL, /*ประเภทของการกระทำ เช่น login, logout, change_password, create_employee, update_employee, delete_employee เป็นต้น*/
        details TEXT NOT NULL, /*รายละเอียดเพิ่มเติมของการกระทำ เพื่อให้สามารถตรวจสอบและวิเคราะห์กิจกรรมของผู้ใช้*/
        created_at TEXT NOT NULL, /*วันที่และเวลาที่กระทำการกระทำนั้นๆ เกิดขึ้น*/
        FOREIGN KEY(user_id) REFERENCES users(id) /*ความสัมพันธ์ระหว่างตาราง audit_logs กับตาราง users โดยใช้ user_id เป็นคีย์ต่างประเทศที่เชื่อมโยงกับ id ของผู้ใช้ในตาราง users เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่เกี่ยวข้องกับการกระทำได้อย่างถูกต้อง*/
    )');

/*ตารางเก็บข้อมูลไฟล์สลิปเงินเดือนที่ถูกสร้างขึ้นสำหรับแต่ละการรันเงินเดือนของพนักงาน เช่น เส้นทางไฟล์ ชื่อไฟล์ โทเค็นสำหรับดาวน์โหลด วันที่หมดอายุ และข้อมูลผู้ที่สร้างไฟล์ เพื่อให้ระบบสามารถจัดการและติดตามข้อมูลไฟล์สลิปเงินเดือนได้อย่างมีประสิทธิภาพและปลอดภัย*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS payslip_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payroll_id INTEGER NOT NULL UNIQUE,
        file_path TEXT NOT NULL,
        file_name TEXT NOT NULL,
        download_token TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL, /*วันที่และเวลาที่ไฟล์สลีปเงินเดือนหมดอายุ*/
        generated_by INTEGER NOT NULL, /*ผู้ที่สร้างไฟล์สลิปเงินเดือนนี้ขึ้นมา*/
        generated_at TEXT NOT NULL, /*วันที่และเวลาที่สร้างไฟล์สลิปเงินเดือน*/
        updated_at TEXT NOT NULL, /*วันที่และเวลาที่อัปเดตไฟล์สลิปเงินเดือนล่าสุด*/
        FOREIGN KEY(payroll_id) REFERENCES payroll_runs(id),
        FOREIGN KEY(generated_by) REFERENCES users(id)
    )');

/*ตารางเก็บข้อมูลการส่งสลิปเงินเดือนที่ถูกกำหนดไว้ล่วงหน้าสำหรับแต่ละเดือนและปี เช่น ช่องทางการส่ง สถานะการส่ง จำนวนที่ส่งสำเร็จและล้มเหลว และหมายเหตุเพิ่มเติม เพื่อให้ระบบสามารถจัดการและติดตามข้อมูลการส่งสลิปเงินเดือนได้อย่างมีประสิทธิภาพและปลอดภัย*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS scheduled_sends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        month INTEGER NOT NULL,
        year INTEGER NOT NULL,
        channel TEXT NOT NULL CHECK(channel IN ("email", "line")),
        send_at TEXT NOT NULL, /*วันที่และเวลาที่กำหนดให้ส่งสลิปเงินเดือนนี้*/
        status TEXT NOT NULL CHECK(status IN ("pending", "completed", "failed")) DEFAULT "pending", /*สถานะของการส่งสลิปเงินเดือน (pending = รอดำเนินการ, completed = ส่งสำเร็จ, failed = ส่งล้มเหลว)*/
        success_count INTEGER NOT NULL DEFAULT 0, /*จำนวนสลิปเงินเดือนที่ส่งสำเร็จ*/
        failed_count INTEGER NOT NULL DEFAULT 0, /*จำนวนสลิปเงินเดือนที่ส่งล้มเหลว*/
        notes TEXT DEFAULT "", /*หมายเหตุเพิ่มเติมเกี่ยวกับการส่งสลิปเงินเดือน*/
        created_by INTEGER NOT NULL, /*ผู้ที่สร้างการส่งสลิปเงินเดือนนี้*/
        created_at TEXT NOT NULL, /*วันที่และเวลาที่สร้างการส่งสลิปเงินเดือนนี้*/
        processed_at TEXT, /*วันที่และเวลาที่ประมวลผลการส่งสลิปเงินเดือนนี้*/
        FOREIGN KEY(created_by) REFERENCES users(id)
    )');

/*ตารางเก็บค่าคงที่ทั่วระบบ เช่น อัตราส่วนของกองทุนสวัสดิการ หมายเลขประจำตัวผู้เสียภาษีของบริษัท หมายเลขนายจ้างสำหรับ SSO รหัสสาขาสำหรับ SSO และอายุสูงสุดของรหัสผ่าน เพื่อให้ระบบสามารถจัดการและเข้าถึงค่าคงที่เหล่านี้ได้อย่างมีประสิทธิภาพและปลอดภัย*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS global_constants (
        key TEXT PRIMARY KEY, /*ชื่อของค่าคงที่ที่ไม่ซ้ำกัน*/
        value_json TEXT NOT NULL, /*ค่าของค่าคงที่ที่เก็บในรูปแบบ JSON เพื่อให้สามารถเก็บข้อมูลประเภทต่างๆ ได้อย่างยืดหยุ่นและมีโครงสร้างที่ชัดเจน*/
        updated_by INTEGER, /*ผู้ที่อัปเดตค่าคงที่นี้ล่าสุด*/
        updated_at TEXT NOT NULL, /*วันที่และเวลาที่อัปเดตค่าคงที่นี้ล่าสุด*/
        FOREIGN KEY(updated_by) REFERENCES users(id)
    )');

/*ตารางเก็บประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานแต่ละคน เช่น เงินเดือนฐานที่เปลี่ยนแปลง วันที่มีผลบังคับใช้ ผู้ที่เปลี่ยนแปลง และวันที่เปลี่ยนแปลง เพื่อให้ระบบสามารถติดตามและตรวจสอบประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานได้อย่างมีประสิทธิภาพและถูกต้อง*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS employee_salary_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        base_salary REAL NOT NULL,
        effective_date TEXT NOT NULL, /*วันที่มีผลบังคับใช้ของเงินเดือนฐานนี้*/
        changed_by INTEGER, /*ผู้ที่เปลี่ยนแปลงเงินเดือนฐานนี้*/
        changed_at TEXT NOT NULL, /*วันที่และเวลาที่เปลี่ยนแปลงเงินเดือนฐานนี้*/
        FOREIGN KEY(employee_id) REFERENCES employees(id),
        FOREIGN KEY(changed_by) REFERENCES users(id)
    )');

    /*สร้างดัชนีสำหรับตาราง employee_salary_history ในการค้นหาข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนของพนักงาน โดยเฉพาะเมื่อมีการค้นหาข้อมูลตามรหัสพนักงานและวันที่มีผลบังคับใช้ ซึ่งจะช่วยให้การดึงข้อมูลจากฐานข้อมูล*/
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_salary_history_employee_effective ON employee_salary_history(employee_id, effective_date)');

    /*ถ้าพนักงานคนไหนยังไม่มีประวัติการเปลี่ยนแปลงเงินเดือนในตาราง employee_salary_history จะทำการเพิ่มประวัติการเปลี่ยนแปลงเงินเดือนเริ่มต้นให้กับพนักงานคนนั้น โดยใช้ข้อมูลเงินเดือนฐานเริ่มต้นจากคอลัมน์ initial_base_salary ในตาราง employees และวันที่มีผลบังคับใช้จะถูกกำหนดเป็นวันที่เริ่มงานของพนักงานหรือวันที่สร้างบัญชีพนักงาน หรือวันที่ปัจจุบันตามลำดับ เพื่อให้ข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานมีความครบถ้วนและถูกต้องตั้งแต่เริ่มต้น*/
    $pdo->exec('INSERT INTO employee_salary_history (employee_id, base_salary, effective_date, changed_by, changed_at)
        SELECT
            e.id, /*รหัสพนักงานจากตาราง employees มาใส่ในคอลัมน์ employee_id ของตาราง employee_salary_history เพื่อเชื่อมโยงข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนกับพนักงานที่เกี่ยวข้อง*/
            IFNULL(e.initial_base_salary, 0),  /*ดูว่าพนักงานมีเงินเดือนฐานเริ่มต้นไว้ไหม ถ้ามีให้เอามาใช้ ถ้าไม่มีให้ตั้งเป็น 0 เพื่อให้ข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนมีความถูกต้องและสมเหตุสมผล*/
            CASE /*กำหนดวันที่มีผลบังคับใช้ของเงินเดือนฐานเริ่มต้น โดยจะใช้วันที่เริ่มงานของพนักงานเป็นหลัก ถ้าไม่มีให้ใช้วันที่สร้างบัญชีพนักงาน และถ้ายังไม่มีอีกก็ใช้วันที่ปัจจุบัน เพื่อให้ข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนมีความครบถ้วนและถูกต้องตั้งแต่เริ่มต้น*/
                WHEN IFNULL(e.start_date, "") != "" THEN e.start_date
                WHEN IFNULL(e.created_at, "") != "" THEN substr(e.created_at, 1, 10)
                ELSE date("now")
                /*substr(e.created_at, 1, 10) คือ การตัดเอาแค่ส่วนวันที่จาก created_at มาใช้เป็นวันที่มีผลบังคับใช้ของเงินเดือนฐานเริ่มต้น เพื่อให้ข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนมีความถูกต้องและสมเหตุสมผลมากขึ้น โดยจะใช้วันที่สร้างบัญชีพนักงานเป็นวันที่มีผลบังคับใช้ของเงินเดือนฐานเริ่มต้นในกรณีที่ไม่มีข้อมูลวันที่เริ่มงานของพนักงาน*/
            END,
            NULL, 
            CASE /*กำหนดวันที่และเวลาที่เปลี่ยนแปลงเงินเดือนฐานเริ่มต้น โดยจะใช้วันที่สร้างบัญชีพนักงานเป็นหลัก ถ้าไม่มีให้ใช้วันที่ปัจจุบัน เพื่อให้ข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนมีความครบถ้วนและถูกต้องตั้งแต่เริ่มต้น*/
                WHEN IFNULL(e.created_at, "") != "" THEN e.created_at
                ELSE datetime("now")
            END
        FROM employees e  /*เลือกข้อมูลจากตาราง employees มาใช้ในการเพิ่มประวัติการเปลี่ยนแปลงเงินเดือน โดยจะเลือกเฉพาะพนักงานที่ยังไม่มีประวัติการเปลี่ยนแปลงเงินเดือนในตาราง employee_salary_history เพื่อให้แน่ใจว่าข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานมีความครบถ้วนและถูกต้องตั้งแต่เริ่มต้น*/
        WHERE NOT EXISTS (
            SELECT 1 FROM employee_salary_history h WHERE h.employee_id = e.id
        )');

    /*ตารางเก็บไฟล์เอกสารของพนักงาน เช่น สำเนาบัตรประชาชน หน้าสมุดบัญชีธนาคาร หรือเอกสารอื่นๆ*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS employee_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        doc_type TEXT NOT NULL CHECK(doc_type IN ("national_id", "bank_book", "other")),
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL UNIQUE,
        uploaded_by INTEGER NOT NULL,
        uploaded_at TEXT NOT NULL,
        FOREIGN KEY(employee_id) REFERENCES employees(id),
        FOREIGN KEY(uploaded_by) REFERENCES users(id)
    )');

    /*ตารางเก็บข้อมูลการลาของพนักงาน เช่น ประเภทการลา วันที่ลา จำนวนวัน และหมายเหตุ*/
    $pdo->exec('CREATE TABLE IF NOT EXISTS leave_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        leave_type TEXT NOT NULL CHECK(leave_type IN ("sick", "annual", "other")),
        leave_date TEXT NOT NULL,
        days REAL NOT NULL DEFAULT 1,
        note TEXT DEFAULT "",
        recorded_by INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(employee_id) REFERENCES employees(id),
        FOREIGN KEY(recorded_by) REFERENCES users(id)
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leave_records_employee_year
        ON leave_records(employee_id, leave_date)');
}

/*ฟังก์ชันนี้ใช้เชื่อมต่อและจัดการฐานข้อมูล SQLite โดยจะสร้างการเชื่อมต่อกับฐานข้อมูลที่กำหนดในไฟล์ config.php และตั้งค่าต่างๆ เช่น โหมดการจัดการข้อผิดพลาดและโหมดการดึงข้อมูล จากนั้นจะเรียกใช้ฟังก์ชัน run_migrations() เพื่อสร้างตารางและปรับโครงสร้างฐานข้อมูลให้ตรงกับความต้องการของระบบ และสุดท้ายจะคืนค่า PDO object ที่ใช้ในการทำงานกับฐานข้อมูลในส่วนอื่นๆ ของระบบ*/
function db(): PDO
{
    static $pdo = null; /*ใช้ตัวแปร static เก็บการเชื่อมต่อฐานข้อมูลเพื่อใช้ซ้ำในครั้งถัดไปโดยไม่ต้องสร้างการเชื่อมต่อใหม่ทุกครั้งที่เรียกใช้ฟังก์ชัน db() */

    /*ถ้าเชื่อมต่อฐานข้อมุลแล้ว ก็จะคืนค่า PDO object ที่เก็บในตัวแปร $pdo ออกมาใช้ได้เลย*/
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /*หยิบไฟล์ config.php เพื่อดึงค่าการตั้งค่าฐานข้อมูลว่าเก็บอยู่ที่ไหน*/
    $config = require __DIR__ . '/config.php';
    $dbPath = $config['db_path'];

    /*ตรวจสอบว่าโฟลเดอร์ที่เก็บฐานข้อมูลมีอยู่หรือไม่ ถ้าไม่มีจะทำการสร้างโฟลเดอร์นั้นขึ้นมาเพื่อให้สามารถเก็บไฟล์ฐานข้อมูลได้อย่างถูกต้องและไม่เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล*/
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true); /*สร้างโฟลเดอร์ที่เก็บฐานข้อมูลถ้ายังไม่มีอยู่*/
    }

    $pdo = new PDO('sqlite:' . $dbPath); /*สร้างการเชื่อมต่อฐานข้อมูล SQLite โดยใช้ไฟล์ฐานข้อมูลที่กำหนดใน config.php */
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); /*ถ้าเขียนคำสั่ง SQL ผิดหรือเกิดข้อผิดพลาดในการทำงานกับฐานข้อมูล จะทำการโยนข้อผิดพลาดเป็น Exception เพื่อให้สามารถจัดการและแก้ไขข้อผิดพลาดได้อย่างมีประสิทธิภาพ*/
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); /*ตั้งค่าโหมดการดึงข้อมูลเป็นแบบ associative array เพื่อให้ง่ายต่อการเข้าถึงข้อมูล*/
    $pdo->exec('PRAGMA busy_timeout = 5000'); /*ให้ SQLite รอ lock ชั่วคราวได้สูงสุด 5 วินาที แทนการ error ทันที*/
    $pdo->exec('PRAGMA journal_mode = WAL'); /*ใช้ WAL เพื่อลดการชนกันระหว่าง read/write เมื่อมีหลาย request พร้อมกัน*/
    $pdo->exec('PRAGMA foreign_keys = ON'); /*เปิดใช้งานการตรวจสอบความสัมพันธ์ระหว่างตาราง (foreign key) เพื่อให้แน่ใจว่าข้อมูลมีความถูกต้องและสอดคล้องกัน หรือห้ามลบข้อมูลที่เกี่ยวข้องกันถ้ายังมีข้อมูลที่เกี่ยวข้องอยู่*/

    $migrationLockPath = $dir . DIRECTORY_SEPARATOR . 'migration.lock';
    $migrationLockHandle = fopen($migrationLockPath, 'c');
    if ($migrationLockHandle === false) {
        throw new RuntimeException('Cannot open migration lock file.');
    }

    try {
        if (!flock($migrationLockHandle, LOCK_EX)) {
            throw new RuntimeException('Cannot acquire migration lock.');
        }
        run_migrations($pdo); /*เรียกใช้ฟังก์ชัน run_migrations() เพื่อสร้างตารางและปรับโครงสร้างฐานข้อมูลให้ตรงกับความต้องการของระบบก่อนที่จะใช้งานฐานข้อมูลในส่วนอื่นๆ ของระบบ*/
    } finally {
        flock($migrationLockHandle, LOCK_UN);
        fclose($migrationLockHandle);
    }

    return $pdo;
}
