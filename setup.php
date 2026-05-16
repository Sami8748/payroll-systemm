<?php

declare(strict_types=1);/*เข้มงวดเรื่องชนิดข้อมูลและประเภทของตัวแปรในโค้ด PHP เพื่อช่วยป้องกันข้อผิดพลาดที่อาจเกิดขึ้นจากการใช้ชนิดข้อมูลที่ไม่ถูกต้องหรือไม่คาดคิด*/

require_once __DIR__ . '/functions.php'; /*การนำเข้าไฟล์ functions.php ซึ่งเป็นไฟล์ที่มีฟังก์ชันต่างๆ ที่ใช้ในระบบการจัดการเงินเดือน เช่น ฟังก์ชันสำหรับเชื่อมต่อฐานข้อมูล, ฟังก์ชันสำหรับตรวจสอบสิทธิ์ผู้ใช้, ฟังก์ชันสำหรับจัดการกับรายงานและใบเสร็จ เป็นต้น*/

$pdo = db(); /*การเชื่อมต่อฐานข้อมูลและการสร้างตารางที่จำเป็นสำหรับระบบการจัดการเงินเดือน รวมถึงการเพิ่มข้อมูลเริ่มต้นสำหรับผู้ใช้และพนักงาน เพื่อให้ระบบพร้อมใช้งานทันทีหลังจากการติดตั้ง*/

/*การสร้างตาราง users สำหรับเก็บข้อมูลผู้ใช้ในระบบ เช่น ชื่อผู้ใช้ รหัสผ่านที่ถูกแฮช บทบาทของผู้ใช้ ชื่อเต็ม สถานะการใช้งาน และวันที่เปลี่ยนรหัสผ่านล่าสุด*/
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, /*การกำหนดคอลัมน์ id เป็น PRIMARY KEY และ AUTOINCREMENT เพื่อให้ฐานข้อมูลสร้างค่า id อัตโนมัติและไม่ซ้ำกันสำหรับแต่ละผู้ใช้*/
    username TEXT NOT NULL UNIQUE,/*เงื่อนไขว่าต้องเป็นค่าไม่ซ้ำกัน (UNIQUE) เพื่อป้องกันการสร้างบัญชีผู้ใช้ที่มีชื่อผู้ใช้เดียวกัน*/
    password_hash TEXT NOT NULL, /*การเก็บรหัสผ่านในรูปแบบที่ถูกแฮชเพื่อเพิ่มความปลอดภัยและป้องกันการเข้าถึงรหัสผ่านโดยไม่ได้รับอนุญาต*/
    role TEXT NOT NULL CHECK(role IN ("admin_it", "hr", "accounting", "ceo")), /*การกำหนดบทบาทของผู้ใช้ในระบบ โดยมีตัวเลือกที่จำกัดเฉพาะ "admin_it", "hr", "accounting", และ "ceo" เพื่อให้สามารถจัดการสิทธิ์การเข้าถึงและการใช้งานฟังก์ชันต่างๆ ในระบบได้อย่างเหมาะสม*/
    full_name TEXT NOT NULL, /*เก็บชื่อเต็มของผู้ใช้เพื่อแสดงในส่วนต่างๆของระบบ เช่น ในรายงานหรือหน้าจัดการผู้ใช้*/
    is_active INTEGER NOT NULL DEFAULT 1, 
    password_changed_at TEXT, /*การเก็บวันที่และเวลาที่ผู้ใช้เปลี่ยนรหัสผ่านล่าสุด เพื่อใช้ในการตรวจสอบว่ารหัสผ่านของผู้ใช้หมดอายุหรือไม่*/
    created_at TEXT NOT NULL /*การเก็บวันที่และเวลาที่บัญชีผู้ใช้ถูกสร้างขึ้น เพื่อใช้ในการจัดการและตรวจสอบประวัติของผู้ใช้ในระบบ*/
)');

