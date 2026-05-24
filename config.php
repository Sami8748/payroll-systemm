<?php

declare(strict_types=1);


/*การตั้งค่าระบบ Payroll System ค่าต่างๆ เหล่านี้ใช้ในการกำหนดพฤติกรรมของระบบ เช่น การเชื่อมต่อฐานข้อมูล การตั้งค่า session การส่งอีเมล และการตั้งค่ารหัสผ่าน*/


return [
    'app_name' => 'Payroll System', /*ชื่อแอปพลิเคชัน*/
    'app_url' => 'https://payroll-systemm-1.onrender.com', /*URL ของแอปพลิเคชัน*/
    //'db_path' => __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payroll.sqlite', /*เส้นทางไปยังไฟล์ฐานข้อมูล SQLite*/
    'session_name' => 'payroll_session', /*ชื่อ บัตรพนักงาน สำหรับระบบ*/
    'line_enabled' => false, /*เปิดใช้งานการแจ้งเตือนผ่าน LINE หรือไม่ ตอนนี้ปิดอยู่*/
    'line_notify_token' => '', // Deprecated for legacy mode
    'line_channel_access_token' => '',
    'line_api_base' => 'https://api.line.me/v2/bot/message/push',
    'smtp_host' => 'smtp.gmail.com', /*โฮสต์ SMTP สำหรับส่งอีเมล*/
    'smtp_port' => 587, /*พอร์ต SMTP สำหรับส่งอีเมล*/
    'smtp_secure' => 'tls', /*ประเภทการเข้ารหัสสำหรับ SMTP*/
    'smtp_username' => 'tonkhawsami@gmail.com', /*ชื่อผู้ใช้ SMTP*/
    'smtp_password' => 'spdnyzpyltngviuz', /*รหัสผ่าน SMTP - ใช้ App Password จาก Google*/
    'mail_from' => 'tonkhawsami@gmail.com', /*อีเมลผู้ส่ง*/
    'mail_from_name' => 'Payroll System', /*ชื่อผู้ส่งอีเมล*/
    'welfare_fund_rate_percent' => 0.20, /*อัตราส่วนของกองทุนเงินทดแทน0.20%*/
    'company_tax_id' => '', /*หมายเลขประจำตัวผู้เสียภาษีของบริษัท ที่ระบบจะดึงไปใส่หัวกระดาษตอนทำไฟล์ สปส. 1-10 และ ภ.ง.ด. 1*/
    'sso_employer_no' => '', /*หมายเลขนายจ้างสำหรับ SSO ที่ระบบจะดึงไปใส่หัวกระดาษตอนทำไฟล์ สปส. 1-10 และ ภ.ง.ด. 1*/
    'sso_branch_code' => '000000', /*รหัสสาขาสำหรับ SSO ที่ระบบจะดึงไปใส่หัวกระดาษตอนทำไฟล์ สปส. 1-10 และ ภ.ง.ด. 1*/
    'password_max_age_days' => 90, /*อายุสูงสุดของรหัสผ่าน (90วัน)*/
    'password_min_length' => 6, /*ความยาวขั้นต่ำของรหัสผ่าน*/
    'default_timezone' => 'Asia/Bangkok', /*เขตเวลามาตรฐานของระบบ*/
    'db_host' => getenv('DB_HOST'),
    'db_port' => getenv('DB_PORT'),
    'db_name' => getenv('DB_NAME'),
    'db_user' => getenv('DB_USER'),
    'db_pass' => getenv('DB_PASS'),
    'db_ssl'  => true,
];
