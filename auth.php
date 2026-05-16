<?php

declare(strict_types=1);


require_once __DIR__ . '/db.php';

function password_policy_config(): array /*กฎการตั้งรหัสผ่านที่กำหนดไว้ในระบบ โดยดึงค่าจากไฟล์ config.php และกำหนดค่าเริ่มต้นหากไม่มีการตั้งค่าในไฟล์ config.php เพื่อให้ระบบสามารถตรวจสอบและบังคับใช้กฎการตั้งรหัสผ่านได้อย่างถูกต้องและปลอดภัย*/
{
    $config = require __DIR__ . '/config.php'; 

    return [
        'min_length' => max((int)($config['password_min_length'] ?? 6), 6), /*ความยาวขั้นต่ำของรหัสผ่านที่กำหนดไว้ในระบบ โดยดึงค่าจากไฟล์ config.php และกำหนดค่าเริ่มต้นเป็น 6 หากไม่มีการตั้งค่าในไฟล์ config.php */
        'max_age_days' => max((int)($config['password_max_age_days'] ?? 90), 1), /*อายุของรหัสผ่าน โดยดึงค่าจากไฟล์ config.php และกำหนดค่าเริ่มต้นเป็น 90 หากไม่มีการตั้งค่าในไฟล์ config.php */
    ];
}


function password_meets_policy(string $password): bool /*ฟังก์ชันนี้ใช้ตรวจสอบว่ารหัสผ่านที่ผู้ใช้ป้อนตรงตามนโยบายการตั้งรหัสผ่านที่กำหนดไว้หรือไม่ โดยใช้กฎจากฟังก์ชัน password_policy_config */
{
    $policy = password_policy_config();
    if (strlen($password) < $policy['min_length']) { /*ตรวจสอบว่าความยาวของรหัสผ่านที่ผู้ใช้ป้อนน้อยกว่าความยาวขั้นต่ำที่กำหนดไว้ในนโยบายการตั้งรหัสผ่านหรือไม่ หากน้อยกว่าก็จะคืนค่า false เพื่อบอกว่าไม่ตรงตามนโยบาย*/
        return false;
    }

    return (bool)(
        preg_match('/[a-z]/', $password) /*ตรวจสอบว่ารหัสผ่านมีตัวอักษรพิมพ์เล็กอย่างน้อยหนึ่งตัวหรือไม่ โดยใช้ regular expression ที่ตรวจสอบการมีอยู่ของตัวอักษรพิมพ์เล็กในรหัสผ่าน*/
        && preg_match('/[A-Z]/', $password) /*ตรวจสอบว่ารหัสผ่านมีตัวอักษรพิมพ์ใหญ่อย่างน้อยหนึ่งตัวหรือไม่ โดยใช้ regular expression ที่ตรวจสอบการมีอยู่ของตัวอักษรพิมพ์ใหญ่ในรหัสผ่าน*/
        && preg_match('/\d/', $password) /*ตรวจสอบว่ารหัสผ่านมีตัวเลขอย่างน้อยหนึ่งตัวหรือไม่ โดยใช้ regular expression ที่ตรวจสอบการมีอยู่ของตัวเลขในรหัสผ่าน*/
        && preg_match('/[^a-zA-Z\d]/', $password) /*ตรวจสอบว่ารหัสผ่านมีอักขระพิเศษอย่างน้อยหนึ่งตัวหรือไม่ โดยใช้ regular expression ที่ตรวจสอบการมีอยู่ของอักขระพิเศษในรหัสผ่าน*/
    );
    /*ระบบ Payroll ของเราไม่รับรหัสผ่านง่ายๆ อย่าง '123456' หรือ 'password' เด็ดขาด ใครจะเปลี่ยนรหัสผ่าน ต้องตั้งให้ยาวเกิน 6 ตัว และต้องผสมทั้งตัวเล็ก ตัวใหญ่ ตัวเลข และสัญลักษณ์พิเศษให้ครบ ถึงจะยอมให้บันทึกลงฐานข้อมูลได้ */
}

