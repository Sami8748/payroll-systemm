<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = current_user();
$config = app_config();
$currentLocale = app_locale();
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$isLoginPage = $currentPage === 'index.php';

function nav_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active-page' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('app_name')) ?></title>
    <script>
        (function () {
            var storedTheme = localStorage.getItem('payroll_theme') || 'light';
            document.documentElement.setAttribute('data-theme', storedTheme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="page-shell <?= $isLoginPage ? 'login-page' : '' ?>">
<nav class="navbar navbar-expand-lg navbar-light top-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><?= e(t('app_name')) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <div class="d-flex align-items-center gap-2 ms-lg-auto mt-3 mt-lg-0">
                <span id="liveClock" class="top-user-chip" data-locale="<?= e($currentLocale) ?>" title="<?= e(t('current_datetime')) ?>"></span>
                <a class="btn btn-sm lang-switch <?= $currentLocale === 'th' ? 'active' : '' ?>" href="?lang=th"><?= e(t('lang_th')) ?></a>
                <a class="btn btn-sm lang-switch <?= $currentLocale === 'en' ? 'active' : '' ?>" href="?lang=en"><?= e(t('lang_en')) ?></a>
                <button id="themeToggle" type="button" class="btn btn-sm theme-toggle" aria-label="Toggle dark mode" title="Toggle Dark/Light">
                    <i id="themeToggleIcon" class="bi bi-moon-stars-fill"></i>
                </button>
            </div>
            <?php if ($user): ?>
                <div class="top-menu-row ms-lg-3 mt-3 mt-lg-0">
                    <span class="text-white-50 small top-user-chip"><?= e($user['full_name']) ?> (<?= e(role_label($user['role'])) ?>)</span>
                    <a class="btn btn-sm menu-link <?= nav_active('dashboard.php', $currentPage) ?>" href="dashboard.php"><i class="bi bi-grid-1x2-fill me-1"></i><?= e(t('dashboard')) ?></a>
                    <?php if ($user['role'] === 'hr'): ?>
                        <a class="btn btn-sm menu-link <?= nav_active('employees.php', $currentPage) ?>" href="employees.php"><i class="bi bi-people-fill me-1"></i><?= e(t('employees')) ?></a>
                        <a class="btn btn-sm menu-link <?= nav_active('leave.php', $currentPage) ?>" href="leave.php"><i class="bi bi-calendar2-check me-1"></i><?= e(t('leave_management')) ?></a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'ceo'): ?>
                        <!-- CEO role menu: view-only access to dashboard -->
                    <?php endif; ?>
                    <?php if (in_array($user['role'], ['hr', 'accounting'], true)): ?>
                        <a class="btn btn-sm menu-link <?= nav_active('payroll.php', $currentPage) ?>" href="payroll.php"><i class="bi bi-cash-coin me-1"></i><?= e($user['role'] === 'hr' ? t('salary_entry') : t('payment_approval')) ?></a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'accounting'): ?>
                        <a class="btn btn-sm menu-link <?= nav_active('accounting_tools.php', $currentPage) ?>" href="accounting_tools.php"><i class="bi bi-file-earmark-text me-1"></i><?= e(t('accounting_tools')) ?></a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin_it'): ?>
                        <a class="btn btn-sm menu-link <?= nav_active('user_admin.php', $currentPage) ?>" href="user_admin.php"><i class="bi bi-person-gear me-1"></i><?= e(t('user_admin')) ?></a>
                        <a class="btn btn-sm menu-link <?= nav_active('system_logs.php', $currentPage) ?>" href="system_logs.php"><i class="bi bi-journal-text me-1"></i><?= e(t('system_logs')) ?></a>
                        <a class="btn btn-sm menu-link <?= nav_active('config_check.php', $currentPage) ?>" href="config_check.php"><i class="bi bi-check2-square me-1"></i><?= e(t('config_check')) ?></a>
                    <?php endif; ?>
                    <a class="btn btn-sm menu-link <?= nav_active('change_password.php', $currentPage) ?>" href="change_password.php"><i class="bi bi-key-fill me-1"></i><?= e(t('change_password')) ?></a>
                    <a class="btn btn-danger btn-sm logout-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i><?= e(t('logout')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="<?= $isLoginPage ? 'container-fluid px-3 px-lg-4 py-4' : 'container py-4' ?>">
<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>