/*การสร้างตาราง employees สำหรับเก็บข้อมูลพนักงานในระบบ เช่น รหัสพนักงาน ชื่อ แผนก ตำแหน่ง เงินเดือนเริ่มต้น ที่อยู่ ข้อมูลบัญชีธนาคาร หมายเลขบัตรประชาชน วันที่เริ่มงาน อีเมล และสถานะการใช้งาน*/
$pdo->exec('CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT, /*การกำหนดคอลัมน์ id เป็น PRIMARY KEY และ AUTOINCREMENT เพื่อให้ฐานข้อมูลสร้างค่า id อัตโนมัติและไม่ซ้ำกันสำหรับแต่ละพนักงาน*/
    emp_code TEXT NOT NULL UNIQUE, /*การกำหนดรหัสพนักงานให้ไม่ซ้ำกัน (UNIQUE) เพื่อป้องกันการสร้างรหัสพนักงานซ้ำ*/
    name TEXT NOT NULL, /*การเก็บชื่อของพนักงาน*/
    department TEXT NOT NULL, /*การเก็บแผนกของพนักงาน*/
    position TEXT NOT NULL DEFAULT "Staff", /*การกำหนดตำแหน่งของพนักงาน โดยมีค่าเริ่มต้นเป็น "Staff" เพื่อให้สามารถจัดการสิทธิ์การเข้าถึงและการใช้งานฟังก์ชันต่างๆ ในระบบได้อย่างเหมาะสม*/
    initial_base_salary REAL NOT NULL DEFAULT 0, /*การเก็บเงินเดือนเริ่มต้นของพนักงานเพื่อใช้ในการคำนวณเงินเดือนและสวัสดิการต่างๆ*/
    address TEXT NOT NULL DEFAULT "", /*การเก็บที่อยู่ของพนักงานเพื่อใช้ในการติดต่อและจัดส่งเอกสารต่างๆ*/
    bank_name TEXT NOT NULL DEFAULT "", /*การเก็บชื่อธนาคารของพนักงาน*/
    bank_account TEXT NOT NULL DEFAULT "", /*การเก็บข้อมูลบัญชีธนาคารของพนักงานเพื่อใช้ในการโอนเงินเดือนและสวัสดิการต่างๆ*/
    national_id TEXT NOT NULL DEFAULT "", /*การเก็บหมายเลขบัตรประชาชนของพนักงานเพื่อใช้ในการตรวจสอบและยืนยันตัวตน*/
    start_date TEXT NOT NULL DEFAULT "", /*การเก็บวันที่เริ่มงานของพนักงานเพื่อใช้ในการคำนวณสิทธิ์และสวัสดิการต่างๆ*/
    email TEXT NOT NULL, /*การเก็บอีเมลของพนักงานเพื่อใช้ในการติดต่อและส่งข้อมูลต่างๆ*/
    line_user_id TEXT DEFAULT "",
    is_manager INTEGER NOT NULL DEFAULT 0, /*การระบุว่าพนักงานเป็นผู้จัดการหรือไม่*/
    is_active INTEGER NOT NULL DEFAULT 1, /*การระบุว่าสถานะการใช้งานของพนักงานเป็นปกติหรือไม่*/
    created_at TEXT NOT NULL /*การเก็บวันที่และเวลาที่บัญชีพนักงานถูกสร้างขึ้น*/
)');


$pdo->exec('UPDATE employees SET position = "Manager" WHERE is_manager = 1 AND (position IS NULL OR position = "" OR position = "Staff")'); 
$pdo->exec('UPDATE employees SET position = "Staff" WHERE is_manager = 0 AND (position IS NULL OR position = "")'); 

