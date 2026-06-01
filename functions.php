<?php

declare(strict_types=1); /*การประกาศ strict_types เป็น true เพื่อเปิดใช้งานการตรวจสอบประเภทข้อมูลอย่างเข้มงวดใน PHP ซึ่งจะช่วยให้โค้ดมีความปลอดภัยและลดข้อผิดพลาดที่เกิดจากการใช้ประเภทข้อมูลที่ไม่ถูกต้อง*/

require_once __DIR__ . '/auth.php'; /*นำเข้าไฟล์ auth.php ซึ่งมีฟังก์ชันที่เกี่ยวข้องกับการตรวจสอบสิทธิ์และการจัดการผู้ใช้ เช่น current_user(), require_role(), logout() เป็นต้น เพื่อให้สามารถใช้ฟังก์ชันเหล่านี้ในไฟล์นี้ได้อย่างถูกต้องและปลอดภัย*/

function app_config(): array /*มีเพื่อดึงข้อมูลการตั้งค่าต่างๆจากไฟล์ config.php */
{
    static $config = null; /*ใช้ตัวแปร static เก็บข้อมูลการตั้งค่าเพื่อสามารถเรียกใช้ได้หลายครั้งโดยไม่ต้องโหลดไฟล์ config.php ซ้ำๆ*/

    /*ถ้ายังไม่มีการโหลดข้อมูลการตั้งค่า จะทำการโหลดจากไฟล์ config.php และตั้งค่า timezone ตามที่กำหนดใน config.php เพื่อให้ระบบสามารถใช้งานได้อย่างถูกต้องตามเวลาที่กำหนดในระบบ*/
    if ($config === null) { 
        $config = require __DIR__ . '/config.php';
        date_default_timezone_set($config['default_timezone']);/*ตั้งค่าไทม์โซนตามที่กำหนดใน config.php เพื่อให้ระบบสามารถใช้งานได้อย่างถูกต้องตามเวลาที่กำหนดในระบบ*/
    }

    return $config;
}

/*เอาไว้ดูว่าตอนนี้ระบบกำลังใช้ภาษาอะไรอยู่ ภาษาเริ่มต้นคือภาษาไทย (th)*/
function app_locale(): string
{
    boot_session(); /*หยิบบัตรพนักงาน(session) มาดูว่าเคยเลือกภาษาอะไรไว้ยัง่ ถ้ายังไม่เคยเลือกก็จะตั้งเป็นภาษาไทย (th) เป็นค่าเริ่มต้น และถ้ามีการส่งค่าภาษาใหม่มาทาง GET ก็จะอัปเดตภาษาที่เลือกไว้ใน session ให้เป็นภาษาที่ส่งมา*/

    /*เช็คการสั่งเปลี่ยนภาษาจากพารามิเตอร์ lang ใน  URL ถ้ามีส่งค่ามาและเป็นภาษา(th หรือ en) ก็จะอัปเดตภาษาที่เลือกไว้*/
    $allowed = ['th', 'en'];
    if (isset($_GET['lang'])) {
        $requested = strtolower((string)$_GET['lang']);
        if (in_array($requested, $allowed, true)) {
            $_SESSION['lang'] = $requested;
        }
    }

    if (!isset($_SESSION['lang']) || !in_array($_SESSION['lang'], $allowed, true)) {
        $_SESSION['lang'] = 'th';
    }

    return $_SESSION['lang'];
}