/*ฟังก์ชันนี้ใช้ตรวจสอบว่ารหัสผ่านของผู้ใช้หมดอายุหรือไม่ โดยเปรียบเทียบวันที่เปลี่ยนรหัสผ่านล่าสุดกับวันที่ปัจจุบันและอายุสูงสุดที่กำหนดไว้ในนโยบายการตั้งรหัสผ่าน หากรหัสผ่านหมดอายุจะคืนค่า true เพื่อบอกว่าผู้ใช้ต้องเปลี่ยนรหัสผ่านใหม่*/
function password_is_expired(?string $changedAt, ?string $createdAt = null): bool
{
    $policy = password_policy_config(); /*ดึงกฎการตั้งรหัสผ่านจากฟังก์ชัน password_policy_config เพื่อใช้ในการตรวจสอบอายุของรหัสผ่านว่าหมดอายุหรือไม่ โดยใช้ค่า max_age_days ที่กำหนดไว้ในนโยบายการตั้งรหัสผ่าน*/
    $base = trim((string)$changedAt) !== '' ? (string)$changedAt : (string)$createdAt;
    if (trim($base) === '') { 
        return true;
    }

    $changed = strtotime($base);
    if ($changed === false) {
        return true;
    }

    return $changed <= strtotime('-' . $policy['max_age_days'] . ' days');
}

function must_change_password(): bool /*ฟังก์ชันนี้ใช้ตรวจสอบว่าผู้ใช้ต้องเปลี่ยนรหัสผ่านใหม่หรือไม่ โดยตรวจสอบว่าผู้ใช้ปัจจุบันมีรหัสผ่านหมดอายุหรือไม่ หากรหัสผ่านหมดอายุจะคืนค่า true เพื่อบอกว่าผู้ใช้ต้องเปลี่ยนรหัสผ่านใหม่*/
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return (bool)($user['password_expired'] ?? false);
}


function boot_session(): void /*จะเริ่มทำงานเพื่อดูว่าคนนี้มีบัตรคล้องคอหรือยัง ถ้ายังไม่มี ก็จะไปเอาบัตรคล้องคอที่ config.php มาให้ แล้วก็เริ่มต้น session เพื่อให้ระบบสามารถเก็บข้อมูลผู้ใช้และสถานะการเข้าสู่ระบบได้อย่างถูกต้องและปลอดภัย*/
{
    $config = require __DIR__ . '/config.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_name($config['session_name']);
        session_start();
    }
}

/*ฟังก์ชันนี้ใช้ตรวจสอบว่าผู้ใช้ปัจจุบันเข้าสู่ระบบแล้วหรือไม่ โดยเรียกใช้ฟังก์ชัน boot_session เพื่อเริ่มต้น session และตรวจสอบว่ามีข้อมูลผู้ใช้ใน session หรือไม่ หากมีข้อมูลผู้ใช้จะคืนค่าข้อมูลผู้ใช้นั้นเป็นอาร์เรย์ หากไม่มีจะคืนค่า null เพื่อบอกว่าไม่มีผู้ใช้เข้าสู่ระบบ*/
function current_user(): ?array
{
    boot_session();
    return $_SESSION['user'] ?? null;
}

/*ฟังก์ชันนี้ใช้ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่ โดยเรียกใช้ฟังก์ชัน current_user เพื่อตรวจสอบว่ามีข้อมูลผู้ใช้ใน session หรือไม่ หากมีข้อมูลผู้ใช้จะคืนค่า true เพื่อบอกว่าผู้ใช้เข้าสู่ระบบแล้ว หากไม่มีจะคืนค่า false เพื่อบอกว่ายังไม่มีผู้ใช้เข้าสู่ระบบ*/
function is_logged_in(): bool
{
    return current_user() !== null;
}

function login(string $username, string $password): bool /*ฟังก์ชันนี้ใช้สำหรับเข้าสู่ระบบ โดยตรวจสอบชื่อผู้ใช้และรหัสผ่าน หากถูกต้องจะเก็บข้อมูลผู้ใช้ใน session และคืนค่า true หากไม่ถูกต้องจะคืนค่า false*/
{
    $stmt = db()->prepare('SELECT id, username, password_hash, role, full_name, is_active, password_changed_at, created_at FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_active'] !== 1) { /*ระบบจะตรวจสอบ ถ้าพนักงานโดนไล่ออกจากบริษัทแล้ว (is_active = 0) ซึ่ง!== 1 แปลว่า "มีค่าไม่เท่ากับเลข 1 อย่างเด็ดขาด ก็จะไม่ยอมให้เข้าสู่ระบบได้อีกต่อไป */
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) { /*เอาพาสเวิร์ดมาถอดรหัสว่าตรงกับรหัสผ่านที่ถูกแฮชไว้ในฐานข้อมูลหรือไม่ ถ้าไม่ตรงกันก็จะไม่ยอมให้เข้าสู่ระบบได้*/
        return false;
    }

    /*ถ้าผ่านการตรวจสอบทุกอย่างแล้ว ก็จะเริ่มต้น session และเก็บข้อมูลผู้ใช้ใน session เพื่อให้ระบบสามารถรู้ได้ว่าผู้ใช้คนนี้คือใคร และมีสิทธิ์อะไรบ้างในการใช้งานระบบต่อไป*/
    boot_session();
    session_regenerate_id(true);

    $_SESSION['user'] = [ 
        'id' => (int)$user['id'],/*เก็บข้อมูล Idของผู้ใช้ เพื่อระบุตัวตนของผู้ใช้ในระบบ*/
        'username' => $user['username'], /*เก็บข้อมูลชื่อผู้ใช้ เพื่อให้ระบบแสดงชื่อผุ้ใช้อ่างถูกต้อง*/
        'role' => $user['role'], /*เก้บข้อมูลบทบาทของผู้ใช้ เพื่อตรวจสอบสิทธิ์ในการเข้าถึงฟังก์ชันต่างๆ ในระบบ*/
        'full_name' => $user['full_name'], /*เก็บข้อมุลชื่อเต็มของผู้ใช้*/
        'password_expired' => password_is_expired((string)($user['password_changed_at'] ?? ''), (string)($user['created_at'] ?? '')), /*ตรวจสอบว่ารหัสผ่านของผู้ใช้หมดอายุหรือไม่*/
    ];

    /*เมื่อผู้ใช้เข้าสู่ระบบสำเร็จแล้ว ระบบจะบันทึกประวัติการเข้าสู่ระบบลงในตาราง audit_logs เพื่อให้สามารถตรวจสอบและติดตามกิจกรรมของผู้ใช้ในระบบได้อย่างมีประสิทธิภาพและปลอดภัย*/
    return true;
}