/*การสร้างตาราง payroll_runs สำหรับเก็บข้อมูลการรันเงินเดือนของพนักงานในแต่ละเดือน เช่น รหัสพนักงาน เดือน ปี เงินเดือนพื้นฐาน ค่าล่วงเวลา โบนัส การหักต่างๆ รวมถึงสถานะการรันเงินเดือนและข้อมูลเกี่ยวกับการจ่ายเงินเดือนและส่งสลิปเงินเดือน*/
$pdo->exec('CREATE TABLE IF NOT EXISTS payroll_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL, /*การเชื่อมโยงกับตาราง employees ผ่าน employee_id เพื่อระบุว่าการรันเงินเดือนนี้เป็นของพนักงานคนใด*/
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    base_salary REAL NOT NULL, /*การเก็บเงินเดือนพื้นฐานของพนักงานเพื่อใช้ในการคำนวณเงินเดือนและสวัสดิการต่างๆ*/
    overtime REAL NOT NULL DEFAULT 0, /*การเก็บค่าล่วงเวลาของพนักงานเพื่อใช้ในการคำนวณเงินเดือนและสวัสดิการต่างๆ*/
    bonus REAL NOT NULL DEFAULT 0, /*การเก็บโบนัส*/
    late_deduction REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักล่าช้า*/
    absence_deduction REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักขาด*/
    welfare_loan_deduction REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักเงินกู้สวัสดิการ*/
    other_deductions REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักอื่นๆ */
    social_security_deduction REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักประกันสังคม*/
    withholding_tax REAL NOT NULL DEFAULT 0, /*การเก็บภาษีหัก ณ ที่จ่าย*/
    deductions REAL NOT NULL DEFAULT 0, /*การเก็บค่าหักรวมทั้งหมดเพื่อใช้ในการคำนวณเงินเดือนสุทธิ*/
    net_salary REAL NOT NULL, /*การเก็บเงินเดือนสุทธิของพนักงานหลังจากหักค่าต่างๆ เพื่อใช้ในการโอนเงินเดือนและแสดงในสลิปเงินเดือน*/
    status TEXT NOT NULL CHECK(status IN ("draft", "paid")) DEFAULT "draft", /*การระบุสถานะของการรันเงินเดือนว่าอยู่ในสถานะร่าง (draft) หรือจ่ายแล้ว (paid) เพื่อใช้ในการจัดการและตรวจสอบประวัติการรันเงินเดือนของพนักงาน*/
    paid_at TEXT, /*การเก็บวันที่และเวลาที่จ่ายเงินเดือน*/
    paid_by INTEGER, /*การเก็บรหัสผู้ใช้ที่จ่ายเงินเดือน*/
    slip_sent_at TEXT, /*การเก็บวันที่และเวลาที่ส่งสลิปเงินเดือน*/
    slip_sent_by INTEGER, /*การเก็บรหัสผู้ใช้ที่ส่งสลิปเงินเดือน*/
    slip_channel TEXT, /*การเก็บช่องทางการส่งสลิปเงินเดือน*/
    notes TEXT DEFAULT "", /*การเก็บหมายเหตุหรือข้อมูลเพิ่มเติมเกี่ยวกับการรันเงินเดือน*/
    created_by INTEGER NOT NULL, /*การเก็บรหัสผู้ใช้ที่สร้างการรันเงินเดือน*/
    created_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่สร้างการรันเงินเดือน*/
    updated_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่อัปเดตการรันเงินเดือน*/
    FOREIGN KEY(employee_id) REFERENCES employees(id), /*การกำหนดความสัมพันธ์ระหว่างตาราง payroll_runs และ employees ผ่าน employee_id เพื่อให้สามารถดึงข้อมูลพนักงานที่เกี่ยวข้องกับการรันเงินเดือน*/
    FOREIGN KEY(paid_by) REFERENCES users(id), /*การกำหนดความสัมพันธ์ระหว่างตาราง payroll_runs และ users ผ่าน paid_by เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่จ่ายเงินเดือน*/
    FOREIGN KEY(slip_sent_by) REFERENCES users(id), /*การกำหนดความสัมพันธ์ระหว่างตาราง payroll_runs และ users ผ่าน slip_sent_by เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่ส่งสลิปเงินเดือน*/
    FOREIGN KEY(created_by) REFERENCES users(id), /*การกำหนดความสัมพันธ์ระหว่างตาราง payroll_runs และ users ผ่าน created_by เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่สร้างการรันเงินเดือน*/
    UNIQUE(employee_id, month, year) /*การกำหนดเงื่อนไขว่าแต่ละพนักงานสามารถมีการรันเงินเดือนได้เพียงหนึ่งครั้งต่อเดือนและปีเดียวกัน เพื่อป้องกันการสร้างการรันเงินเดือนซ้ำสำหรับพนักงานคนเดียวกันในเดือนและปีเดียวกัน*/
)');