/*ฟังก์ชันนี้ใช้แปลภาษา จากนั้นจะดึงข้อมุลที่ต้องการแปลจากอาร์เรย์ $dict ตามภาษาที่กำหนดในฟังก์ชัน app_locale()*/
function t(string $key, array $replace = []): string
{
    $locale = app_locale(); /*เรียกใช้ฟังก์ชัน app_locale() เพื่อดึงภาษาที่ระบบกำลังใช้งานอยู่*/

    /*กางพจนานุกรมแปลภาษาออกมาเป็นตัวแปร $dict*/
    static $dict = [
        'th' => [
            'app_name' => 'ระบบเงินเดือน', 
            'it_admin' => 'ผู้ดูแลระบบไอที',
            'hr' => 'ฝ่ายบุคคล',
            'accounting' => 'ฝ่ายบัญชี',
            'ceo' => 'บริหาร',
            'dashboard' => 'แดชบอร์ด',
            'employees' => 'พนักงาน',
            'payroll' => 'เงินเดือน',
            'salary_entry' => 'บันทึกเงินเดือน',
            'payment_approval' => 'อนุมัติการจ่าย',
            'accounting_tools' => 'รายงานและตั้งเวลาจ่าย',
            'send_payslip' => 'ส่งสลิปเงินเดือน',
            'system_logs' => 'ประวัติการเข้าระบบ',
            'config_check' => 'ตรวจสอบตั้งค่า',
            'user_admin' => 'จัดการผู้ใช้',
            'logout' => 'ออกจากระบบ',
            'login_title' => 'เข้าสู่ระบบเงินเดือน',
            'login_subtitle' => 'ผู้ใช้ที่ได้รับสิทธิ์เท่านั้น: IT Admin, HR, Accounting, CEO',
            'username' => 'ชื่อผู้ใช้',
            'password' => 'รหัสผ่าน',
            'sign_in' => 'เข้าสู่ระบบ',
            'setup_hint' => 'รัน setup.php หนึ่งครั้งก่อนเข้าใช้งานครั้งแรก',
            'active_employees' => 'พนักงานที่ใช้งาน',
            'work_anniversary' => 'ครบรอบปีงาน',
            'anniversary_today' => 'ครบรอบวันนี้',
            'anniversary_upcoming' => 'ครบรอบที่กำลังจะมาถึง',
            'years_of_service' => 'อายุงาน (ปี)',
            'no_anniversary_30_days' => 'ไม่มีพนักงานครบรอบงานใน 30 วันถัดไป',
            'payroll_records' => 'รายการเงินเดือน',
            'paid_records' => 'รายการที่จ่ายแล้ว',
            'pending_records' => 'รายการรอดำเนินการ',
            'ceo_total_salary_summary' => 'สรุปภาพรวมเงินเดือน',
            'ceo_total_salary_paid' => 'ยอดจ่ายแล้วรวม',
            'ceo_total_outstanding' => 'ยอดค้างจ่ายรวม',
            'ceo_payroll_status' => 'สถานะการจ่ายเงินเดือน',
            'ceo_processed_of_total' => ':paid จาก :total รายการดำเนินการแล้ว',
            'ceo_recent_payroll_activity' => 'กิจกรรมเงินเดือนล่าสุด (ดูได้อย่างเดียว)',
            'ceo_dashboard_notice_title' => 'แดชบอร์ดผู้บริหาร:',
            'ceo_dashboard_notice' => 'บัญชีนี้มีสิทธิ์ดูภาพรวมเงินเดือนและรายงานเท่านั้น ไม่สามารถแก้ไขข้อมูลได้ หากต้องการแก้ไขกรุณาติดต่อ HR หรือ Accounting',
            'status_paid' => 'จ่ายแล้ว',
            'status_pending' => 'รอดำเนินการ',
            'status_draft' => 'ฉบับร่าง',
            'net_total' => 'ยอดสุทธิรวม',
            'latest_payroll' => 'รายการเงินเดือนล่าสุด',
            'manage_payroll' => 'จัดการเงินเดือน',
            'employee' => 'พนักงาน',
            'month_year' => 'เดือน/ปี',
            'net_salary' => 'เงินสุทธิ',
            'status' => 'สถานะ',
            'payment_timestamp' => 'เวลาจ่ายเงิน',
            'action' => 'การดำเนินการ',
            'no_payroll_records' => 'ยังไม่มีรายการเงินเดือน',
            'open' => 'เปิดดู',
            'msg_it_admin_manage' => 'IT Admin สามารถจัดการผู้ใช้และรีเซ็ตรหัสผ่านได้ที่หน้า User Admin',
            'msg_hr_non_manager_only' => 'HR สามารถจัดการได้เฉพาะเงินเดือนพนักงานที่ไม่ใช่ผู้จัดการตามนโยบาย',
            'employees_page' => 'ข้อมูลพนักงาน',
            'create_employee' => 'เพิ่มพนักงาน',
            'emp_code' => 'รหัสพนักงาน',
            'name' => 'ชื่อ',
            'department' => 'แผนก',
            'position' => 'ตำแหน่ง',
            'address' => 'ที่อยู่',
            'bank_name' => 'ธนาคาร',
            'select_bank' => 'เลือกธนาคาร',
            'bank_account' => 'เลขบัญชี',
            'national_id' => 'เลขบัตรประชาชน',
            'national_id_invalid' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลักเท่านั้น',
            'start_date' => 'วันที่เริ่มทำงาน',
            'effective_date' => 'วันที่มีผล',
            'email' => 'อีเมล',
            'line_user_id' => 'LINE User ID',
            'line_user_id_invalid' => 'LINE User ID ไม่ถูกต้อง ต้องขึ้นต้นด้วย U และมีความยาว 33 ตัวอักษร',
            'manager' => 'ผู้จัดการ',
            'yes' => 'ใช่',
            'no' => 'ไม่ใช่',
            'save_employee' => 'บันทึกพนักงาน',
            'employee_list' => 'รายชื่อพนักงาน',
            'salary_history' => 'ประวัติการเปลี่ยนเงินเดือนพื้นฐาน',
            'updated_by' => 'อัปเดตโดย',
            'updated_at' => 'เวลาอัปเดต',
            'salary_history_empty' => 'ยังไม่มีประวัติการเปลี่ยนเงินเดือน',
            'code' => 'รหัส',
            'line' => 'ไลน์',
            'type' => 'ประเภท',
            'no_employees' => 'ยังไม่มีพนักงาน',
            'manager_type' => 'ผู้จัดการ',
            'employee_type' => 'พนักงาน',
            'payroll_management' => 'จัดการเงินเดือน',
            'create_payroll_hr_only' => 'สร้างรายการเงินเดือน (เฉพาะ HR)',
            'select_employee' => 'เลือกพนักงาน',
            'month' => 'เดือน',
            'year' => 'ปี',
            'base_salary' => 'เงินเดือนพื้นฐาน',
            'overtime' => 'โอที',
            'bonus' => 'โบนัส',
            'deductions' => 'หัก',
            'other_deductions' => 'หักอื่นๆ',
            'late_deduction' => 'หักมาสาย',
            'absence_deduction' => 'หักขาดงาน',
            'welfare_loan_deduction' => 'หักเงินกู้สวัสดิการ',
            'social_security' => 'ประกันสังคม',
            'withholding_tax' => 'ภาษีหัก ณ ที่จ่าย',
            'welfare_fund' => 'กองทุนเงินทดแทน',
            'welfare_fund_summary' => 'คำนวณกองทุนเงินทดแทนรายปี',
            'annual_salary_base' => 'ฐานเงินเดือนรวมทั้งปี',
            'welfare_fund_rate' => 'อัตราสมทบ',
            'welfare_fund_contribution_due' => 'ยอดสมทบที่บริษัทต้องจ่าย',
            'welfare_fund_year' => 'ปีที่ใช้คำนวณ',
            'global_constants' => 'ตัวแปรกลางนโยบายบริษัท',
            'save_constants' => 'บันทึกตัวแปรกลาง',
            'sso_cap_update' => 'เพดานประกันสังคม (ปี 2569 เป็นต้นไป)',
            'tax_rates_update' => 'ตารางภาษีอัตราก้าวหน้า',
            'upper_limit' => 'ช่วงรายได้สูงสุด',
            'rate_percent' => 'อัตรา (%)',
            'constants_saved' => 'บันทึกตัวแปรกลางเรียบร้อย',
            'notes' => 'หมายเหตุ',
            'optional_note' => 'หมายเหตุเพิ่มเติม (ถ้ามี)',
            'save_payroll' => 'บันทึกรายการเงินเดือน',
            'period' => 'งวด',
            'base' => 'พื้นฐาน',
            'ot' => 'โอที',
            'deduction' => 'หัก',
            'slip' => 'สลิป',
            'no_records' => 'ยังไม่มีข้อมูล',
            'filter' => 'กรอง',
            'all_months' => 'ทุกเดือน',
            'all_years' => 'ทุกปี',
            'clear_filter' => 'ล้างตัวกรอง',
            'not_sent' => 'ยังไม่ส่ง',
            'not_paid_yet' => 'ยังไม่จ่าย',
            'pay' => 'จ่ายเงิน',
            'generate_pdf' => 'สร้าง PDF',
            'bulk_send' => 'ส่งสลิปทั้งหมด',
            'bulk_send_channel' => 'ช่องทางการส่ง (Bulk)',
            'bulk_send_result' => 'ส่งสลิปแบบ Bulk ผ่าน :channel สำเร็จ :success รายการ, ไม่สำเร็จ :failed รายการ',
            'pdf_ready' => 'PDF พร้อมใช้งาน',
            'current_datetime' => 'วันเวลา ณ ปัจจุบัน',
            'government_compliance_reports' => 'รายงานส่งหน่วยงานรัฐ',
            'generate_social_security_report' => 'สร้างรายงาน สปส. 1-10',
            'generate_tax_report' => 'สร้างรายงาน ภ.ง.ด. 1',
            'download_generated_report' => 'ดาวน์โหลดไฟล์รายงานล่าสุด',
            'payday_settings' => 'ตั้งค่าวันจ่ายเงิน',
            'scheduled_sending' => 'ตั้งเวลาส่งสลิปอัตโนมัติ',
            'schedule_sent_at' => 'วันเวลาในการส่ง',
            'schedule_created' => 'สร้างรายการตั้งเวลาส่งเรียบร้อย',
            'scheduled_jobs' => 'รายการตั้งเวลาส่ง',
            'channel' => 'ช่องทาง',
            'send_time' => 'เวลาส่ง',
            'processed_time' => 'เวลาประมวลผล',
            'pending' => 'รอดำเนินการ',
            'completed' => 'เสร็จสิ้น',
            'failed' => 'ล้มเหลว',
            'report_generated' => 'สร้างไฟล์รายงานเรียบร้อย:',
            'run_summary' => 'ผลการส่ง',
            'bulk_send_scope_note' => 'ระบบจะส่งตามเดือน/ปีที่เลือกในตัวกรอง',
            'portal_not_available' => 'ไม่มีพอร์ทัลพนักงานในระบบนี้ และสลิปจะถูกส่งโดย HR/Accounting ผ่าน Email หรือ LINE เท่านั้น',
            'it_admin_user_management' => 'IT Admin - จัดการผู้ใช้',
            'create_internal_user' => 'เพิ่มผู้ใช้ภายใน',
            'full_name' => 'ชื่อเต็ม',
            'role' => 'บทบาท',
            'create' => 'สร้าง',
            'save' => 'บันทึก',
            'existing_users' => 'ผู้ใช้ที่มีอยู่',
            'reset_password' => 'รีเซ็ตรหัสผ่าน',
            'change_password' => 'เปลี่ยนรหัสผ่าน',
            'current_password' => 'รหัสผ่านปัจจุบัน',
            'confirm_password' => 'ยืนยันรหัสผ่าน',
            'password_expired_notice' => 'รหัสผ่านหมดอายุ กรุณาเปลี่ยนรหัสผ่านก่อนใช้งานต่อ',
            'password_policy' => 'นโยบายรหัสผ่าน',
            'password_policy_hint' => 'รหัสผ่านต้องอย่างน้อย 6 ตัว และต้องมีตัวพิมพ์เล็ก, ตัวพิมพ์ใหญ่, ตัวเลข, อักขระพิเศษ พร้อมเปลี่ยนทุก 90 วัน',
            'password_policy_failed' => 'รหัสผ่านไม่ผ่านนโยบายความปลอดภัย',
            'password_confirm_mismatch' => 'ยืนยันรหัสผ่านไม่ตรงกัน',
            'current_password_invalid' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
            'password_changed_success' => 'เปลี่ยนรหัสผ่านเรียบร้อย',
            'database_backup' => 'สำรองฐานข้อมูล',
            'backup_success' => 'สำรองข้อมูลสำเร็จไฟล์:',
            'backup_failed' => 'ไม่สามารถสำรองฐานข้อมูลได้',
            'enable_disable' => 'เปิด/ปิดการใช้งาน',
            'active' => 'ใช้งาน',
            'inactive' => 'ปิดใช้งาน',
            'active_users' => 'ผู้ใช้ที่ใช้งาน',
            'new_password' => 'รหัสผ่านใหม่',
            'reset' => 'รีเซ็ต',
            'current_user' => 'ผู้ใช้ปัจจุบัน',
            'toggle' => 'สลับสถานะ',
            'delete_user' => 'ลบผู้ใช้',
            'user_removed' => 'ลบผู้ใช้ออกจากระบบแล้ว',
            'it_visible_staff' => 'ข้อมูลพนักงานที่ IT มองเห็นได้',
            'it_financial_blind_notice' => 'หน้าจอ IT ถูกปิดบังข้อมูลการเงินทั้งหมด และไม่ดึงคอลัมน์ salary/bonus ในหน้า IT',
            'system_logs_title' => 'System Logs (ไม่มีข้อมูลเงินเดือน)',
            'log_time' => 'เวลา',
            'log_user' => 'ผู้ใช้',
            'log_action' => 'การทำรายการ',
            'log_details' => 'รายละเอียด',
            'no_logs' => 'ยังไม่มีประวัติการใช้งาน',
            'lang_th' => 'ไทย',
            'lang_en' => 'English',
            'invalid_credentials' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
            'required_fields_missing' => 'กรอกข้อมูลจำเป็นไม่ครบ',
            'employee_created' => 'เพิ่มพนักงานสำเร็จ',
            'employee_updated' => 'อัปเดตข้อมูลพนักงานเรียบร้อย',
            'effective_date_invalid' => 'วันที่มีผลไม่ถูกต้อง',
            'employee_create_failed' => 'ไม่สามารถเพิ่มพนักงานได้ อาจมีรหัสพนักงานซ้ำ',
            'only_hr_create_payroll' => 'เฉพาะ HR เท่านั้นที่สร้างรายการเงินเดือนได้',
            'employee_not_found' => 'ไม่พบพนักงาน',
            'hr_cannot_create_manager' => 'HR ไม่สามารถสร้างเงินเดือนของผู้จัดการได้',
            'payroll_created' => 'สร้างรายการเงินเดือนสำเร็จ',
            'payroll_updated' => 'อัปเดตรายการเงินเดือนสำเร็จ',
            'edit_payroll' => 'แก้ไขรายการเงินเดือน',
            'payroll_not_found_or_paid' => 'ไม่พบรายการเงินเดือน หรือรายการนี้ยืนยันการจ่ายแล้ว',
            'social_security_policy_note' => 'ระบบคำนวณอัตโนมัติจากรายรับที่กรอก: ประกันสังคม 5% (เพดานฐานค่าจ้าง 17,500 บาท สูงสุด 875 บาท/เดือน สำหรับปี 2569 เป็นต้นไป), ภาษีหัก ณ ที่จ่ายแบบอัตราก้าวหน้า และใช้วันที่เริ่มทำงานคำนวณอัตราก้าวหน้า/โปรเรตงวดแรก',
            'social_security_included' => 'รวมประกันสังคม :amount บาท (หักอื่นๆ :other บาท, ภาษีหัก ณ ที่จ่าย :tax บาท)',
            'progressive_applied' => 'ปรับฐานตามอายุงาน :years ปี (+:rate%) และคำนวณงวดแรกตามวันทำงานจริง :worked/:days วัน',
            'progressive_applied_no_proration' => 'ปรับฐานตามอายุงาน :years ปี (+:rate%)',
            'payroll_create_failed' => 'ไม่สามารถสร้างรายการเงินเดือนได้ (อาจมีงวดซ้ำ)',
            'only_accounting_mark_paid' => 'เฉพาะฝ่ายบัญชีเท่านั้นที่ยืนยันการจ่ายเงินได้',
            'payroll_marked_paid' => 'บันทึกว่าจ่ายเงินแล้วเรียบร้อย',
            'payroll_marked_paid_pdf' => 'บันทึกว่าจ่ายเงินแล้วและสร้างสลิป PDF สำเร็จ',
            'only_hr_accounting_send_slip' => 'เฉพาะ HR หรือ Accounting เท่านั้นที่ส่งสลิปได้',
            'only_accounting_generate_slip' => 'เฉพาะฝ่ายบัญชีเท่านั้นที่สร้างสลิปได้',
            'only_accounting_send_slip' => 'เฉพาะฝ่ายบัญชีเท่านั้นที่ส่งสลิปได้',
            'payroll_not_found' => 'ไม่พบรายการเงินเดือน',
            'hr_cannot_access_manager' => 'HR ไม่สามารถเข้าถึงข้อมูลเงินเดือนของผู้จัดการได้',
            'payslip_sent_via' => 'ส่งสลิปผ่าน :channel เรียบร้อย',
            'payslip_send_failed' => 'ส่งสลิปผ่าน :channel ไม่สำเร็จ กรุณาตรวจสอบการตั้งค่าและ log',
            'payslip_generate_failed' => 'ไม่สามารถสร้างไฟล์สลิป PDF ได้ กรุณาตรวจสอบการตั้งค่าไลบรารี',
            'payslip_missing_national_id' => 'พนักงานยังไม่มีเลขบัตรประชาชน จึงไม่สามารถเข้ารหัส PDF ได้',
            'payslip_library_missing' => 'ไม่พบไลบรารี PDF หรือ PHPMailer กรุณาติดตั้ง composer dependencies ก่อน',
            'download_link_expired' => 'ลิงก์ดาวน์โหลดหมดอายุหรือไม่ถูกต้อง',
            'download_payslip' => 'ดาวน์โหลดสลิป',
            'invalid_input' => 'ข้อมูลไม่ถูกต้อง',
            'user_created' => 'สร้างผู้ใช้สำเร็จ',
            'user_create_failed' => 'ไม่สามารถสร้างผู้ใช้ได้ (ชื่อผู้ใช้อาจซ้ำ)',
            'invalid_password_input' => 'ข้อมูลรหัสผ่านไม่ถูกต้อง',
            'password_reset_complete' => 'รีเซ็ตรหัสผ่านเรียบร้อย',
            'cannot_disable_current_user' => 'ไม่สามารถปิดการใช้งานผู้ใช้ที่กำลังล็อกอินอยู่',
            'user_status_updated' => 'อัปเดตสถานะผู้ใช้แล้ว',
            'forbidden' => 'ไม่มีสิทธิ์เข้าถึง',
            /* CEO Charts */
            'ceo_dept_salary_chart' => 'ค่าใช้จ่ายเงินเดือนแยกตามแผนก',
            'ceo_monthly_trend' => 'แนวโน้มเงินเดือน (6 เดือนล่าสุด)',
            'ceo_vs_last_month' => 'เทียบเดือนที่แล้ว',
            'ceo_current_month_total' => 'เดือนล่าสุด',
            'ceo_last_month_total' => 'เดือนก่อนหน้า',
            'ceo_change_pct' => 'เปลี่ยนแปลง',
            'ceo_no_data' => 'ยังไม่มีข้อมูลเงินเดือน',
            /* Resignation */
            'resign_employee' => 'บันทึกการลาออก',
            'end_date' => 'วันที่ออกจากงาน',
            'resignation_reason' => 'เหตุผลการลาออก',
            'resigned' => 'ลาออกแล้ว',
            'employee_resigned' => 'บันทึกการลาออกเรียบร้อย',
            'employee_resign_failed' => 'บันทึกการลาออกล้มเหลว',
            'resigned_employees' => 'พนักงานที่ลาออกแล้ว',
            'last_month_prorated' => 'เดือนสุดท้าย: ทำงาน :worked วัน จาก :days วัน',
            /* Severance */
            'severance_pay' => 'เงินชดเชย',
            'leave_encashment' => 'เงินคืนวันลาพักร้อน',
            /* Documents */
            'emp_docs_title' => 'เอกสารประกอบพนักงาน',
            'emp_doc_type_national_id' => 'สำเนาบัตรประชาชน',
            'emp_doc_type_bank_book' => 'หน้าสมุดบัญชีธนาคาร',
            'emp_doc_type_other' => 'เอกสารอื่นๆ',
            'upload_document' => 'อัปโหลดเอกสาร',
            'doc_uploaded' => 'อัปโหลดเอกสารเรียบร้อย',
            'doc_upload_failed' => 'อัปโหลดเอกสารล้มเหลว',
            'doc_deleted' => 'ลบเอกสารเรียบร้อย',
            'doc_delete_failed' => 'ลบเอกสารล้มเหลว',
            'doc_invalid_type' => 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ PDF, JPG, PNG เท่านั้น)',
            'doc_too_large' => 'ไฟล์ใหญ่เกินไป (สูงสุด 5MB)',
            /* SSO 6-09 */
            'generate_sso_609' => 'สร้างรายงาน สปส. 6-09',
            'sso_609_title' => 'แจ้งสิ้นสุดความเป็นผู้ประกันตน สปส. 6-09',
            /* Leave Management */
            'leave_management' => 'บันทึกการลา',
            'leave_type' => 'ประเภทการลา',
            'leave_type_sick' => 'ลาป่วย',
            'leave_type_annual' => 'ลาพักร้อน',
            'leave_type_other' => 'ลากิจ/อื่นๆ',
            'leave_date' => 'วันที่ลา',
            'leave_days' => 'จำนวนวัน',
            'leave_note' => 'หมายเหตุ',
            'leave_recorded' => 'บันทึกการลาเรียบร้อย',
            'leave_record_failed' => 'บันทึกการลาล้มเหลว กรุณาลองใหม่',
            'leave_deleted' => 'ลบรายการลาเรียบร้อย',
            'leave_delete_failed' => 'ลบรายการลาล้มเหลว',
            'leave_quota_sick' => 'โควต้าลาป่วย',
            'leave_quota_annual' => 'โควต้าพักร้อน',
            'leave_used' => 'ใช้ไปแล้ว',
            'leave_remaining' => 'คงเหลือ',
            'leave_days_unit' => 'วัน',
            'leave_history' => 'ประวัติการลา',
            'leave_no_records' => 'ยังไม่มีรายการลา',
            'sick_leave_quota' => 'โควต้าลาป่วย (วัน/ปี)',
            'annual_leave_quota' => 'โควต้าพักร้อน (วัน/ปี)',
            'leave_select_employee' => 'เลือกพนักงาน',
            'leave_record_title' => 'บันทึกการลาพนักงาน',
            'leave_invalid_days' => 'จำนวนวันลาต้องมากกว่า 0',
            'leave_invalid_date' => 'วันที่ลาไม่ถูกต้อง',
        ],
        'en' => [
            'app_name' => 'Payroll System',
            'it_admin' => 'IT Admin',
            'hr' => 'HR',
            'accounting' => 'Accounting',
            'ceo' => 'Chief Executive Officer',
            'dashboard' => 'Dashboard',
            'employees' => 'Employees',
            'payroll' => 'Payroll',
            'salary_entry' => 'Salary Entry',
            'payment_approval' => 'Payment Approval',
            'accounting_tools' => 'Reports & Scheduling',
            'send_payslip' => 'Send Payslip',
            'system_logs' => 'System Logs',
            'config_check' => 'Config Check',
            'user_admin' => 'User Admin',
            'logout' => 'Logout',
            'login_title' => 'Payroll Login',
            'login_subtitle' => 'Authorized users only: IT Admin, HR, Accounting, CEO.',
            'username' => 'Username',
            'password' => 'Password',
            'sign_in' => 'Sign in',
            'setup_hint' => 'Run setup.php one time before first login.',
            'active_employees' => 'Active Employees',
            'work_anniversary' => 'Work Anniversary',
            'anniversary_today' => 'Anniversary Today',
            'anniversary_upcoming' => 'Upcoming Anniversary',
            'years_of_service' => 'Years of Service',
            'no_anniversary_30_days' => 'No work anniversary within next 30 days.',
            'payroll_records' => 'Payroll Records',
            'paid_records' => 'Paid Records',
            'pending_records' => 'Pending Records',
            'ceo_total_salary_summary' => 'Total Salary Summary',
            'ceo_total_salary_paid' => 'Total Salary Paid',
            'ceo_total_outstanding' => 'Total Outstanding',
            'ceo_payroll_status' => 'Payroll Status',
            'ceo_processed_of_total' => ':paid of :total processed',
            'ceo_recent_payroll_activity' => 'Recent Payroll Activity (Read-Only)',
            'ceo_dashboard_notice_title' => 'CEO Dashboard:',
            'ceo_dashboard_notice' => 'You have view-only access to payroll summaries and reports. For detailed modifications, contact the HR or Accounting department.',
            'status_paid' => 'PAID',
            'status_pending' => 'PENDING',
            'status_draft' => 'DRAFT',
            'net_total' => 'Net Total',
            'latest_payroll' => 'Latest Payroll',
            'manage_payroll' => 'Manage Payroll',
            'employee' => 'Employee',
            'month_year' => 'Month/Year',
            'net_salary' => 'Net Salary',
            'status' => 'Status',
            'payment_timestamp' => 'Payment Timestamp',
            'action' => 'Action',
            'no_payroll_records' => 'No payroll records',
            'open' => 'Open',
            'msg_it_admin_manage' => 'IT Admin can manage users and reset passwords in User Admin.',
            'msg_hr_non_manager_only' => 'HR can manage only non-manager payroll records by policy.',
            'employees_page' => 'Employees',
            'create_employee' => 'Create Employee',
            'emp_code' => 'Emp Code',
            'name' => 'Name',
            'department' => 'Department',
            'position' => 'Position',
            'address' => 'Address',
            'bank_name' => 'Bank',
            'select_bank' => 'Select Bank',
            'bank_account' => 'Bank Account',
            'national_id' => 'National ID',
            'national_id_invalid' => 'National ID must contain exactly 13 digits.',
            'start_date' => 'Start Date',
            'effective_date' => 'Effective Date',
            'email' => 'Email',
            'line_user_id' => 'LINE User ID',
            'line_user_id_invalid' => 'Invalid LINE User ID. It must start with U and be 33 characters long.',
            'manager' => 'Manager',
            'yes' => 'Yes',
            'no' => 'No',
            'save_employee' => 'Save Employee',
            'employee_list' => 'Employee List',
            'salary_history' => 'Base Salary Change History',
            'updated_by' => 'Updated By',
            'updated_at' => 'Updated At',
            'salary_history_empty' => 'No salary history records yet.',
            'code' => 'Code',
            'line' => 'LINE',
            'type' => 'Type',
            'no_employees' => 'No employees',
            'manager_type' => 'Manager',
            'employee_type' => 'Employee',
            'payroll_management' => 'Payroll Management',
            'create_payroll_hr_only' => 'Create Payroll (HR Only)',
            'select_employee' => 'Select employee',
            'month' => 'Month',
            'year' => 'Year',
            'base_salary' => 'Base Salary',
            'overtime' => 'Overtime',
            'bonus' => 'Bonus',
            'deductions' => 'Deductions',
            'other_deductions' => 'Other Deductions',
            'late_deduction' => 'Late Deduction',
            'absence_deduction' => 'Absence Deduction',
            'welfare_loan_deduction' => 'Welfare Loan Deduction',
            'social_security' => 'Social Security',
            'withholding_tax' => 'Withholding Tax',
            'welfare_fund' => 'Welfare Fund',
            'welfare_fund_summary' => 'Annual Welfare Fund Calculation',
            'annual_salary_base' => 'Annual Salary Base',
            'welfare_fund_rate' => 'Contribution Rate',
            'welfare_fund_contribution_due' => 'Company Contribution Due',
            'welfare_fund_year' => 'Calculation Year',
            'global_constants' => 'Global Company Constants',
            'save_constants' => 'Save Constants',
            'sso_cap_update' => 'Social Security Wage Ceiling (B.E. 2569 onward)',
            'tax_rates_update' => 'Progressive Tax Brackets',
            'upper_limit' => 'Upper Limit',
            'rate_percent' => 'Rate (%)',
            'constants_saved' => 'Global constants saved.',
            'notes' => 'Notes',
            'optional_note' => 'Optional note',
            'save_payroll' => 'Save Payroll',
            'period' => 'Period',
            'base' => 'Base',
            'ot' => 'OT',
            'deduction' => 'Deduction',
            'slip' => 'Slip',
            'no_records' => 'No records',
            'filter' => 'Filter',
            'all_months' => 'All months',
            'all_years' => 'All years',
            'clear_filter' => 'Clear filter',
            'not_sent' => 'Not sent',
            'not_paid_yet' => 'Not paid yet',
            'pay' => 'Pay',
            'generate_pdf' => 'Generate PDF',
            'bulk_send' => 'Bulk Send Slips',
            'bulk_send_channel' => 'Bulk Send Channel',
            'bulk_send_result' => 'Bulk send via :channel completed. Success: :success, Failed: :failed',
            'pdf_ready' => 'PDF Ready',
            'current_datetime' => 'Current Date/Time',
            'government_compliance_reports' => 'Government Compliance Reports',
            'generate_social_security_report' => 'Generate Social Security Report (SSF 1-10)',
            'generate_tax_report' => 'Generate Tax Report (PND.1)',
            'download_generated_report' => 'Download latest generated report',
            'payday_settings' => 'Payday Settings',
            'scheduled_sending' => 'Scheduled Sending',
            'schedule_sent_at' => 'Scheduled Send Time',
            'schedule_created' => 'Scheduled send has been created.',
            'scheduled_jobs' => 'Scheduled Jobs',
            'channel' => 'Channel',
            'send_time' => 'Send Time',
            'processed_time' => 'Processed Time',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'report_generated' => 'Report generated:',
            'run_summary' => 'Run Summary',
            'bulk_send_scope_note' => 'System sends based on selected month/year filters.',
            'portal_not_available' => 'Employee portal is not available. Payslip delivery is restricted to HR/Accounting via Email or LINE only.',
            'it_admin_user_management' => 'IT Admin - User Management',
            'create_internal_user' => 'Create Internal User',
            'full_name' => 'Full Name',
            'role' => 'Role',
            'create' => 'Create',
            'save' => 'Save',
            'existing_users' => 'Existing Users',
            'reset_password' => 'Reset Password',
            'change_password' => 'Change Password',
            'current_password' => 'Current Password',
            'confirm_password' => 'Confirm Password',
            'password_expired_notice' => 'Your password has expired. Please change it before continuing.',
            'password_policy' => 'Password Policy',
            'password_policy_hint' => 'Password must be at least 6 characters and include lowercase, uppercase, number, and special character. Change required every 90 days.',
            'password_policy_failed' => 'Password does not meet security policy.',
            'password_confirm_mismatch' => 'Password confirmation does not match.',
            'current_password_invalid' => 'Current password is incorrect.',
            'password_changed_success' => 'Password changed successfully.',
            'database_backup' => 'Database Backup',
            'backup_success' => 'Backup created:',
            'backup_failed' => 'Failed to create database backup.',
            'enable_disable' => 'Enable/Disable',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'active_users' => 'Active Users',
            'new_password' => 'New password',
            'reset' => 'Reset',
            'current_user' => 'Current user',
            'toggle' => 'Toggle',
            'delete_user' => 'Delete User',
            'user_removed' => 'User removed from active access.',
            'it_visible_staff' => 'Staff Directory For IT',
            'it_financial_blind_notice' => 'IT view is finance-blind and does not select salary/bonus columns.',
            'system_logs_title' => 'System Logs (No salary data)',
            'log_time' => 'Time',
            'log_user' => 'User',
            'log_action' => 'Action',
            'log_details' => 'Details',
            'no_logs' => 'No log entries',
            'lang_th' => 'ไทย',
            'lang_en' => 'English',
            'invalid_credentials' => 'Invalid username or password.',
            'required_fields_missing' => 'Required fields are missing.',
            'employee_created' => 'Employee created.',
            'employee_updated' => 'Employee record updated.',
            'effective_date_invalid' => 'Effective date is invalid.',
            'employee_create_failed' => 'Cannot create employee. Employee code might be duplicated.',
            'only_hr_create_payroll' => 'Only HR can create payroll records.',
            'employee_not_found' => 'Employee not found.',
            'hr_cannot_create_manager' => 'HR cannot create payroll for manager.',
            'payroll_created' => 'Payroll record created.',
            'payroll_updated' => 'Payroll record updated.',
            'edit_payroll' => 'Edit Payroll',
            'payroll_not_found_or_paid' => 'Payroll record not found or already paid.',
            'social_security_policy_note' => 'Auto-calculated from entered income: Social Security (5% with wage ceiling 17,500 THB, max 875 THB/month for B.E. 2569 onward), progressive withholding tax, and Start Date based progression/pro-rata for first month.',
            'social_security_included' => 'Included Social Security :amount THB (Other deductions :other THB, withholding tax :tax THB).',
            'progressive_applied' => 'Applied progression for :years years (+:rate%) and first-month pro-rata :worked/:days days.',
            'progressive_applied_no_proration' => 'Applied progression for :years years (+:rate%).',
            'payroll_create_failed' => 'Cannot create payroll (maybe duplicate period for employee).',
            'only_accounting_mark_paid' => 'Only Accounting can mark payment.',
            'payroll_marked_paid' => 'Payroll marked as paid.',
            'payroll_marked_paid_pdf' => 'Payroll marked as paid and encrypted payslip PDF generated.',
            'only_hr_accounting_send_slip' => 'Only HR or Accounting can send slip.',
            'only_accounting_generate_slip' => 'Only Accounting can generate payslip files.',
            'only_accounting_send_slip' => 'Only Accounting can send payslips.',
            'payroll_not_found' => 'Payroll not found.',
            'hr_cannot_access_manager' => 'HR cannot access manager payroll.',
            'payslip_sent_via' => 'Payslip sent via :channel.',
            'payslip_send_failed' => 'Failed to send payslip via :channel. Check delivery log or settings.',
            'payslip_generate_failed' => 'Cannot generate payslip PDF. Please verify library setup.',
            'payslip_missing_national_id' => 'Employee national ID is required for PDF password protection.',
            'payslip_library_missing' => 'PDF library or PHPMailer is missing. Install composer dependencies first.',
            'download_link_expired' => 'Download link is invalid or expired.',
            'download_payslip' => 'Download Payslip',
            'invalid_input' => 'Invalid input.',
            'user_created' => 'User created.',
            'user_create_failed' => 'Cannot create user (username may already exist).',
            'invalid_password_input' => 'Invalid password input.',
            'password_reset_complete' => 'Password reset complete.',
            'cannot_disable_current_user' => 'Cannot disable current login user.',
            'user_status_updated' => 'User status updated.',
            'forbidden' => 'Forbidden',
            /* CEO Charts */
            'ceo_dept_salary_chart' => 'Salary Cost by Department',
            'ceo_monthly_trend' => 'Monthly Payroll Trend (Last 6 Months)',
            'ceo_vs_last_month' => 'vs. Last Month',
            'ceo_current_month_total' => 'Latest Month',
            'ceo_last_month_total' => 'Previous Month',
            'ceo_change_pct' => 'Change',
            'ceo_no_data' => 'No payroll data available',
            /* Resignation */
            'resign_employee' => 'Record Resignation',
            'end_date' => 'Resignation Date',
            'resignation_reason' => 'Reason for Leaving',
            'resigned' => 'Resigned',
            'employee_resigned' => 'Resignation recorded.',
            'employee_resign_failed' => 'Failed to record resignation.',
            'resigned_employees' => 'Resigned Employees',
            'last_month_prorated' => 'Last month: worked :worked of :days days',
            /* Severance */
            'severance_pay' => 'Severance Pay',
            'leave_encashment' => 'Leave Encashment',
            /* Documents */
            'emp_docs_title' => 'Employee Documents',
            'emp_doc_type_national_id' => 'National ID Copy',
            'emp_doc_type_bank_book' => 'Bank Book',
            'emp_doc_type_other' => 'Other Document',
            'upload_document' => 'Upload Document',
            'doc_uploaded' => 'Document uploaded.',
            'doc_upload_failed' => 'Document upload failed.',
            'doc_deleted' => 'Document deleted.',
            'doc_delete_failed' => 'Document delete failed.',
            'doc_invalid_type' => 'Invalid file type. Only PDF, JPG, PNG are accepted.',
            'doc_too_large' => 'File too large. Maximum 5MB.',
            /* SSO 6-09 */
            'generate_sso_609' => 'Generate SSO 6-09 Report',
            'sso_609_title' => 'SSO 6-09 - Termination of Insurance',
            /* Leave Management */
            'leave_management' => 'Leave Records',
            'leave_type' => 'Leave Type',
            'leave_type_sick' => 'Sick Leave',
            'leave_type_annual' => 'Annual Leave',
            'leave_type_other' => 'Other Leave',
            'leave_date' => 'Leave Date',
            'leave_days' => 'Days',
            'leave_note' => 'Note',
            'leave_recorded' => 'Leave recorded successfully.',
            'leave_record_failed' => 'Failed to record leave. Please try again.',
            'leave_deleted' => 'Leave record deleted.',
            'leave_delete_failed' => 'Failed to delete leave record.',
            'leave_quota_sick' => 'Sick Leave Quota',
            'leave_quota_annual' => 'Annual Leave Quota',
            'leave_used' => 'Used',
            'leave_remaining' => 'Remaining',
            'leave_days_unit' => 'day(s)',
            'leave_history' => 'Leave History',
            'leave_no_records' => 'No leave records yet.',
            'sick_leave_quota' => 'Sick Leave Quota (days/year)',
            'annual_leave_quota' => 'Annual Leave Quota (days/year)',
            'leave_select_employee' => 'Select Employee',
            'leave_record_title' => 'Employee Leave Entry',
            'leave_invalid_days' => 'Days must be greater than 0.',
            'leave_invalid_date' => 'Invalid leave date.',
        ],
    ];

    /*พยายามดึงข้อความแปลจาก $dict ตามภาษาที่กำหนดและคีย์ที่ระบุ ถ้าไม่พบจะ fallback ไปที่ภาษาอังกฤษ และถ้ายังไม่พบอีกก็จะใช้คีย์เป็นข้อความเลย*/
    $message = $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;


    foreach ($replace as $token => $value) {
        $message = str_replace(':' . $token, (string)$value, $message);
        /*'payslip_sent_via' => 'ส่งสลิปผ่าน :channel เรียบร้อย'*/
        /*คำว่า :channel คือช่องทางการส่งสลิป เช่น อีเมล หรือ SMS */
        /*ถ้าระบบส่งสลีปผ่านอีเมล โค้ดจะส่งค่ามาให้เปลี่ยน :channel เป็น 'อีเมล' ทำให้ข้อความที่แสดงออกมาคือ 'ส่งสลิปผ่าน อีเมล เรียบร้อย'*/
    }

    return $message;
}