/*ฟังก์ชันนี้ใช้สำหรับออกจากระบบ โดยล้างข้อมูลผู้ใช้จาก session และทำลาย session เพื่อให้ผู้ใช้ไม่สามารถเข้าถึงฟังก์ชันหรือหน้าต่างๆ ที่ต้องการการยืนยันตัวตนได้อีกต่อไป*/
function logout(): void
{
    boot_session();/*เริ่มต้น session เพื่อให้สามารถเข้าถึงข้อมูล session ได้อย่างถูกต้องและปลอดภัย*/
    $_SESSION = [];

    if (ini_get('session.use_cookies')) { 
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy(); /*ทำลาย session เพื่อให้ผู้ใช้ไม่สามารถเข้าถึงข้อมูล session ได้อีกต่อไป และป้องกันการใช้งาน session ที่ไม่ถูกต้องหรือเป็นอันตราย*/
}


function require_login(): void /*ถ้าไม่มีบัตรคล้องคอ (ยังไม่ได้เข้าสู่ระบบ) ก็จะถูกส่งกลับไปที่หน้า index.php ซึ่งเป็นหน้าล็อกอิน เพื่อให้ผู้ใช้เข้าสู่ระบบก่อนที่จะเข้าถึงฟังก์ชันหรือหน้าต่างๆ ที่ต้องการการยืนยันตัวตน*/
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }

    /*ถ้าระบบเห็นว่า "รหัสผ่านคุณหมดอายุแล้ว!" รปภ. จะล็อคตัวคุณไว้เลย คุณจะกดไปหน้าไหนไม่ได้ทั้งนั้น ยกเว้นหน้า change_password.php หน้าเดียว*/
    $currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    $allowedWhenExpired = ['change_password.php', 'logout.php', 'index.php'];
    if (must_change_password() && !in_array($currentPage, $allowedWhenExpired, true)) {
        header('Location: change_password.php');
        exit;
    }
}

/*ฟังก์ชันนี้ใช้ตรวจสอบว่าผู้ใช้มีบทบาทที่อนุญาตให้เข้าถึงฟังก์ชันหรือหน้าต่างๆ ที่ต้องการการยืนยันตัวตนหรือไม่ โดยเรียกใช้ฟังก์ชัน require_login เพื่อตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่ และตรวจสอบบทบาทของผู้ใช้กับบทบาทที่อนุญาต หากผู้ใช้ไม่มีสิทธิ์เข้าถึงจะส่งกลับข้อความ 403 Forbidden และหยุดการทำงานของสคริปต์*/
function require_role(array $allowedRoles): void
{
    require_login();
    $user = current_user();

    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        $lang = $_SESSION['lang'] ?? 'th';
        $forbiddenText = $lang === 'en' ? '403 Forbidden' : '403 ไม่มีสิทธิ์เข้าถึง';
        http_response_code(403);
        echo $forbiddenText;
        exit;
    }
}
/*การปกป้องข้อมูลเงินเดือนครับ มันจัดการตั้งแต่ตอนพิมพ์รหัสผ่าน ตรวจสอบสถานะการทำงาน บังคับเปลี่ยนรหัสทุก 3 เดือน และคอยเฝ้ายามหน้าห้องทุกห้องในระบบ*/