/*การสร้างตาราง audit_logs สำหรับเก็บข้อมูลการกระทำต่างๆ ของผู้ใช้ในระบบ เช่น การเข้าสู่ระบบ การออกจากระบบ การสร้างผู้ใช้ การรีเซ็ตรหัสผ่าน และการจัดการกับพนักงาน เพื่อให้สามารถตรวจสอบและติดตามกิจกรรมของผู้ใช้ในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
$pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT, /*การกำหนดคอลัมน์ id เป็น PRIMARY KEY และ AUTOINCREMENT เพื่อให้ฐานข้อมูลสร้างค่า id อัตโนมัติและไม่ซ้ำกันสำหรับแต่ละบันทึกการกระทำของผู้ใช้*/
    user_id INTEGER NOT NULL, /*การเชื่อมโยงกับตาราง users ผ่าน user_id เพื่อระบุว่าการกระทำนี้เป็นของผู้ใช้คนใด*/
    action TEXT NOT NULL, /*การเก็บประเภทของการกระทำที่ผู้ใช้ทำในระบบ เช่น "login", "logout", "create_user", "reset_password", "toggle_active", "remove_user", "create_employee" เป็นต้น เพื่อใช้ในการจัดหมวดหมู่และวิเคราะห์กิจกรรมของผู้ใช้ในระบบ*/
    details TEXT NOT NULL, /*การเก็บรายละเอียดเพิ่มเติมเกี่ยวกับการกระทำของผู้ใช้ เช่น ข้อมูลที่ถูกเปลี่ยนแปลงหรือเหตุผลในการกระทำ เพื่อให้สามารถตรวจสอบและวิเคราะห์กิจกรรมของผู้ใช้ได้อย่างละเอียด*/
    created_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่เกิดการกระทำของผู้ใช้*/
    FOREIGN KEY(user_id) REFERENCES users(id) /*การกำหนดความสัมพันธ์ระหว่างตาราง audit_logs และ users ผ่าน user_id เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่เกี่ยวข้องกับการกระทำในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
)');

/*การสร้างตาราง payslip_files สำหรับเก็บข้อมูลเกี่ยวกับไฟล์สลิปเงินเดือนของพนักงาน เช่น รหัสการรันเงินเดือนที่เกี่ยวข้อง ชื่อไฟล์ เส้นทางไฟล์ โทเค็นสำหรับดาวน์โหลด วันที่หมดอายุ และข้อมูลผู้ใช้ที่สร้างไฟล์สลิปเงินเดือน เพื่อให้สามารถจัดการและตรวจสอบการสร้างและการเข้าถึงไฟล์สลิปเงินเดือนในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
$pdo->exec('CREATE TABLE IF NOT EXISTS payslip_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    payroll_id INTEGER NOT NULL UNIQUE, /*การเชื่อมโยงกับตาราง payroll_runs ผ่าน payroll_id เพื่อระบุว่าไฟล์สลิปเงินเดือนนี้เป็นของการรันเงินเดือนใด และกำหนดให้แต่ละการรันเงินเดือนสามารถมีไฟล์สลิปเงินเดือนได้เพียงหนึ่งไฟล์เท่านั้น*/
    file_path TEXT NOT NULL, /*การเก็บเส้นทางของไฟล์สลิปเงินเดือนในระบบไฟล์*/
    file_name TEXT NOT NULL, /*การเก็บชื่อไฟล์สลิปเงินเดือน*/
    download_token TEXT NOT NULL UNIQUE, /*การเก็บโทเค็นสำหรับดาวน์โหลดไฟล์สลิปเงินเดือน*/
    expires_at TEXT NOT NULL, /*การเก็บวันที่หมดอายุของโทเค็นดาวน์โหลดไฟล์สลิปเงินเดือน*/
    generated_by INTEGER NOT NULL, /*การเชื่อมโยงกับตาราง users ผ่าน generated_by เพื่อระบุว่าผู้ใช้คนใดเป็นผู้สร้างไฟล์สลิปเงินเดือน*/
    generated_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่สร้างไฟล์สลิปเงินเดือน*/
    updated_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่อัปเดตไฟล์สลิปเงินเดือนล่าสุด*/
    FOREIGN KEY(payroll_id) REFERENCES payroll_runs(id), 
    FOREIGN KEY(generated_by) REFERENCES users(id) /*การกำหนดความสัมพันธ์ระหว่างตาราง payslip_files และ users ผ่าน generated_by เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่สร้างไฟล์สลิปเงินเดือนได้อย่างมีประสิทธิภาพและปลอดภัย*/
)');