/*ทำความสะอาดข้อความก่อนเอาไปโชว์บนหน้าเว็บ เพื่อป้องกันการโจมตีแบบ XSS 
ใช้ฟังก์ชัน htmlspecialchars เพื่อให้มันกลายเป็นข้อความธรรมดาแทนที่จะถูกตีความเป็นโค้ด HTML หรือ JavaScript*/
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*ฟังก์ชันเก็บข้อความชั่วคราวใน session แสดงได้ครั้งเดียวแล้วหายไป เหมาะแสดงข้อความแจ้งเตือน เช่น บันทึกสำเร็จหรือเกิดข้อผิดพลาด*/
function flash(string $key, ?string $value = null): ?string
{
    boot_session(); 

    /*ถ้ามีการส่งค่า $value เข้ามา แสดงว่าต้องการตั้งค่าข้อความชั่วคราวสำหรับคีย์นี้ ดังนั้นจะเก็บข้อความไว้ใน session และไม่ต้องคืนค่าอะไรกลับมา*/
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    /*ถ้าไม่มีค่า $value แสดงว่าต้องการดึงข้อความชั่วคราวสำหรับคีย์นี้ ดังนั้นจะเช็คว่ามีข้อความสำหรับคีย์นี้ใน session หรือไม่ ถ้าไม่มีจะคืนค่า null ถ้ามีจะดึงข้อความมาเก็บในตัวแปร $message แล้วลบข้อความนั้นออกจาก session เพื่อให้แสดงได้ครั้งเดียว จากนั้นคืนค่าข้อความที่ดึงมา*/
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    /*ถ้าไม่มีข้อความสำหรับคีย์นี้ใน session จะคืนค่า null*/
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

/*ฟังก์ชันเก็บข้อความล่าสุดที่เกิดข้อผิดพลาดในการส่งสลีป*/
function set_last_delivery_error(string $message): void
{
    $GLOBALS['__last_delivery_error'] = $message;
}

/*ฟังก์ชันดึงข้อความล่าสุดที่เกิดจ้อผิดพลาดในการส่งสลีป*/
function get_last_delivery_error(): string
{
    return (string)($GLOBALS['__last_delivery_error'] ?? '');
}

/*ฟังก์ชันบันทึกประวัติการทำรายการของผู้ใช้ เช่นการสร้างพนักงานใหม่ การแก้ไขข้อมูล โดยจะเก็บไปใช้วิเคราะห์ภายหลังหรือแสดงในระบบได้*/
function audit_log(int $userId, string $action, string $details): void
{
    /*ฟังก์ชันนี้รับค่า $userID เป็นรหัสผู้ทำรายการ*/
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (:user_id, :action, :details, :created_at)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        /*ห้ามให้ระบบล้มจากการบันทึก log โดยเฉพาะระหว่าง logout*/
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

/*ฟังก์ชันแปลงชื่อบทบาทให้เป็นข้อความ เหมาะแสดงบนหน้าเว็บเช่น : admin_it จะแสดงเป็น IT Admin, grouping 
จะช่วยให้ไม่ต้องเขียนแปลงชื่อบทบาทซ้ำๆ ในหลายโค้ดที่เกี่ยวข้องกับการแสดงผลบทบาทของผู้ใช้*/
function role_label(string $role): string
{
    return [
        'admin_it' => t('it_admin'),
        'hr' => t('hr'),
        'accounting' => t('accounting'),
        'ceo' => t('ceo'),
    ][$role] ?? $role;
}


function can_hr_access_manager_data(array $user): bool
{
    return $user['role'] !== 'hr';
}

function hr_filter_clause(array $user): string
{
    if ($user['role'] !== 'hr') {
        return '';
    }

    return " AND e.position != 'Manager'";
}

/*ฟังก์ชันคำนวณเงินเดือนสุทธิ รับค่าเงินเดือนพื้นฐาน + โอที + โบนัส - หักต่างๆ = เงินเดือนสุทธิ พร้อมปัดเศษทศนิยม 2 ตำแหน่ง 
เพื่อเหมาะสมกับการแสดงผลและการคำนวณทางการเงินที่ต้องการความแม่นยำในระดับสตางค์*/
function calculate_net_salary(float $baseSalary, float $overtime, float $bonus, float $deductions): float
{
    return round($baseSalary + $overtime + $bonus - $deductions, 2);
}

/*ตารางภาษีอัตราก้าวหน้าตามนโยบายบริษัท โดยกำหนดช่วงรายได้สูงสุดและอัตราภาษีที่ต้องจ่ายสำหรับแต่ละช่วงรายได้ เพื่อใช้ในการคำนวณภาษีหัก ณ ที่จ่ายตามนโยบายของบริษัทที่กำหนดไว้*/
function default_tax_brackets(): array
{
    return [
        ['upper' => 150000.0, 'rate' => 0.00],
        ['upper' => 300000.0, 'rate' => 0.05],
        ['upper' => 500000.0, 'rate' => 0.10],
        ['upper' => 750000.0, 'rate' => 0.15],
        ['upper' => 1000000.0, 'rate' => 0.20],
        ['upper' => 2000000.0, 'rate' => 0.25],
        ['upper' => 5000000.0, 'rate' => 0.30],
        ['upper' => 999999999999.0, 'rate' => 0.35],
    ];
}

/*คำนวณนโยบายบริษัท*/
/*เก็บค่าที่เปลี่ยนแปลงบ่อยตามกฎหมายนโยบายบริษัท เช่น เพดานประกันสังคม ตารางภาษีอัตราก้าวหน้า เพื่อให้สามารถแก้ไขได้ง่ายโดยไม่ต้องแก้โค้ด และยังสามารถดึงค่าที่เก็บไว้มาใช้ในการคำนวณต่างๆ ได้อย่างสะดวก*/
function get_global_constant(string $key, $default = null)
{
    static $cache = []; /*ใช้ตัวแปร static เพื่อเก็บค่าที่ดึงมาจากฐานข้อมูลไว้ในหน่วยความจำระหว่างการทำงานของสคริปต์เดียวกัน เพื่อให้การเรียกใช้งานซ้ำๆ สำหรับคีย์เดียวกันไม่ต้องไปดึงจากฐานข้อมูลอีกครั้ง ซึ่งช่วยเพิ่มประสิทธิภาพและลดภาระของฐานข้อมูลได้มากขึ้น*/

    if (array_key_exists($key, $cache)) /*ถ้าเคยดึงค่าของคีย์นี้มาแล้วในระหว่างการทำงานของสคริปต์นี้ จะเก็บไว้ในตัวแปร $cache ดังนั้นถ้ามีการเรียกใช้งานซ้ำๆ สำหรับคีย์เดียวกัน จะเช็คใน $cache ก่อน ถ้ามีค่าอยู่แล้วก็จะคืนค่านั้นเลยโดยไม่ต้องไปดึงจากฐานข้อมูลอีกครั้ง ซึ่งช่วยเพิ่มประสิทธิภาพและลดภาระของฐานข้อมูลได้มากขึ้น*/ {
        return $cache[$key];
    }

    /*ถ้ายังไม่เคยดึงค่าของคีย์นี้มาในระหว่างการทำงานของสคริปต์นี้ จะไปดึงจากฐานข้อมูล โดยใช้คำสั่ง SQL SELECT เพื่อค้นหาค่า value_json ที่ตรงกับคีย์ที่ระบุในตาราง global_constants ถ้าพบก็จะนำค่าที่ได้มาแปลงจาก JSON เป็นรูปแบบข้อมูลที่เหมาะสม (เช่น array หรือ scalar) และเก็บไว้ใน $cache สำหรับการเรียกใช้งานครั้งต่อไป และคืนค่าที่ได้กลับมา ถ้าไม่พบหรือเกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล ก็จะเก็บค่า default ที่ส่งเข้ามาไว้ใน $cache และคืนค่า default นั้นกลับมาแทน*/
    try {
        $stmt = db()->prepare('SELECT value_json FROM global_constants WHERE key = :key LIMIT 1');
        $stmt->execute(['key' => $key]); 
        $row = $stmt->fetch(); 
        if ($row && isset($row['value_json'])) {
            $decoded = json_decode((string)$row['value_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $cache[$key] = $decoded;
                return $decoded;
            }
        }
        /*ถ้าเกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล เช่น ตาราง global_constants ยังไม่ถูกสร้าง หรือคีย์ที่ระบุไม่มีอยู่ในตาราง ก็จะข้ามไปยังส่วน catch เพื่อจัดการกับข้อผิดพลาดนั้น โดยในกรณีนี้จะไม่ทำอะไรเลยและปล่อยให้ระบบทำงานต่อไปโดยใช้ค่า default ที่ส่งเข้ามาแทน*/
    } catch (Throwable $e) {
        // Ignore and fallback to default when table is unavailable.
    }

    /*ถ้าไม่พบค่าที่ต้องการในฐานข้อมูล หรือเกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล ก็จะเก็บค่า default ที่ส่งเข้ามาไว้ใน $cache สำหรับคีย์นี้ เพื่อให้การเรียกใช้งานซ้ำๆ สำหรับคีย์เดียวกันในอนาคตจะได้ไม่ต้องไปดึงจากฐานข้อมูลอีกครั้ง และคืนค่า default นั้นกลับมาแทน*/
    $cache[$key] = $default; 
    return $default;
}

function set_global_constant(string $key, $value, ?int $updatedBy = null): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE); /*แปลงค่าที่ต้องการเก็บเป็น JSON เพื่อให้สามารถเก็บข้อมูลที่มีโครงสร้างซับซ้อนได้ เช่น array หรือ object และยังคงรักษาความถูกต้องของข้อมูลที่เป็นภาษาไทยหรืออักขระพิเศษได้ด้วยการใช้ตัวเลือก JSON_UNESCAPED_UNICODE ในการเข้ารหัส JSON ซึ่งจะทำให้ตัวอักษรที่เป็น Unicode ไม่ถูกแปลงเป็นรูปแบบ \uXXXX แต่จะเก็บเป็นตัวอักษรจริงๆ ใน JSON ที่ได้มาแทน ซึ่งช่วยให้การจัดการกับข้อมูลที่มีภาษาไทยหรืออักขระพิเศษในระบบนี้ทำได้ง่ายและถูกต้องมากขึ้น*/
    if ($json === false) {
        throw new RuntimeException('Failed to encode constant'); 
    }

    /*ใช้คำสั่ง SQL INSERT INTO ... ON CONFLICT เพื่อบันทึกค่าที่ต้องการเก็บลงในตาราง global_constants โดยถ้าคีย์ที่ระบุมีอยู่แล้วในตาราง จะทำการอัปเดตค่า value_json, updated_by, และ updated_at แทนการแทรกข้อมูลใหม่ ซึ่งช่วยให้การจัดการกับค่าที่เปลี่ยนแปลงบ่อยตามนโยบายบริษัท เช่น เพดานประกันสังคม ตารางภาษีอัตราก้าวหน้า ทำได้ง่ายและมีประสิทธิภาพมากขึ้น โดยไม่ต้องกังวลเรื่องการตรวจสอบว่าคีย์นั้นมีอยู่แล้วหรือไม่ก่อนที่จะทำการบันทึกข้อมูล*/
    $stmt = db()->prepare('INSERT INTO global_constants (key, value_json, updated_by, updated_at)
        VALUES (:key, :value_json, :updated_by, :updated_at)
        ON CONFLICT(key) DO UPDATE SET /*ถ้าคีย์ที่ระบุมีอยู่แล้วในตาราง global_constants จะทำการอัปเดตค่า value_json, updated_by, และ updated_at แทนการแทรกข้อมูลใหม่ โดยใช้ค่าที่ส่งเข้ามาในพารามิเตอร์ของฟังก์ชันนี้ ซึ่งช่วยให้การจัดการกับค่าที่เปลี่ยนแปลงบ่อยตามนโยบายบริษัท เช่น เพดานประกันสังคม ตารางภาษีอัตราก้าวหน้า ทำได้ง่ายและมีประสิทธิภาพมากขึ้น โดยไม่ต้องกังวลเรื่องการตรวจสอบว่าคีย์นั้นมีอยู่แล้วหรือไม่ก่อนที่จะทำการบันทึกข้อมูล*/
            value_json = excluded.value_json, /*ค่า value_json จะถูกอัปเดตเป็นค่าที่ส่งเข้ามาในพารามิเตอร์ของฟังก์ชันนี้ ซึ่งถูกแปลงเป็น JSON แล้ว และจะถูกเก็บไว้ในฐานข้อมูลเพื่อใช้ในการดึงค่าต่างๆ ตามนโยบายบริษัทในอนาคต*/
            updated_by = excluded.updated_by, /*ค่า updated_by จะถูกอัปเดตเป็นค่าที่ส่งเข้ามาในพารามิเตอร์ของฟังก์ชันนี้ ซึ่งระบุรหัสผู้ที่ทำการอัปเดตค่านี้ เพื่อให้สามารถติดตามได้ว่าใครเป็นผู้ที่ทำการเปลี่ยนแปลงค่าต่างๆ ตามนโยบายบริษัท และยังช่วยในการวิเคราะห์ประวัติการเปลี่ยนแปลงค่าต่างๆ ในระบบได้ด้วย*/
            updated_at = excluded.updated_at'); /*ค่า updated_at จะถูกอัปเดตเป็นเวลาปัจจุบันเมื่อมีการบันทึกค่าต่างๆ ตามนโยบายบริษัท เพื่อให้สามารถติดตามได้ว่าเมื่อไหร่ที่มีการเปลี่ยนแปลงค่าต่างๆ ในระบบ และยังช่วยในการวิเคราะห์ประวัติการเปลี่ยนแปลงค่าต่างๆ ในระบบได้ด้วย*/
    /*เมื่อเตรียมคำสั่ง SQL เสร็จแล้ว จะทำการ execute คำสั่งนั้นโดยส่งค่าที่ต้องการเก็บลงในฐานข้อมูลผ่านพารามิเตอร์ของฟังก์ชันนี้ ซึ่งจะทำให้ค่าที่เปลี่ยนแปลงบ่อยตามนโยบายบริษัท เช่น เพดานประกันสังคม ตารางภาษีอัตราก้าวหน้า ถูกบันทึกลงในฐานข้อมูลอย่างถูกต้องและมีประสิทธิภาพมากขึ้น โดยไม่ต้องกังวลเรื่องการตรวจสอบว่าคีย์นั้นมีอยู่แล้วหรือไม่ก่อนที่จะทำการบันทึกข้อมูล และยังสามารถติดตามได้ว่าใครเป็นผู้ที่ทำการเปลี่ยนแปลงค่าต่างๆ ในระบบและเมื่อไหร่ที่มีการเปลี่ยนแปลงค่าต่างๆ ในระบบด้วย*/
    $stmt->execute([
        'key' => $key,
        'value_json' => $json,
        'updated_by' => $updatedBy,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

/*ฟังก์ชันแปลงปี ค.ศ. เป็น พ.ศ. และเพื่อความสะดวกในการใช้งานในระบบ จะตรวจสอบว่าปีที่ส่งมาเป็นปี พ.ศ. อยุ่แล้วหรือไม่
ถ้าเป็นปี พ.ศ.ที่มากกว่า 2400 จะถือว่าเป็นปีพ.ศ.อยุ่แล้ว และจะคืนค่าปีนั้นกลับมาเลย แต่ถ้าเป็นปีที่น้อยกว่าหรือเท่ากับ 2400 จะถือว่าเป็นปีค.ศ. 
จะทำการแปลงโดย +543 เพื่อให้ได้ปี พ.ศ. และคืนค่าปีพ.ศ.ที่ได้กลับมา*/
function payroll_policy_buddhist_year(int $year): int 
{
    return $year > 2400 ? $year : $year + 543; /*ถ้า $year มากกว่า 2400 จะถือว่าเป็นปี พ.ศ.อยู่แล้ว และจะคืนค่าปีนั้นกลับมาเลย แต่ถ้า $year น้อยกว่าหรือเท่ากับ 2400 จะถือว่าเป็นปีค.ศ. และจะทำการแปลงโดย +543 เพื่อให้ได้ปี พ.ศ. และคืนค่าปีพ.ศ.ที่ได้กลับมา*/
}

function payroll_policy_gregorian_year(int $year): int /*ฟังก์ชันแปลงปี พ.ศ. เป็น ค.ศ. และเพื่อความสะดวกในการใช้งานในระบบ จะตรวจสอบว่าปีที่ส่งมาเป็นปี พ.ศ. อยุ่แล้วหรือไม่*/
{
    return $year > 2400 ? $year - 543 : $year; /*ถ้า $year มากกว่า 2400 จะถือว่าเป็นปี พ.ศ.อยู่แล้ว และจะทำการแปลงเป็นปี ค.ศ. โดย -543 แต่ถ้า $year น้อยกว่าหรือเท่ากับ 2400 จะถือว่าเป็นปีค.ศ. อยู่แล้ว และจะคืนค่าปีนั้นกลับมาเลย*/
}

/*ฟังก์ชันคำนวณวันสุดท้ายของเดือนสำหรับงวดเงินเดือนที่ระบุ โดยรับค่าเดือนและปีเป็นพารามิเตอร์ และจะตรวจสอบความถูกต้องของค่าเดือนและปีที่ส่งเข้ามา ถ้าค่าไม่ถูกต้องจะคืนค่า null แต่ถ้าค่าเดือนและปีถูกต้อง จะใช้ฟังก์ชัน cal_days_in_month เพื่อหาจำนวนวันในเดือนนั้นๆ และสร้างวัตถุ DateTimeImmutable ที่แสดงถึงวันสุดท้ายของเดือนนั้นๆ โดยใช้รูปแบบ '!Y-n-j' ซึ่งจะทำให้วันที่ถูกสร้างขึ้นโดยไม่มีเวลาและโซนเวลา และคืนค่าวัตถุ DateTimeImmutable ที่ได้กลับมา*/
function payroll_period_end_date(int $month, int $year): ?DateTimeImmutable
{
    if ($month < 1 || $month > 12) {
        return null;
    }

    $gregorianYear = payroll_policy_gregorian_year($year);
    if ($gregorianYear < 1900 || $gregorianYear > 3000) {
        return null;
    }

    $daysInMonth = (int)date('t', strtotime($gregorianYear . '-' . $month . '-01'));

    return DateTimeImmutable::createFromFormat(
        '!Y-n-j',
        $gregorianYear . '-' . $month . '-' . $daysInMonth
    ) ?: null;
}

/*นโยบายการคำนวณปีที่ทำงานของพนักงาน คำนวณจากวันที่เริ่มทำงานจนถึงวันสุดท้าย*/
function calculate_service_years(?string $startDate, int $month, int $year): int /*ฟังก์ชันคำนวณจำนวนปีที่พนักงานทำงาน โดยรับค่า startDate เป็นวันที่เริ่มทำงาน และค่าเดือนและปีของงวดเงินเดือนเป็นพารามิเตอร์*/
{
    if ($startDate === null || trim($startDate) === '') { /*ถ้า startDate เป็น null หรือเป็นสตริงว่างๆ หลังจาก trim แล้ว แสดงว่าไม่มีข้อมูลวันที่เริ่มทำงานที่ถูกต้อง ดังนั้นจะคืนค่า 0 ปีที่ทำงานกลับมาเลย เพราะไม่สามารถคำนวณได้*/
        return 0;
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d', trim($startDate)); /*สร้างวัตถุ DateTimeImmutable จากวันที่เริ่มทำงาน*/
    $periodEnd = payroll_period_end_date($month, $year); /*คำนวณวันสุดท้ายของเดือนสำหรับงวดเงินเดือนที่ระบุ โดยใช้ฟังก์ชัน payroll_period_end_date ที่ได้สร้างไว้ก่อนหน้านี้ ซึ่งจะคืนค่าวัตถุ DateTimeImmutable ที่แสดงถึงวันสุดท้ายของเดือนนั้นๆ หรือ null ถ้าค่าเดือนหรือปีไม่ถูกต้อง*/
    if (!$start || !$periodEnd || $start > $periodEnd) {
        return 0; /*ถ้าไม่สามารถสร้างวัตถุ DateTimeImmutable จากวันที่เริ่มทำงานได้ หรือไม่สามารถคำนวณวันสุดท้ายของเดือนได้ หรือถ้าวันที่เริ่มทำงานอยู่หลังวันสุดท้ายของเดือนที่ระบุ แสดงว่าไม่มีข้อมูลวันที่เริ่มทำงานที่ถูกต้องหรือไม่สามารถคำนวณได้ ดังนั้นจะคืนค่า 0 ปีที่ทำงานกลับมาเลย*/
    }

    return (int)$start->diff($periodEnd)->y; /*ถ้าทุกอย่างถูกต้อง จะคำนวณความแตกต่างระหว่างวันที่เริ่มทำงานและวันสุดท้ายของเดือนที่ระบุ โดยใช้เมธอด diff ของวัตถุ DateTimeImmutable ซึ่งจะคืนค่าวัตถุ DateInterval ที่มีคุณสมบัติ y แสดงจำนวนปีที่แตกต่างกัน และจะคืนค่าจำนวนปีที่พนักงานทำงานกลับมาเป็นจำนวนเต็ม*/
}

/*นโยบายการเพิ่มเงินเดือนแบบก้าวหน้า จะเพิ่มอัตราการเพิ่มเงินตามอายุงานของพนักงาน จะเพิ่ม 5% ต่อปีที่ทำงาน แต่จะมีสูงสุดที่ 50% 
เพื่อเป็นการให้รางวัล */
function calculate_progressive_rate_percent(int $serviceYears): float /*ฟังก์ชันคำนวณอัตราการเพิ่มเงินเดือนแบบก้าวหน้า โดยรับจำนวนปีที่พนักงานทำงานเป็นพารามิเตอร์*/
{
    if ($serviceYears <= 0) {
        return 0.0;
    }

    return min($serviceYears * 5.0, 50.0); /*ถ้าจำนวนปีที่พนักงานทำงานน้อยกว่าหรือเท่ากับ 0 จะคืนค่า 0.0% เพราะไม่มีการเพิ่มเงินเดือน แต่ถ้าจำนวนปีที่พนักงานทำงานมากกว่า 0 จะคำนวณอัตราการเพิ่มเงินเดือนโดยการคูณจำนวนปีที่ทำงานด้วย 5.0% และจะใช้ฟังก์ชัน min เพื่อจำกัดอัตราการเพิ่มเงินเดือนสูงสุดที่ 50.0% เพื่อเป็นการให้รางวัลสำหรับพนักงานที่มีอายุงานมาก แต่ไม่ให้เกินขอบเขตที่กำหนดไว้*/
}

/*ฟังก์ชันคำนวณเงินเดือนหลังจากเพิ่มอัตราการเพิ่มเงินเดือนแบบก้าวหน้า โดยรับค่าเงินเดือนพื้นฐานและอัตราการเพิ่มเงินเดือนแบบก้าวหน้าเป็นพารามิเตอร์ และจะคำนวณโดยการนำเงินเดือนพื้นฐานมาคูณกับ 1 บวกกับอัตราการเพิ่มเงินเดือนแบบก้าวหน้าที่ถูกแปลงเป็นทศนิยมแล้ว และจะปัดเศษผลลัพธ์ให้มีทศนิยม 2 ตำแหน่งเพื่อความเหมาะสมกับการแสดงผลและการคำนวณทางการเงินที่ต้องการความแม่นยำในระดับสตางค์*/
function apply_progressive_rate(float $baseSalary, float $ratePercent): float
{
    /*ถ้าอัตราการเพิ่มเงินเดือนแบบก้าวหน้าที่ส่งเข้ามาเป็นค่าลบ จะถือว่าไม่มีการเพิ่มเงินเดือน ดังนั้นจะใช้ max เพื่อให้แน่ใจว่าอัตราการเพิ่มเงินเดือนแบบก้าวหน้าจะไม่ต่ำกว่า 0.0% และจะคำนวณโดยการนำเงินเดือนพื้นฐานมาคูณกับ 1 บวกกับอัตราการเพิ่มเงินเดือนแบบก้าวหน้าที่ถูกแปลงเป็นทศนิยมแล้ว และจะปัดเศษผลลัพธ์ให้มีทศนิยม 2 ตำแหน่งเพื่อความเหมาะสมกับการแสดงผลและการคำนวณทางการเงินที่ต้องการความแม่นยำในระดับสตางค์*/
    return round($baseSalary * (1 + max($ratePercent, 0.0) / 100.0), 2);
    /*เอาเงินเดือนพื้นฐานมาคูณกับ 1 บวกกับอัตราการเพิ่มเงินเดือนแบบก้าวหน้าที่ถูกแปลงเป็นทศนิยมแล้ว
    โดยการแปลงอัตราการเพิ่มเงินเดือนจากเปอร์เซ็นต์เป็นทศนิยมจะทำโดยเอาอัตราการเพิ่มเงินเดือนมา หารด้วย100.0 */
}

/*ฟังก์ชันคำนวณเงินเดือนสำหรับเดือนแรกที่พนักงานเริ่มทำงาน เอาวัน เดือน ปี เป็นพารามิเตอร์*/
function apply_first_month_proration(float $salaryAfterProgressive, ?string $startDate, int $month, int $year): array 
{
    /*ถ้าไม่มีข้อมูลวันที่เริ่มทำงานที่ถูกต้อง หรือไม่สามารถคำนวณวันสุดท้ายของเดือนที่ระบุได้ หรือถ้าวันที่เริ่มทำงานอยู่หลังวันสุดท้ายของเดือนที่ระบุ แสดงว่าไม่มีข้อมูลวันที่เริ่มทำงานที่ถูกต้องหรือไม่สามารถคำนวณได้ ดังนั้นจะคืนค่าเงินเดือนหลังจากเพิ่มอัตราการเพิ่มเงินเดือนแบบก้าวหน้าโดยไม่ต้องปรับตามจำนวนวันที่ทำงานในเดือนแรก และจะระบุว่าไม่ได้ใช้การปรับตามจำนวนวันที่ทำงานในเดือนแรก และจำนวนวันที่ทำงานและจำนวนวันในเดือนเป็น 0 เพื่อให้สามารถแสดงผลได้อย่างเหมาะสม*/
    $default = [
        'salary' => round(max($salaryAfterProgressive, 0.0), 2), 
        'applied' => false,
        'days_worked' => 0,
        'days_in_month' => 0, 
    ];

    /*ตรวจสอบว่านพนักงานเริ่มงานในเดือนและปีเดียวกับงวดเงินเดือนที่ระบุมั้ย ถ้าไม่ใช่แสดงว่าไม่ต้องปรับตามจำนวนวันที่ทำงานในเดือนแรก เพราะพนักงานเริ่มงานก่อนหน้าแล้ว
    ดังนั้นจะคืนค่าเงินเดือนหลังจากเพิ่มอัตราการเพิ่มเงินเดือนแบบก้าวหน้า*/

    /*ถ้า startDate เป็น null หรือเป็นสตริงว่างๆ หลังจาก trim แล้ว แสดงว่าไม่มีข้อมูลวันที่เริ่มทำงานที่ถูกต้อง ดังนั้นจะคืนค่า default ที่กำหนดไว้ข้างต้นกลับมาเลย เพราะไม่สามารถคำนวณได้*/
    if ($startDate === null || trim($startDate) === '') {
        return $default;
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d', trim($startDate));
    $periodEnd = payroll_period_end_date($month, $year);
    if (!$start || !$periodEnd) {
        return $default;
    }

    $periodStart = $periodEnd->modify('first day of this month');
    if ($start < $periodStart || $start > $periodEnd) {
        return $default;
    }

/*ถ้าทุกอย่างถูกต้อง จะคำนวณจำนวนวันในเดือนนั้นๆ และจำนวนวันที่พนักงานทำงานในเดือนแรก โดยการนำจำนวนวันในเดือนมาลบกับวันที่เริ่มทำงานแล้วบวก 1 เพื่อให้รวมวันที่เริ่มทำงานด้วย และจะใช้ฟังก์ชัน max เพื่อให้แน่ใจว่าจำนวนวันที่พนักงานทำงานจะไม่ต่ำกว่า 0 เพราะถ้าวันที่เริ่มทำงานอยู่หลังวันสุดท้ายของเดือนที่ระบุ จะทำให้จำนวนวันที่พนักงานทำงานเป็นค่าลบ ซึ่งไม่สมเหตุสมผล ดังนั้นจะใช้ max เพื่อให้แน่ใจว่าจำนวนวันที่พนักงานทำงานจะไม่ต่ำกว่า 0 และจะคำนวณเงินเดือนโดยการนำเงินเดือนหลังจากเพิ่มอัตราการเพิ่มเงินเดือนแบบก้าวหน้ามาหารด้วยจำนวนวันในเดือนแล้วคูณกับจำนวนวันที่พนักงานทำงานในเดือนแรก เพื่อให้ได้เงินเดือนที่ปรับตามจำนวนวันที่ทำงานในเดือนแรก และจะปัดเศษผลลัพธ์ให้มีทศนิยม 2 ตำแหน่งเพื่อความเหมาะสมกับการแสดงผลและการคำนวณทางการเงินที่ต้องการความแม่นยำในระดับสตางค์*/
    $daysInMonth = (int)$periodEnd->format('j'); /*จำนวนวันในเดือนนั้นๆ จะถูกคำนวณโดยการใช้ฟังก์ชัน format ที่แสดงถึงจำนวนวันในเดือนนั้นๆ จะได้ค่าที่เป็นจำนวนเต็มของวันในเดือนนั้นๆ*/
    $daysWorked = max($daysInMonth - (int)$start->format('j') + 1, 0);/*จำนวนวันที่พนักงานทำงานจริงในเดือนแรกจะถูกคำนวณโดยการนำจำนวนวันในเดือนนั้นๆ มาลบกับวันที่เริ่มทำงานแล้วบวก 1 เพื่อให้รวมวันที่เริ่มทำงานด้วย และจะใช้ฟังก์ชัน max เพื่อให้แน่ใจว่าจำนวนวันที่พนักงานทำงานจะไม่ต่ำกว่า 0 เพราะถ้าวันที่เริ่มทำงานอยู่หลังวันสุดท้ายของเดือนที่ระบุ จะทำให้จำนวนวันที่พนักงานทำงานเป็นค่าลบ ซึ่งไม่สมเหตุสมผล ดังนั้นจะใช้ max เพื่อให้แน่ใจว่าจำนวนวันที่พนักงานทำงานจะไม่ต่ำกว่า 0*/
    $proratedSalary = $daysInMonth > 0 
        /*สูตรคำนวณเงินเดือนที่ปรับตามจำนวณวันที่ทำงานในเดือนแรก โดย (เงินเดือนเต็ม / จำนวนวันทั้งหมดในเดือน) * จำนวนวันที่พนักงานทำงานจริง แล้วก็ปัดเศษผลลัพธ์ให้มีทศนิยม 2 ตำแหน่ง*/
        ? round(($salaryAfterProgressive / $daysInMonth) * $daysWorked, 2)
        : 0.0;

    /*คืนค่าเงินเดือนที่ปรับตามจำนวนวันที่ทำงานในเดือนแรก*/
    return [
        'salary' => $proratedSalary, /*เงินเดือนที่ปรับตามจำนวนวันที่ทำงานในเดือนแรก ถูกคำนวณโดยนำเงินเดือนหลังจากเพิ่มอัตราการเพิ่มเงินเดือนแบบก้าวหน้า*/
        'applied' => true, /*ระบุว่าได้ใช้สูตรเฉลี่ยปรับตามจำนวนวันที่ทำงานในเดือนแรกแล้ว*/
        'days_worked' => $daysWorked, /*จำนวนวันที่พนักงานทำงานจริงในเดือนแรก*/
        'days_in_month' => $daysInMonth, /*จำนวนวันทั้งหมดในเดือนนั้นๆ*/
        /*ส่งข้อมูล days_worked และ days_in_month เพื่อตอนพิมพ์สลีปจะได้แสดงข้อความประกอบ เช่น ทำงาน 15 วัน จากทั้งหมด 30 วัน*/
    ];
    /*คอยเช็คว่าใครเป็นเด็กใหม่ที่เพิ่งเข้ามาทำงานกลางเดือนบ้าง ถ้าเจอ มันจะจับเงินเดือนมาหารด้วยจำนวนวันในเดือนนั้น แล้วคูณด้วยจำนวนวันที่มาทำงานจริงให้แบบอัตโนมัติ*/
}

/*ฟังก์ชันคำนวณเงินเดือนงวดสุดท้ายเมื่อพนักงานลาออกกลางเดือน สูตรเดียวกับงวดแรก แต่ใช้ end_date แทน start_date*/
function apply_last_month_proration(float $salaryAfterProgressive, ?string $endDate, int $month, int $year): array
{
    $default = [
        'salary' => round(max($salaryAfterProgressive, 0.0), 2),
        'applied' => false,
        'days_worked' => 0,
        'days_in_month' => 0,
    ];

    if ($endDate === null || trim($endDate) === '') {
        return $default;
    }

    $end = DateTimeImmutable::createFromFormat('!Y-m-d', trim($endDate));
    $periodEnd = payroll_period_end_date($month, $year);
    if (!$end || !$periodEnd) {
        return $default;
    }

    $periodStart = $periodEnd->modify('first day of this month');
    /*ใช้เฉพาะถ้าวันลาออกอยู่ในเดือนงวดเงินเดือนนี้เท่านั้น*/
    if ($end < $periodStart || $end > $periodEnd) {
        return $default;
    }

    $daysInMonth = (int)$periodEnd->format('j');
    $daysWorked = max(min((int)$end->format('j'), $daysInMonth), 0);
    $proratedSalary = $daysInMonth > 0
        ? round(($salaryAfterProgressive / $daysInMonth) * $daysWorked, 2)
        : 0.0;

    return [
        'salary' => $proratedSalary,
        'applied' => true,
        'days_worked' => $daysWorked,
        'days_in_month' => $daysInMonth,
    ];
}


function social_security_wage_ceiling(int $year): float 
{
    $beYear = payroll_policy_buddhist_year($year);/*แปลงปีที่ส่งเข้ามาเป็นปี พ.ศ. เพื่อใช้ในการตรวจสอบเพดานเงินเดือนประกันสังคม ใช้ฟังก์ชัน payroll_policy_buddhist_year ที่ได้สร้างไว้ก่อนหน้านี้ ซึ่งจะคืนค่าปีพ.ศ.ที่ได้จากการแปลงปีที่ส่งเข้ามา*/
    if ($beYear >= 2569) { /*ถ้าปีพ.ศ.ที่ได้จากการแปลงตั้งแต่ปี 2569 ระบบจะขยับเพดานเงินประกันสังคมขึ้นเป็น 17,500 บาท (หัก 5% = 875 บาท)*/
        return (float)get_global_constant('sso_wage_ceiling_be_2569_onward', 17500.0);
    }
    /*ถ้าปีพ.ศ.ที่ได้จากการแปลงน้อยกว่า 2569 ระบบจะใช้เพดานเงินประกันสังคมเดิมที่ 15,000 บาท (หัก 5% = 750 บาท)*/
    return 15000.0;
}

/*ฟังก์ชันคำนวณเงินสมทบประกันสังคม โดยรับเงินเดือนพื้นฐานและปีเป็นพารามิเตอร์ และจะคำนวณโดยการนำเงินเดือนพื้นฐานมาคำนวณกับเพดานเงินเดือนประกันสังคมที่ได้จากฟังก์ชัน*/
function calculate_social_security_contribution(float $baseSalary, int $year): float
{
    /*ถ้าเงินเดือนพื้นฐานที่ส่งมาเป็นค่าลบ ถือว่าไม่มีเงินเดือน จึงใช้ 0.0 เป็นฐานในการคำนวณ และใช้ฟังก์ชัน min ให้แน่ใจว่าเงินเดือนที่ใช้ในการคำนวณจะไม่เกินเพดานประกันสังคม*/
    $assessableWage = min(max($baseSalary, 0.0), social_security_wage_ceiling($year));
    return round($assessableWage * 0.05, 2); /*คำนวณเงินประกันสังคมโดยการนำเงินเดือน (ที่ไม่เกินเพดานประกันสังคม) * 0.05 (อัตราเงินสมทบประกันสังคม 5%) และปัดเศษผลลัพธ์ให้มีทศนิยม 2 คำแหน่งเพื่อความเหมาะสมกับการแสดงผล*/
}


/*ฟังก์ชันคำนวณภาษีหัก ณ ที่จ่าย โดยรับเงินเดือนพื้นฐาน, ค่าล่วงเวลา, โบนัส และปีเป็นพารามิเตอร์*/
function calculate_withholding_tax(float $baseSalary, float $overtime, float $bonus, int $year): float
{
    /*คำนวณรายได้รวมต่อเดือนโดยนำเงินเดือนพื้นฐานมาบวกกับค่าล่วงเวลาและโบนัส ใช้ฟังก์ชัน max เพื่อให้แน่ใจว่ารายได้รวมจะไม่ติดลบ ถ้าติดลบจะถือว่าเป็น 0.0*/
    $monthlyIncome = max($baseSalary + $overtime + $bonus, 0.0);
    $monthlySocialSecurity = calculate_social_security_contribution($baseSalary, $year); /*คำนวณเงินสมทบประกันสังคมโดยใช้ฟังก์ชัน calculate_social_security_contribution ที่ได้สร้างไว้ก่อนหน้านี้ ซึ่งจะคืนค่าเงินสมทบประกันสังคมที่คำนวณจากเงินเดือนพื้นฐานและปีที่ส่งเข้ามา*/

    /*คำนวณรายได้รวมต่อปีโดยนำเงินเดือนรวมมาคูณ 12 เพื่อให้ได้รายได้ต่อปี */
    $annualIncome = $monthlyIncome * 12.0;
    $annualExpenseDeduction = min($annualIncome * 0.5, 100000.0); /*หักค่าใช้จ่ายประจำปี โดยใช้ 50% ของรายได้รวมต่อปี แต่ไม่เกิน 100,000 บาท*/
    $annualPersonalAllowance = 60000.0; /*ค่าลดหย่อนส่วนบุคคลต่อปี ทุกคนได้สิทธ์นี้*/
    $annualSocialSecurity = $monthlySocialSecurity * 12.0; /*เงินสมทบประกันสังคมต่อปี*/

    /*คำนวณรายได้สุทธิที่ต้องเสียภาษี โดยนำรายได้รวมต่อปี-คำใช้จ่ายประจำปี-ค่าลดหย่อนส่วนบุคคล-เงินสมทบประกันสังคมรายปี 
    และใช้ฟังก์ชัน max เพื่อให้แน่ใจว่ารายได้สุทธิที่ต้องเสียภาษีจะไม่ติดลบ ถ้าติดลบจะถือว่าเป็น 0.0 ซึ่งหมายความว่าไม่มีรายได้ที่ต้องเสียภาษี*/
    $taxableAnnualIncome = max(
        $annualIncome - $annualExpenseDeduction - $annualPersonalAllowance - $annualSocialSecurity,
        0.0
    );

    /*ดึงตารางภาษีอัตราก้าวหน้าจากฐานข้อมูล ใช้ฟังก์ชัน get_global_constant ที่ได้สร้างไว้ก่อนหน้านี้ โดยระบุคีย์ 'tax_brackets' และถ้าไม่มีค่าที่เก็บไว้ในฐานข้อมูล จะใช้ค่า default ที่ได้จากฟังก์ชัน default_tax_brackets ซึ่งจะคืนค่าตารางภาษีอัตราก้าวหน้าที่กำหนดไว้ล่วงหน้า*/
    $rawBrackets = get_global_constant('tax_brackets', default_tax_brackets());
    $brackets = [];
    if (is_array($rawBrackets)) {
        foreach ($rawBrackets as $item) {
            if (!is_array($item)) {
                continue;
            }
            $upper = isset($item['upper']) ? (float)$item['upper'] : 0.0;
            $rate = isset($item['rate']) ? (float)$item['rate'] : 0.0;
            if ($upper <= 0 || $rate < 0) {
                continue;
            }
            $brackets[] = ['upper' => $upper, 'rate' => $rate];
        }
    }

    if (!$brackets) {
        $brackets = default_tax_brackets();
    }

    usort($brackets, static fn(array $a, array $b): int => $a['upper'] <=> $b['upper']);

    $last = end($brackets);
    if (!is_array($last) || (float)$last['upper'] < INF) {
        $lastRate = is_array($last) ? (float)$last['rate'] : 0.35;
        $brackets[] = ['upper' => INF, 'rate' => $lastRate];
    }

    $annualTax = 0.0;
    $previousLimit = 0.0;

    foreach ($brackets as $bracket) {
        $upperLimit = (float)$bracket['upper'];
        $rate = (float)$bracket['rate'];
        if ($taxableAnnualIncome <= $previousLimit) {
            break;
        }

        $taxableAtRate = min($taxableAnnualIncome, $upperLimit) - $previousLimit;
        if ($taxableAtRate > 0) {
            $annualTax += $taxableAtRate * $rate;
        }

        $previousLimit = $upperLimit;
    }

    return round($annualTax / 12.0, 2);
}

function welfare_fund_rate_percent(): float
{
    $config = app_config();
    $rate = (float)($config['welfare_fund_rate_percent'] ?? 0.20);
    if ($rate < 0) {
        return 0.0;
    }

    return $rate;
}

function calculate_welfare_fund_contribution(float $annualSalaryBase, ?float $ratePercent = null): float
{
    $rate = $ratePercent ?? welfare_fund_rate_percent();
    return round(max($annualSalaryBase, 0.0) * max($rate, 0.0) / 100.0, 2);
}

function is_valid_line_user_id(string $lineUserId): bool
{
    $lineUserId = trim($lineUserId);
    return $lineUserId !== '';
}

function is_line_delivery_enabled(): bool
{
    $config = app_config();
    return (bool)($config['line_enabled'] ?? true);
}

function ensure_composer_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    $loaded = true;
}

function resolve_pdf_font_family_for_thai(): string
{
    static $resolved = null;
    if (is_string($resolved) && $resolved !== '') {
        return $resolved;
    }

    $fontCandidates = [
        [
            'regular' => 'C:/Windows/Fonts/THSarabunNew.ttf',
            'bold' => 'C:/Windows/Fonts/THSarabunNew Bold.ttf',
            'italic' => 'C:/Windows/Fonts/THSarabunNew Italic.ttf',
            'bolditalic' => 'C:/Windows/Fonts/THSarabunNew BoldItalic.ttf',
        ],
        [
            'regular' => 'C:/Windows/Fonts/tahoma.ttf',
            'bold' => 'C:/Windows/Fonts/tahomabd.ttf',
            'italic' => '',
            'bolditalic' => '',
        ],
        [
            'regular' => 'C:/Windows/Fonts/LeelawUI.ttf',
            'bold' => 'C:/Windows/Fonts/LeelaUIb.ttf',
            'italic' => '',
            'bolditalic' => '',
        ],
    ];

    if (class_exists('TCPDF_FONTS')) {
        foreach ($fontCandidates as $fontSet) {
            $regularPath = (string)($fontSet['regular'] ?? '');
            if ($regularPath === '' || !is_file($regularPath)) {
                continue;
            }

            try {
                $fontName = TCPDF_FONTS::addTTFfont($regularPath, 'TrueTypeUnicode', '', 96);

                $boldPath = (string)($fontSet['bold'] ?? '');
                if ($boldPath !== '' && is_file($boldPath)) {
                    TCPDF_FONTS::addTTFfont($boldPath, 'TrueTypeUnicode', 'B', 96);
                }

                $italicPath = (string)($fontSet['italic'] ?? '');
                if ($italicPath !== '' && is_file($italicPath)) {
                    TCPDF_FONTS::addTTFfont($italicPath, 'TrueTypeUnicode', 'I', 96);
                }

                $boldItalicPath = (string)($fontSet['bolditalic'] ?? '');
                if ($boldItalicPath !== '' && is_file($boldItalicPath)) {
                    TCPDF_FONTS::addTTFfont($boldItalicPath, 'TrueTypeUnicode', 'BI', 96);
                }
            } catch (Throwable $e) {
                $fontName = '';
            }
            if (is_string($fontName) && $fontName !== '') {
                $resolved = $fontName;
                return $resolved;
            }
        }
    }

    $resolved = 'freeserif';
    return $resolved;
}

function payslip_storage_dir(): string
{
    $dir = __DIR__ . '/storage/payslips';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function get_payroll_detail(int $payrollId): ?array
{
    $stmt = db()->prepare('SELECT pr.*, e.emp_code, e.name, e.department, e.position, e.email, e.line_user_id, e.national_id, e.bank_name, e.bank_account
        FROM payroll_runs pr
        JOIN employees e ON e.id = pr.employee_id
        WHERE pr.id = :id');
    $stmt->execute(['id' => $payrollId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function upsert_payslip_file(int $payrollId, string $filePath, string $fileName, int $generatedBy): array
{
    $token = bin2hex(random_bytes(24));
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));


    $stmt = db()->prepare('INSERT INTO payslip_files
        (payroll_id, file_path, file_name, download_token, expires_at, generated_by, generated_at, updated_at)
        VALUES(:payroll_id, :file_path, :file_name, :download_token, :expires_at, :generated_by, :generated_at, :updated_at)
        ON DUPLICATE KEY UPDATE
        file_path = VALUES(file_path),
        file_name = VALUES(file_name),
        download_token = VALUES(download_token),
        expires_at = VALUES(expires_at),
        generated_by = VALUES(generated_by),
        generated_at = VALUES(generated_at),
        updated_at = VALUES(updated_at)');

    $stmt->execute([
        'payroll_id' => $payrollId,
        'file_path' => $filePath,
        'file_name' => $fileName,
        'download_token' => $token,
        'expires_at' => $expiresAt,
        'generated_by' => $generatedBy,
        'generated_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'download_token' => $token,
        'expires_at' => $expiresAt,
        'file_path' => $filePath,
        'file_name' => $fileName,
    ];
}

function get_payslip_file_by_payroll(int $payrollId): ?array
{
    $stmt = db()->prepare('SELECT * FROM payslip_files WHERE payroll_id = :payroll_id LIMIT 1');
    $stmt->execute(['payroll_id' => $payrollId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function get_payslip_file_by_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT pf.*, pr.employee_id, pr.month, pr.year
        FROM payslip_files pf
        JOIN payroll_runs pr ON pr.id = pf.payroll_id
        WHERE pf.download_token = :token
        LIMIT 1');
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function build_private_download_url(string $token): string
{
    $base = rtrim((string)app_config()['app_url'], '/');
    return $base . '/download_payslip.php?token=' . urlencode($token);
}

function generate_encrypted_payslip_pdf(int $payrollId, int $generatedBy, ?string &$error = null): ?array
{
    ensure_composer_autoload();

    if (!class_exists('TCPDF')) {
        $error = t('payslip_library_missing');
        return null;
    }

    $detail = get_payroll_detail($payrollId);
    if (!$detail) {
        $error = t('payroll_not_found');
        return null;
    }

    $nationalId = trim((string)$detail['national_id']);
    if ($nationalId === '') {
        $error = t('payslip_missing_national_id');
        return null;
    }

    $safeNationalId = preg_replace('/[^0-9]/', '', $nationalId) ?: $nationalId;
    if (strlen($safeNationalId) < 4) {
        $error = t('payslip_missing_national_id');
        return null;
    }
    $pdfPassword = substr($safeNationalId, -4);
    $lateDeduction = (float)($detail['late_deduction'] ?? 0);
    $absenceDeduction = (float)($detail['absence_deduction'] ?? 0);
    $welfareLoanDeduction = (float)($detail['welfare_loan_deduction'] ?? 0);
    $otherDeductions = (float)($detail['other_deductions'] ?? $detail['deductions'] ?? 0);
    $socialSecurityDeduction = (float)($detail['social_security_deduction'] ?? 0);
    $withholdingTax = (float)($detail['withholding_tax'] ?? 0);

    $grossIncome = (float)$detail['base_salary'] + (float)$detail['overtime'] + (float)$detail['bonus'];
    $payDateRaw = (string)($detail['paid_at'] ?: $detail['updated_at'] ?: $detail['created_at']);
    $payDateTs = strtotime($payDateRaw);
    $payDateDisplay = $payDateTs !== false ? date('d/m/Y', $payDateTs) : '-';
    $periodDisplay = sprintf('%02d/%04d', (int)$detail['month'], (int)$detail['year']);

    $ytdStmt = db()->prepare('SELECT
            COALESCE(SUM(base_salary + overtime + bonus), 0) AS ytd_income,
            COALESCE(SUM(deductions), 0) AS ytd_deduction,
            COALESCE(SUM(withholding_tax), 0) AS ytd_tax,
            COALESCE(SUM(social_security_deduction), 0) AS ytd_ssf
        FROM payroll_runs
        WHERE employee_id = :employee_id
          AND year = :year
          AND month <= :month');
    $ytdStmt->execute([
        'employee_id' => (int)$detail['employee_id'],
        'year' => (int)$detail['year'],
        'month' => (int)$detail['month'],
    ]);
    $ytd = $ytdStmt->fetch() ?: [
        'ytd_income' => 0,
        'ytd_deduction' => 0,
        'ytd_tax' => 0,
        'ytd_ssf' => 0,
    ];

    $config = app_config();
    $companyName = trim((string)($config['company_name'] ?? $config['app_name'] ?? 'Payroll System'));
    $companyLogoPath = trim((string)($config['company_logo_path'] ?? ''));
    $companyLogoFile = '';
    if ($companyLogoPath !== '') {
        $isAbsolute = (bool)preg_match('/^[A-Za-z]:[\\\\\/]|^\//', $companyLogoPath);
        $resolved = $isAbsolute ? $companyLogoPath : (__DIR__ . DIRECTORY_SEPARATOR . ltrim($companyLogoPath, '\\/'));
        if (is_file($resolved)) {
            $companyLogoFile = str_replace('\\', '/', $resolved);
        }
    }

    $totalDeduction = (float)$detail['deductions'];
    $netPay = (float)$detail['net_salary'];

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('HR Payroll System');
    $pdf->SetAuthor('HR Payroll System');
    $pdf->SetTitle('Payslip ' . $detail['month'] . '/' . $detail['year']);
    $pdf->SetMargins(10, 8, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdfFont = resolve_pdf_font_family_for_thai();
    try {
        $pdf->SetFont($pdfFont, '', 10);
    } catch (Throwable $e) {
        try {
            $pdf->SetFont('freeserif', '', 10);
        } catch (Throwable $e2) {
            $pdf->SetFont('dejavusans', '', 10);
        }
    }
    $pdf->setFontSubsetting(true);
    $pdf->SetProtection(['print', 'copy'], $pdfPassword, null);

    $deptDisplay = trim((string)($detail['department'] ?? $detail['position'] ?? '-'));
    $providentFund = (float)($detail['provident_fund_deduction'] ?? 0);
    $parkingFee = (float)($detail['parking_fee_deduction'] ?? 0);
    $advanceDeduction = (float)($detail['advance_deduction'] ?? 0);
    $ytdProvident = (float)($detail['ytd_provident_fund'] ?? 0);
    $bonus = (float)($detail['bonus'] ?? 0);

    $tableDensity = strtolower((string)($config['payslip_table_density'] ?? 'comfortable'));
    if (!in_array($tableDensity, ['compact', 'comfortable'], true)) {
        $tableDensity = 'comfortable';
    }
    $isCompact = $tableDensity === 'compact';
    $gridCellPadding = $isCompact ? '4px 6px' : '6px 8px';
    $gridHeadPadding = $isCompact ? '5px 6px' : '7px 8px';
    $mainHeadMinHeight = $isCompact ? '14mm' : '16mm';
    $thaiHeadFontSize = $isCompact ? '10px' : '11px';
    $engHeadFontSize = $isCompact ? '8px' : '9px';
    $bodyFontSize = $isCompact ? '13px' : '14px';
    $headerFontSize = $isCompact ? '15px' : '16px';
    $thaiHeadFontSizeEn = $isCompact ? '11px' : '12px';
    $engHeadFontSizeEn = $isCompact ? '9px' : '10px';
    $cellPaddingValue = '8px 10px';
    $cellPaddingAttr = '8';

    // ===== BUILD COMPLETE PAYSLIP HTML WITH NEW PROFESSIONAL LAYOUT =====
    $html = ''
        // ===== HEADER WITH DIVIDER =====
        . '<style>'
        . 'body, table, td, th, div, span {'
        . 'font-family: ' . $pdfFont . ', tahoma, thsarabunnew, garuda, freeserif, dejavusans, sans-serif;'
        . '}'
        . '.table-full { width:100%; border-collapse:collapse; }'
        . '.grid { width:100%; border-collapse:collapse; border:1px solid #183B7E; }'
        . '.grid td, .grid th { border:1px solid #183B7E; padding:' . $gridCellPadding . '; vertical-align:middle; line-height:1.4; }'
        . '.grid .head-row th { padding:' . $gridHeadPadding . '; min-height:' . $mainHeadMinHeight . '; height:' . $mainHeadMinHeight . '; vertical-align:middle; }'
        . '.th-th { font-size:' . $thaiHeadFontSizeEn . '; font-weight:bold; line-height:1.3; }'
        . '.th-en { font-size:' . $engHeadFontSizeEn . '; font-weight:normal; line-height:1.25; }'
        . '.body-fs { font-size:' . $bodyFontSize . '; }'
        . '.text-right { text-align:right; }'
        . '.head-blue { background-color:#183B7E; color:#ffffff; font-weight:bold; }'
        . '</style>'
        . '<table class="table-full" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr>'
        . '<td width="50%" style="padding:12px 10px;font-size:' . $headerFontSize . ';font-weight:bold;color:#183B7E;line-height:1.4;vertical-align:middle;">บริษัท HR Payroll System</td>'
        . '<td width="50%" style="padding:12px 10px;font-size:' . $headerFontSize . ';font-weight:bold;color:#183B7E;text-align:right;line-height:1.4;vertical-align:middle;">ใบแจ้งเงินเดือน / PAY SLIP</td>'
        . '</tr>'
        . '</table>'

        // Double-line divider (thick top, thin bottom)
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:2px;">'
        . '<tr><td style="height:3px;background-color:#183B7E;font-size:1px;line-height:1px;padding:0;margin:0;">&nbsp;</td></tr>'
        . '</table>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">'
        . '<tr><td style="height:1px;background-color:#183B7E;font-size:1px;line-height:1px;padding:0;margin:0;">&nbsp;</td></tr>'
        . '</table>'

        // ===== EMPLOYEE INFORMATION SECTION (4 fields with dots) =====
        . '<table class="table-full" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">'
        . '<tr>'
        . '<td style="width:25%;padding:0 8px 8px 0;font-size:' . $headerFontSize . ';color:#183B7E;vertical-align:top;line-height:1.5;">'
        . '<strong>รหัส:</strong> ' . e((string)$detail['emp_code'])
        . '</td>'
        . '<td style="width:35%;padding:0 8px 8px 0;font-size:' . $headerFontSize . ';color:#183B7E;vertical-align:top;line-height:1.5;">'
        . '<strong>ชื่อพนักงาน:</strong> ' . e((string)$detail['name'])
        . '</td>'
        . '<td style="width:20%;padding:0 8px 8px 0;font-size:' . $headerFontSize . ';color:#183B7E;vertical-align:top;line-height:1.5;">'
        . '<strong>แผนก:</strong> ' . e($deptDisplay)
        . '</td>'
        . '<td style="width:20%;padding:0 0 8px 0;font-size:' . $headerFontSize . ';color:#183B7E;vertical-align:top;line-height:1.5;">'
        . '<strong>วันที่:</strong> ' . e($payDateDisplay)
        . '</td>'
        . '</tr>'
        . '</table>'

        // ===== MAIN INCOME/DEDUCTION TABLE WITH CLEAR 4-COLUMN HEADER =====
        . '<table class="grid" width="100%" cellpadding="' . $cellPaddingAttr . '" cellspacing="0" border="1" style="margin-bottom:8px;">' 
        . '<thead>'
        // ===== TABLE HEADER ROW (CRITICAL - 4 columns) =====
        . '<tr class="head-blue head-row">'
        . '<th width="35%" style="padding:' . $cellPaddingValue . ';"><div class="th-th">รายการเงินได้</div><div class="th-en">(Income)</div></th>'
        . '<th width="15%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">จำนวนเงิน</div><div class="th-en">(Baht)</div></th>'
        . '<th width="35%" style="padding:' . $cellPaddingValue . ';"><div class="th-th">รายการหัก</div><div class="th-en">(Deductions)</div></th>'
        . '<th width="15%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">จำนวนเงิน</div><div class="th-en">(Baht)</div></th>'
        . '</tr>'
        . '</thead>'
        . '<tbody>'

        // ===== INCOME ROWS =====
        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">เงินเดือน (Salary)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format((float)$detail['base_salary'], 2) . '</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">ภาษีหัก ณ ที่จ่าย (W/H TAX)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($withholdingTax, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">ค่าล่วงเวลา (Overtime)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format((float)$detail['overtime'], 2) . '</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">ประกันสังคม (Social Security Fund)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($socialSecurityDeduction, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">โบนัส (Bonus)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($bonus, 2) . '</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">กองทุนสำรองเลี้ยงชีพ (Provident Fund)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($providentFund, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">หักมาสาย (Late Deduction)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($lateDeduction, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">หักขาดงาน (Absence Deduction)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($absenceDeduction, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">หักเงินกู้สวัสดิการ (Welfare Loan)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($welfareLoanDeduction, 2) . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">&nbsp;</td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';">หักอื่นๆ (Other)</td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . ';">' . number_format($otherDeductions, 2) . '</td>'
        . '</tr>'

        // ===== TOTAL ROW (BLUE BACKGROUND, WHITE TEXT) =====
        . '<tr class="head-blue">'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">รวมรายการเงินได้</div><div class="th-en">(Total Income)</div></td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">' . number_format($grossIncome, 2) . '</div></td>'
        . '<td width="35%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">รวมรายการหัก</div><div class="th-en">(Total Deductions)</div></td>'
        . '<td width="15%" style="padding:' . $cellPaddingValue . '; text-align:right;"><div class="th-th">' . number_format($totalDeduction, 2) . '</div></td>'
        . '</tr>'
        . '</tbody>'
        . '</table>'

        // ===== YTD SECTION (5 COLUMNS) =====
        . '<table class="grid" width="100%" cellpadding="' . $cellPaddingAttr . '" cellspacing="0" border="1" style="margin-bottom:8px;">' 
        . '<thead>'
        . '<tr class="head-blue head-row">'
        . '<th width="20%" style="padding:' . $cellPaddingValue . '; text-align:center;"><div class="th-th">รายได้สะสม</div><div class="th-en">YTD Income</div></th>'
        . '<th width="20%" style="padding:' . $cellPaddingValue . '; text-align:center;"><div class="th-th">ภาษีสะสม</div><div class="th-en">YTD TAX</div></th>'
        . '<th width="20%" style="padding:' . $cellPaddingValue . '; text-align:center;"><div class="th-th">กองทุนสะสม</div><div class="th-en">YTD Provident Fund</div></th>'
        . '<th width="20%" style="padding:' . $cellPaddingValue . '; text-align:center;"><div class="th-th">ประกันฯ สะสม</div><div class="th-en">YTD Social Security</div></th>'
        . '<th width="20%" style="padding:' . $cellPaddingValue . '; text-align:center;"><div class="th-th">เงินได้สุทธิ</div><div class="th-en">Net Income</div></th>'
        . '</tr>'
        . '</thead>'
        . '<tbody>'
        . '<tr>'
        . '<td width="20%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . '; font-weight:bold;">' . number_format((float)$ytd['ytd_income'], 2) . '</td>'
        . '<td width="20%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . '; font-weight:bold;">' . number_format((float)$ytd['ytd_tax'], 2) . '</td>'
        . '<td width="20%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . '; font-weight:bold;">' . number_format($ytdProvident, 2) . '</td>'
        . '<td width="20%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . '; font-weight:bold;">' . number_format((float)$ytd['ytd_ssf'], 2) . '</td>'
        . '<td width="20%" style="padding:' . $cellPaddingValue . '; text-align:right; font-size:' . $bodyFontSize . '; font-weight:bold;">' . number_format($netPay, 2) . '</td>'
        . '</tr>'
        . '</tbody>'
        . '</table>'

        // ===== BANK ACCOUNT SECTION =====
        . '<table class="grid" width="100%" cellpadding="' . $cellPaddingAttr . '" cellspacing="0" border="1">'
        . '<tr>'
        . '<td style="padding:' . $cellPaddingValue . '; font-size:' . $bodyFontSize . ';"><strong>เลขที่บัญชีธนาคาร (Bank Account):</strong> ' . e(trim((string)($detail['bank_account'] ?? '')) ?: '-') . '</td>'
        . '</tr>'
        . '</table>'
        ;

    $pdf->writeHTML($html, true, false, true, false, '');

    $fileName = 'payslip_' . $payrollId . '_' . date('YmdHis') . '.pdf';
    $filePath = payslip_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    try {
        $pdf->Output($filePath, 'F');
    } catch (Throwable $e) {
        $error = t('payslip_generate_failed');
        return null;
    }

    return upsert_payslip_file($payrollId, $filePath, $fileName, $generatedBy);
}

function send_payslip_email(array $employee, array $payroll): bool
{
    ensure_composer_autoload();

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $msg = t('payslip_library_missing');
        set_last_delivery_error($msg);
        log_delivery('email', (string)$employee['name'], (string)$employee['email'], false, $msg);
        return false;
    }

    $to = trim((string)$employee['email']);
    if ($to === '') {
        $msg = 'Employee email is empty.';
        set_last_delivery_error($msg);
        log_delivery('email', (string)$employee['name'], '', false, $msg);
        return false;
    }

    $file = get_payslip_file_by_payroll((int)$payroll['id']);
    if (!$file || !is_file((string)$file['file_path'])) {
        $error = null;
        $file = generate_encrypted_payslip_pdf((int)$payroll['id'], (int)$payroll['slip_sent_by'] ?: (int)$payroll['created_by'], $error);
        if ($file === null) {
            $msg = $error ?? t('payslip_generate_failed');
            set_last_delivery_error($msg);
            log_delivery('email', (string)$employee['name'], $to, false, $msg);
            return false;
        }
    }

    $config = app_config();
    $smtpHost = trim((string)$config['smtp_host']);
    if ($smtpHost === '') {
        $msg = 'SMTP is not configured. Please set smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password in config.php';
        set_last_delivery_error($msg);
        log_delivery('email', (string)$employee['name'], $to, false, $msg);
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $smtpUsername = trim((string)$config['smtp_username']);
        $smtpPassword = trim((string)$config['smtp_password']);


        

        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;
        
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';

        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string)$config['mail_from'], (string)$config['mail_from_name']);
        $mail->addAddress($to, (string)$employee['name']);
        $mail->Subject = sprintf('Payslip %s/%s - %s', $payroll['month'], $payroll['year'], $employee['name']);
        $mail->Body = "Attached is your payslip PDF.\nPassword: Employee National ID";
        $mail->addAttachment((string)$file['file_path'], (string)$file['file_name']);
        $mail->send();

        set_last_delivery_error('');
        log_delivery('email', (string)$employee['name'], $to, true, 'PHPMailer sent with attachment');
        return true;
    }catch (Throwable $e) {
        error_log('MAIL ERROR FULL: ' . $e->getMessage());

        $msg = $e->getMessage();
        set_last_delivery_error($msg);
        log_delivery('email', (string)$employee['name'], $to, false, $msg);

        return false;
}

}

function send_payslip_line(array $employee, array $payroll): bool
{
    if (!is_line_delivery_enabled()) {
        $msg = 'LINE delivery is disabled in config.php (line_enabled=false).';
        set_last_delivery_error($msg);
        log_delivery('line', (string)$employee['name'], '', false, $msg);
        return false;
    }

    $config = app_config();
    $token = trim((string)$config['line_channel_access_token']);
    $lineUserId = trim((string)$employee['line_user_id']);

    if ($lineUserId === '') {
        $msg = 'LINE user id missing.';
        set_last_delivery_error($msg);
        log_delivery('line', (string)$employee['name'], '', false, $msg);
        return false;
    }

    if ($token === '') {
        $msg = 'LINE channel access token not configured. Set config.php line_channel_access_token.';
        set_last_delivery_error($msg);
        log_delivery('line', (string)$employee['name'], $lineUserId, false, $msg);
        return false;
    }

    $file = get_payslip_file_by_payroll((int)$payroll['id']);
    if (!$file || !is_file((string)$file['file_path'])) {
        $error = null;
        $file = generate_encrypted_payslip_pdf((int)$payroll['id'], (int)$payroll['slip_sent_by'] ?: (int)$payroll['created_by'], $error);
        if ($file === null) {
            $msg = $error ?? t('payslip_generate_failed');
            set_last_delivery_error($msg);
            log_delivery('line', (string)$employee['name'], $lineUserId, false, $msg);
            return false;
        }
    }

    $downloadUrl = build_private_download_url((string)$file['download_token']);

    $message = sprintf(
        "Payslip %s/%s\n%s\nDownload: %s",
        $payroll['month'],
        $payroll['year'],
        $employee['name'],
        $downloadUrl
    );

    $payload = [
        'to' => $lineUserId,
        'messages' => [
            [
                'type' => 'text',
                'text' => $message,
            ],
        ],
    ];

    $ch = curl_init((string)$config['line_api_base']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $ok = $response !== false && $httpCode >= 200 && $httpCode < 300;
    if ($ok) {
        set_last_delivery_error('');
        log_delivery('line', (string)$employee['name'], $lineUserId, true, (string)$response);
        return true;
    }

    $details = 'HTTP ' . $httpCode . ' ' . $error;
    if (is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $details = 'HTTP ' . $httpCode . ' ' . json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
    }

    set_last_delivery_error($details);
    log_delivery('line', (string)$employee['name'], $lineUserId, false, $details);

    return false;
}

function send_test_email(string $to, ?string &$error = null): bool
{
    ensure_composer_autoload();

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $error = t('payslip_library_missing');
        return false;
    }

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
        return false;
    }

    $config = app_config();
    $smtpHost = trim((string)$config['smtp_host']);
    if ($smtpHost === '') {
        $error = 'SMTP is not configured. Please set smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password in config.php';
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $smtpUsername = trim((string)$config['smtp_username']);
        $smtpPassword = trim((string)$config['smtp_password']);


        $mail->isSMTP();
        $mail->Host = $smtpHost;

        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 2525;

        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;

        $mail->SMTPDebug = 4;
        $mail->Debugoutput = 'error_log';

        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string)$config['mail_from'], (string)$config['mail_from_name']);
        $mail->addAddress($to);
        $mail->Subject = 'Payroll Test Email';
        $mail->Body = 'This is a test email from Payroll Config Check at ' . date('Y-m-d H:i:s');
        $mail->send();

        return true;
    } catch (Throwable $e) {
        error_log('MAIL ERROR: ' . $e->getMessage());
        $error = $e->getMessage();
        return false;
    }
}

function send_test_line(string $lineUserId, ?string &$error = null): bool
{
    if (!is_line_delivery_enabled()) {
        $error = 'LINE delivery is disabled in config.php (line_enabled=false).';
        return false;
    }

    $config = app_config();
    $token = trim((string)$config['line_channel_access_token']);
    $lineUserId = trim($lineUserId);

    if ($lineUserId === '') {
        $error = 'LINE destination is empty.';
        return false;
    }

    if ($token === '') {
        $error = 'LINE channel access token not configured. Set config.php line_channel_access_token.';
        return false;
    }

    if (!extension_loaded('curl')) {
        $error = 'cURL extension is not enabled.';
        return false;
    }

    $payload = [
        'to' => $lineUserId,
        'messages' => [
            [
                'type' => 'text',
                'text' => 'Payroll test message from Config Check at ' . date('Y-m-d H:i:s'),
            ],
        ],
    ];

    $ch = curl_init((string)$config['line_api_base']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    $error = 'HTTP ' . $httpCode . ' ' . $curlErr;
    if (is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $error = 'HTTP ' . $httpCode . ' ' . json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
    }

    return false;
}

function log_delivery(string $channel, string $employeeName, string $destination, bool $success, string $details): void
{
    $line = sprintf(
        "[%s] channel=%s employee=%s destination=%s success=%s details=%s\n",
        date('Y-m-d H:i:s'),
        $channel,
        $employeeName,
        $destination,
        $success ? 'yes' : 'no',
        str_replace(["\r", "\n"], ' ', $details)
    );

    file_put_contents(__DIR__ . '/storage/logs/delivery.log', $line, FILE_APPEND);
}

function reports_storage_dir(): string
{
    $dir = __DIR__ . '/storage/reports';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function employee_docs_storage_dir(): string
{
    $dir = __DIR__ . '/storage/employee_docs';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    return $dir;
}

function normalize_digits_only(string $value): string
{
    return preg_replace('/[^0-9]/', '', trim($value)) ?? '';
}

function csv_excel_text(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    // Prefix apostrophe so spreadsheet tools keep numeric-like text as plain text.
    return "'" . $trimmed;
}

function generate_social_security_report_csv(int $month, int $year): string
{
    $config = app_config();
    $employerNo = normalize_digits_only((string)($config['sso_employer_no'] ?? ''));
    $branchCode = normalize_digits_only((string)($config['sso_branch_code'] ?? '000000'));
    $branchCode = str_pad(substr($branchCode, 0, 6), 6, '0', STR_PAD_LEFT);
    $beYear = payroll_policy_buddhist_year($year);

    $stmt = db()->prepare('SELECT e.emp_code, e.name, e.national_id, pr.base_salary, pr.social_security_deduction
        FROM payroll_runs pr
        JOIN employees e ON e.id = pr.employee_id
        WHERE pr.month = :month AND pr.year = :year
        ORDER BY e.emp_code ASC');
    $stmt->execute(['month' => $month, 'year' => $year]);
    $rows = $stmt->fetchAll();

    $fileName = sprintf('ssf_1_10_%04d_%02d_%s.csv', $year, $month, date('Ymd_His'));
    $filePath = reports_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create SSF report file');
    }

    fwrite($fh, "\xEF\xBB\xBF");

    // Upload-friendly template columns for SSF 1-10 import.
    fputcsv($fh, [
        'RECORD_TYPE',
        'EMPLOYER_NO',
        'BRANCH_CODE',
        'PAY_MONTH',
        'PAY_YEAR_BE',
        'EMPLOYEE_NATIONAL_ID',
        'EMPLOYEE_CODE',
        'EMPLOYEE_NAME',
        'WAGE_BASE',
        'EMPLOYEE_CONTRIBUTION',
        'EMPLOYER_CONTRIBUTION',
    ]);

    foreach ($rows as $row) {
        $base = (float)$row['base_salary'];
        $employeeContribution = (float)$row['social_security_deduction'];
        $employerContribution = $employeeContribution;
        $nationalId = normalize_digits_only((string)$row['national_id']);

        fputcsv($fh, [
            'D',
            $employerNo,
            $branchCode,
            str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            $beYear,
            csv_excel_text($nationalId),
            $row['emp_code'],
            $row['name'],
            number_format($base, 2, '.', ''),
            number_format($employeeContribution, 2, '.', ''),
            number_format($employerContribution, 2, '.', ''),
        ]);
    }
    fclose($fh);

    return $fileName;
}

function generate_tax_report_csv(int $month, int $year): string
{
    $config = app_config();
    $payerTaxId = normalize_digits_only((string)($config['company_tax_id'] ?? ''));
    $beYear = payroll_policy_buddhist_year($year);

    $stmt = db()->prepare('SELECT e.emp_code, e.name, e.national_id, pr.base_salary, pr.overtime, pr.bonus, pr.withholding_tax
        FROM payroll_runs pr
        JOIN employees e ON e.id = pr.employee_id
        WHERE pr.month = :month AND pr.year = :year
        ORDER BY e.emp_code ASC');
    $stmt->execute(['month' => $month, 'year' => $year]);
    $rows = $stmt->fetchAll();

    $fileName = sprintf('pnd1_%04d_%02d_%s.csv', $year, $month, date('Ymd_His'));
    $filePath = reports_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create tax report file');
    }

    fwrite($fh, "\xEF\xBB\xBF");

    // Upload-friendly template columns for PND.1 import.
    fputcsv($fh, [
        'RECORD_TYPE',
        'PAYER_TAX_ID',
        'TAX_MONTH',
        'TAX_YEAR_BE',
        'SEQUENCE_NO',
        'TAX_ID',
        'PAYEE_CODE',
        'PAYEE_NAME',
        'INCOME_TYPE', //ประเภทเงินได้
        'WAGE_TOTAL',
        'TAX_AMOUNT',
    ]);

    $seq = 0;
    foreach ($rows as $row) {
        $seq++;
        $income = (float)$row['base_salary'] + (float)$row['overtime'] + (float)$row['bonus'];
        $tax = (float)$row['withholding_tax'];
        $payeeTaxId = normalize_digits_only((string)$row['national_id']);
        $incomeType = '40(1)-SALARY';

        fputcsv($fh, [
            'D',
            csv_excel_text($payerTaxId),
            str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            $beYear,
            $seq,
            csv_excel_text($payeeTaxId),
            $row['emp_code'],
            $row['name'],
            $incomeType,
            number_format($income, 2, '.', ''),
            number_format($tax, 2, '.', ''),
        ]);
    }
    fclose($fh);

    return $fileName;
}

function pipe_safe_value(string $value): string
{
    $safe = str_replace(["\r", "\n", '|'], [' ', ' ', ' '], trim($value));
    return preg_replace('/\s+/', ' ', $safe) ?? '';
}

function generate_tax_report_rd_prep_txt(int $month, int $year): string
{
    $config = app_config();
    $payerTaxId = normalize_digits_only((string)($config['company_tax_id'] ?? ''));
    $beYear = payroll_policy_buddhist_year($year);

    $stmt = db()->prepare('SELECT e.emp_code, e.name, e.national_id, pr.base_salary, pr.overtime, pr.bonus, pr.withholding_tax
        FROM payroll_runs pr
        JOIN employees e ON e.id = pr.employee_id
        WHERE pr.month = :month AND pr.year = :year
        ORDER BY e.emp_code ASC');
    $stmt->execute(['month' => $month, 'year' => $year]);
    $rows = $stmt->fetchAll();

    $fileName = sprintf('pnd1_rdprep_%04d_%02d_%s.txt', $year, $month, date('Ymd_His'));
    $filePath = reports_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create tax RD Prep report file');
    }

    fwrite($fh, "RECORD_TYPE|PAYER_TAX_ID|TAX_MONTH|TAX_YEAR_BE|SEQUENCE_NO|TAX_ID|PAYEE_CODE|PAYEE_NAME|INCOME_TYPE|WAGE_TOTAL|TAX_AMOUNT\r\n");

    $seq = 0;
    foreach ($rows as $row) {
        $seq++;
        $income = (float)$row['base_salary'] + (float)$row['overtime'] + (float)$row['bonus'];
        $tax = (float)$row['withholding_tax'];
        $payeeTaxId = normalize_digits_only((string)$row['national_id']);

        $line = implode('|', [
            'D',
            pipe_safe_value($payerTaxId),
            str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            (string)$beYear,
            (string)$seq,
            pipe_safe_value($payeeTaxId),
            pipe_safe_value((string)$row['emp_code']),
            pipe_safe_value((string)$row['name']),
            '40(1)-SALARY',
            number_format($income, 2, '.', ''),
            number_format($tax, 2, '.', ''),
        ]);
        fwrite($fh, $line . "\r\n");
    }

    fclose($fh);
    return $fileName;
}

function create_scheduled_send(int $month, int $year, string $channel, string $sendAt, int $createdBy): void
{
    $stmt = db()->prepare("INSERT INTO scheduled_sends (month, year, channel, sent_at, status, success_count, failed_count, notes, created_by, created_at)
        VALUES (:month, :year, :channel, :sent_at, 'pending', 0, 0, '', :created_by, :created_at)");
    $stmt->execute([
        'month' => $month,
        'year' => $year,
        'channel' => $channel,
        'sent_at' => $sendAt,
        'created_by' => $createdBy,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function process_due_scheduled_sends(): array
{
    $pdo = db();
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT * FROM scheduled_sends WHERE status = 'pending' AND sent_at <= :now ORDER BY sent_at ASC, id ASC");
    $stmt->execute(['now' => $now]);
    $jobs = $stmt->fetchAll();

    $processed = 0;
    foreach ($jobs as $job) {
        $channel = (string)$job['channel'];
        if ($channel === 'line' && !is_line_delivery_enabled()) {
            $updFail = $pdo->prepare('UPDATE scheduled_sends SET status = "failed", notes = :notes, processed_at = :processed_at WHERE id = :id');
            $updFail->execute([
                'notes' => 'LINE disabled in config',
                'processed_at' => $now,
                'id' => (int)$job['id'],
            ]);
            $processed++;
            continue;
        }

        $rowsStmt = $pdo->prepare('SELECT pr.*, e.name, e.email, e.line_user_id
            FROM payroll_runs pr
            JOIN employees e ON e.id = pr.employee_id
            WHERE pr.month = :month
              AND pr.year = :year
              AND pr.status = "paid"
              AND pr.slip_sent_at IS NULL');
        $rowsStmt->execute([
            'month' => (int)$job['month'],
            'year' => (int)$job['year'],
        ]);
        $rows = $rowsStmt->fetchAll();

        $success = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $employee = [
                'name' => $row['name'],
                'email' => $row['email'],
                'line_user_id' => $row['line_user_id'],
            ];

            $ok = $channel === 'line' ? send_payslip_line($employee, $row) : send_payslip_email($employee, $row);
            if ($ok) {
                $updPayroll = $pdo->prepare('UPDATE payroll_runs SET slip_sent_at = :sent_at, slip_channel = :channel, updated_at = :updated_at WHERE id = :id');
                $updPayroll->execute([
                    'sent_at' => date('Y-m-d H:i:s'),
                    'channel' => $channel,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => (int)$row['id'],
                ]);
                $success++;
            } else {
                $failed++;
            }
        }

        $updJob = $pdo->prepare('UPDATE scheduled_sends
            SET status = :status,
                success_count = :success_count,
                failed_count = :failed_count,
                notes = :notes,
                processed_at = :processed_at
            WHERE id = :id');
        $updJob->execute([
            'status' => $failed > 0 ? 'failed' : 'completed',
            'success_count' => $success,
            'failed_count' => $failed,
            'notes' => 'Processed automatically',
            'processed_at' => date('Y-m-d H:i:s'),
            'id' => (int)$job['id'],
        ]);

        $processed++;
    }

    return [
        'processed_jobs' => $processed,
    ];
}

/*สร้างไฟล์ CSV สำหรับ สปส. 6-09 (แจ้งสิ้นสุดความเป็นผู้ประกันตน) โดยดึงพนักงานที่มี end_date ในเดือน/ปีที่ระบุ*/
function generate_sso_6_09_csv(int $month, int $year): string
{
    $config = app_config();
    $employerNo = normalize_digits_only((string)($config['sso_employer_no'] ?? ''));
    $branchCode = normalize_digits_only((string)($config['sso_branch_code'] ?? '000000'));
    $branchCode = str_pad(substr($branchCode, 0, 6), 6, '0', STR_PAD_LEFT);
    $beYear = payroll_policy_buddhist_year($year);

    $stmt = db()->prepare(
        'SELECT e.emp_code, e.name, e.national_id, e.end_date, e.resignation_reason
         FROM employees e
         WHERE e.end_date IS NOT NULL
           AND e.end_date != ""
           AND substr(e.end_date, 1, 7) = :period
         ORDER BY e.emp_code ASC'
    );
    $stmt->execute(['period' => sprintf('%04d-%02d', $year, $month)]);
    $rows = $stmt->fetchAll();

    $fileName = sprintf('sso_6_09_%04d_%02d_%s.csv', $year, $month, date('Ymd_His'));
    $filePath = reports_storage_dir() . DIRECTORY_SEPARATOR . $fileName;

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create SSO 6-09 report file');
    }

    fwrite($fh, "\xEF\xBB\xBF");

    fputcsv($fh, [
        'RECORD_TYPE',
        'EMPLOYER_NO',
        'BRANCH_CODE',
        'TERMINATION_MONTH',
        'TERMINATION_YEAR_BE',
        'EMPLOYEE_NATIONAL_ID',
        'EMPLOYEE_CODE',
        'EMPLOYEE_NAME',
        'TERMINATION_DATE',
        'TERMINATION_REASON',
    ]);

    foreach ($rows as $row) {
        $nationalId = normalize_digits_only((string)$row['national_id']);
        fputcsv($fh, [
            'D',
            $employerNo,
            $branchCode,
            str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            $beYear,
            csv_excel_text($nationalId),
            $row['emp_code'],
            $row['name'],
            (string)$row['end_date'],
            (string)($row['resignation_reason'] ?? ''),
        ]);
    }

    fclose($fh);

    return $fileName;
}