/*การสร้างตาราง scheduled_sends สำหรับเก็บข้อมูลเกี่ยวกับการส่งสลิปเงินเดือนตามกำหนด เช่น เดือน ปี ช่องทางการส่ง วันที่และเวลาที่กำหนดให้ส่ง สถานะการส่ง จำนวนที่ส่งสำเร็จและล้มเหลว รวมถึงข้อมูลผู้ใช้ที่สร้างการส่งตามกำหนด เพื่อให้สามารถจัดการและตรวจสอบการส่งสลิปเงินเดือนตามกำหนดในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
$pdo->exec('CREATE TABLE IF NOT EXISTS scheduled_sends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    month INTEGER NOT NULL, /*การเก็บเดือนที่กำหนดให้ส่งสลิปเงินเดือน*/
    year INTEGER NOT NULL, /*การเก็บปีที่กำหนดให้ส่งสลิปเงินเดือน*/
    channel TEXT NOT NULL CHECK(channel IN ("email", "line")), /*การเก็บช่องทางการส่งสลิปเงินเดือน เช่น "email" หรือ "line"*/
    send_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่กำหนดให้ส่งสลิปเงินเดือน*/
    status TEXT NOT NULL CHECK(status IN ("pending", "completed", "failed")) DEFAULT "pending", /*การเก็บสถานะการส่งสลิปเงินเดือน*/
    success_count INTEGER NOT NULL DEFAULT 0, /*การเก็บจำนวนสลิปเงินเดือนที่ส่งสำเร็จ*/
    failed_count INTEGER NOT NULL DEFAULT 0, /*การเก็บจำนวนสลิปเงินเดือนที่ส่งล้มเหลว*/
    notes TEXT DEFAULT "", 
    created_by INTEGER NOT NULL, /*การเชื่อมโยงกับตาราง users ผ่าน created_by เพื่อระบุว่าผู้ใช้คนใดเป็นผู้สร้างการส่งสลิปเงินเดือนตามกำหนด*/
    created_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่สร้างการส่งสลิปเงินเดือนตามกำหนด*/
    processed_at TEXT, /*การเก็บวันที่และเวลาที่ประมวลผลการส่งสลิปเงินเดือนตามกำหนด*/
    FOREIGN KEY(created_by) REFERENCES users(id)
)');

/*การสร้างตาราง global_constants สำหรับเก็บค่าคงที่ต่างๆ ที่ใช้ในระบบ เช่น นโยบายรหัสผ่าน ระยะเวลาหมดอายุของรหัสผ่าน และค่าคงที่อื่นๆ ที่เกี่ยวข้องกับการจัดการเงินเดือน เพื่อให้สามารถจัดการและปรับปรุงค่าคงที่เหล่านี้ได้อย่างง่ายดายและมีประสิทธิภาพในระบบ*/
$pdo->exec('CREATE TABLE IF NOT EXISTS global_constants (
    key TEXT PRIMARY KEY, /*การกำหนดคอลัมน์ key เป็น PRIMARY KEY เพื่อให้สามารถระบุค่าคงที่แต่ละค่าได้อย่างชัดเจนและไม่ซ้ำกันในตาราง*/
    value_json TEXT NOT NULL, /*การเก็บค่าคงที่ในรูปแบบ JSON เพื่อให้สามารถจัดเก็บข้อมูลที่ซับซ้อนได้อย่างยืดหยุ่น*/
    updated_by INTEGER, /*การเชื่อมโยงกับตาราง users ผ่าน updated_by เพื่อระบุว่าผู้ใช้คนใดเป็นผู้ที่อัปเดตค่าคงที่*/
    updated_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่อัปเดตค่าคงที่ล่าสุด*/
    FOREIGN KEY(updated_by) REFERENCES users(id)
)');

/*การสร้างตาราง employee_salary_history สำหรับเก็บประวัติการเปลี่ยนแปลงเงินเดือนของพนักงาน เช่น รหัสพนักงาน เงินเดือนพื้นฐาน วันที่มีผล และข้อมูลผู้ใช้ที่ทำการเปลี่ยนแปลง เพื่อให้สามารถติดตามและตรวจสอบประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานได้อย่างมีประสิทธิภาพและปลอดภัย*/
$pdo->exec('CREATE TABLE IF NOT EXISTS employee_salary_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL, /*การเชื่อมโยงกับตาราง employees ผ่าน employee_id เพื่อระบุว่าประวัติการเปลี่ยนแปลงเงินเดือนนี้เป็นของพนักงานคนใด*/
    base_salary REAL NOT NULL, /*การเก็บเงินเดือนพื้นฐานของพนักงาน*/
    effective_date TEXT NOT NULL, /*การเก็บวันที่มีผลของการเปลี่ยนแปลงเงินเดือน*/
    changed_by INTEGER, /*การเชื่อมโยงกับตาราง users ผ่าน changed_by เพื่อระบุว่าผู้ใช้คนใดเป็นผู้ที่ทำการเปลี่ยนแปลงเงินเดือน*/
    changed_at TEXT NOT NULL, /*การเก็บวันที่และเวลาที่ทำการเปลี่ยนแปลงเงินเดือน*/
    FOREIGN KEY(employee_id) REFERENCES employees(id), /*การกำหนดความสัมพันธ์ระหว่างตาราง employee_salary_history และ employees ผ่าน employee_id เพื่อให้สามารถดึงข้อมูลพนักงานที่เกี่ยวข้องกับประวัติการเปลี่ยนแปลงเงินเดือน*/
    FOREIGN KEY(changed_by) REFERENCES users(id) /*การกำหนดความสัมพันธ์ระหว่างตาราง employee_salary_history และ users ผ่าน changed_by เพื่อให้สามารถดึงข้อมูลผู้ใช้ที่ทำการเปลี่ยนแปลงเงินเดือน*/
)');

/*การสร้างดัชนี (index) บนตาราง employee_salary_history เพื่อเพิ่มประสิทธิภาพในการค้นหาข้อมูลประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานตามรหัสพนักงานและวันที่มีผล*/
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_salary_history_employee_effective ON employee_salary_history(employee_id, effective_date)');

/*การตรวจสอบจำนวนผู้ใช้ในตาราง users และเพิ่มข้อมูลเริ่มต้นสำหรับผู้ใช้หากยังไม่มีผู้ใช้ใดๆ ในระบบ เพื่อให้ระบบพร้อมใช้งานทันทีหลังจากการติดตั้ง*/
$countUsers = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];

/*การตรวจสอบจำนวนพนักงานในตาราง employees และเพิ่มข้อมูลเริ่มต้นสำหรับพนักงานหากยังไม่มีพนักงานใดๆ ในระบบ เพื่อให้ระบบพร้อมใช้งานทันทีหลังจากการติดตั้ง*/
if ($countUsers === 0) {
    /*การเตรียมคำสั่ง SQL สำหรับเพิ่มข้อมูลผู้ใช้เริ่มต้นในระบบ เช่น ผู้ดูแลระบบไอที เจ้าหน้าที่ฝ่ายบุคคล และเจ้าหน้าที่บัญชี เพื่อให้สามารถเข้าสู่ระบบและใช้งานฟังก์ชันต่างๆ ในระบบได้ทันทีหลังจากการติดตั้ง*/
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, is_active, password_changed_at, created_at) VALUES (:username, :password_hash, :role, :full_name, 1, :password_changed_at, :created_at)');

    /*การกำหนดข้อมูลเริ่มต้นสำหรับผู้ใช้ในระบบ เช่น ชื่อผู้ใช้ รหัสผ่านที่ถูกแฮช บทบาทของผู้ใช้ ชื่อเต็ม และวันที่เปลี่ยนรหัสผ่านล่าสุด เพื่อให้สามารถเข้าสู่ระบบและใช้งานฟังก์ชันต่างๆ ในระบบได้ทันทีหลังจากการติดตั้ง*/
    $seedUsers = [
        ['username' => 'itadmin', 'password' => 'IT@1234', 'role' => 'admin_it', 'full_name' => 'IT Administrator'],
        ['username' => 'hr01', 'password' => 'HR@1234', 'role' => 'hr', 'full_name' => 'HR Officer'],
        ['username' => 'acc01', 'password' => 'ACC@1234', 'role' => 'accounting', 'full_name' => 'Accounting Officer'],
        ['username' => 'ceo01', 'password' => 'CEO@1234', 'role' => 'ceo', 'full_name' => 'Chief Executive Officer'],
    ];

    /*การวนลูปผ่านข้อมูลผู้ใช้เริ่มต้นและเพิ่มข้อมูลเหล่านี้ลงในตาราง users โดยใช้คำสั่ง SQL ที่เตรียมไว้ และแฮชรหัสผ่านเพื่อเพิ่มความปลอดภัยในการจัดเก็บข้อมูลผู้ใช้ในระบบ*/
    foreach ($seedUsers as $u) {
        $stmt->execute([
            'username' => $u['username'],
            'password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
            'role' => $u['role'],
            'full_name' => $u['full_name'],
            'password_changed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

/*การตรวจสอบจำนวนพนักงานในตาราง employees และเพิ่มข้อมูลเริ่มต้นสำหรับพนักงานหากยังไม่มีพนักงานใดๆ ในระบบ เพื่อให้ระบบพร้อมใช้งานทันทีหลังจากการติดตั้ง*/
$countEmp = (int)$pdo->query('SELECT COUNT(*) AS c FROM employees')->fetch()['c'];
if ($countEmp === 0) {
    $stmt = $pdo->prepare('INSERT INTO employees (emp_code, name, department, position, initial_base_salary, address, bank_name, bank_account, national_id, start_date, email, line_user_id, is_manager, is_active, created_at)
        VALUES (:emp_code, :name, :department, :position, :initial_base_salary, :address, :bank_name, :bank_account, :national_id, :start_date, :email, :line_user_id, :is_manager, 1, :created_at)');

    $seedEmployees = [
        ['emp_code' => 'EMP001', 'name' => 'Somchai N.', 'department' => 'Production', 'position' => 'Staff', 'initial_base_salary' => 18000, 'address' => 'Bangkok', 'bank_name' => 'BBL', 'bank_account' => '111-2-00001-1', 'national_id' => '1101700000001', 'start_date' => '2024-01-10', 'email' => 'somchai@example.com', 'line_user_id' => '', 'is_manager' => 0],
        ['emp_code' => 'EMP002', 'name' => 'Suda K.', 'department' => 'Sales', 'position' => 'Staff', 'initial_base_salary' => 20000, 'address' => 'Nonthaburi', 'bank_name' => 'KTB', 'bank_account' => '111-2-00002-2', 'national_id' => '1101700000002', 'start_date' => '2024-03-01', 'email' => 'suda@example.com', 'line_user_id' => '', 'is_manager' => 0],
        ['emp_code' => 'MGR001', 'name' => 'Manager A.', 'department' => 'Management', 'position' => 'Manager', 'initial_base_salary' => 45000, 'address' => 'Pathum Thani', 'bank_name' => 'SCB', 'bank_account' => '111-2-00003-3', 'national_id' => '1101700000003', 'start_date' => '2023-05-15', 'email' => 'manager.a@example.com', 'line_user_id' => '', 'is_manager' => 1],
    ];

    /*การวนลูปผ่านข้อมูลพนักงานเริ่มต้นและเพิ่มข้อมูลเหล่านี้ลงในตาราง employees โดยใช้คำสั่ง SQL ที่เตรียมไว้ เพื่อให้สามารถจัดการและตรวจสอบข้อมูลพนักงานในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
    foreach ($seedEmployees as $e) {
        $stmt->execute([
            'emp_code' => $e['emp_code'],
            'name' => $e['name'],
            'department' => $e['department'],
            'position' => $e['position'],
            'initial_base_salary' => $e['initial_base_salary'],
            'address' => $e['address'],
            'bank_name' => $e['bank_name'],
            'bank_account' => $e['bank_account'],
            'national_id' => $e['national_id'],
            'start_date' => $e['start_date'],
            'email' => $e['email'],
            'line_user_id' => $e['line_user_id'],
            'is_manager' => $e['is_manager'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        /*โค้ดก้อนนี้ทำหน้าที่เหมือนฝ่ายบุคคลที่กำลังหอบ 'แฟ้มประวัติพนักงานชุดแรก' มาพิมพ์กรอกเข้าสู่ระบบอัตโนมัติรวดเดียว เพื่อให้พอเปิดเว็บโปรเจกต์ขึ้นมาปุ๊บ HR ก็มีรายชื่อพนักงานให้ทดลองกดจ่ายเงินเดือนได้เลย โดยไม่ต้องมานั่งคลิกเพิ่มเองทีละคน*/
    }
}

/*การเพิ่มข้อมูลเริ่มต้นสำหรับประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานในตาราง employee_salary_history โดยการดึงข้อมูลจากตาราง employees และเพิ่มข้อมูลเหล่านี้ลงในตาราง employee_salary_history เพื่อให้สามารถติดตามและตรวจสอบประวัติการเปลี่ยนแปลงเงินเดือนของพนักงานได้อย่างมีประสิทธิภาพและปลอดภัย*/
$pdo->exec('INSERT INTO employee_salary_history (employee_id, base_salary, effective_date, changed_by, changed_at)
    SELECT
        e.id,
        IFNULL(e.initial_base_salary, 0),
        CASE
            WHEN IFNULL(e.start_date, "") != "" THEN e.start_date
            WHEN IFNULL(e.created_at, "") != "" THEN substr(e.created_at, 1, 10)
            ELSE date("now")
        END,
        NULL,
        CASE
            WHEN IFNULL(e.created_at, "") != "" THEN e.created_at
            ELSE datetime("now")
        END
    FROM employees e
    WHERE NOT EXISTS (
        SELECT 1 FROM employee_salary_history h WHERE h.employee_id = e.id
    )');

echo 'Setup complete. Default users: itadmin/IT@1234, hr01/HR@1234, acc01/ACC@1234';
