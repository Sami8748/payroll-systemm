# Payroll System (Internal Only)

A lightweight Payroll web application for internal use only.

## Roles and Access Policy

- IT Admin
  - Manages internal users (IT/HR/Accounting)
  - Resets passwords and enables/disables accounts
- HR
  - Creates employee payroll records
  - Can view dashboard totals
  - Cannot access manager payroll data
  - Can send payslips via Email (and LINE if enabled)
- Accounting
  - Marks payroll as paid
  - Can send payslips via Email (and LINE if enabled)

## Security Scope

- No employee self-service account exists in this system.
- Payroll access is restricted to internal roles only.
- Payslips are delivered via Email from HR/Accounting.
- LINE delivery is optional and can be disabled in configuration.

## Tech Stack

- PHP (no framework)
- SQLite (`storage/payroll.sqlite`)
- Bootstrap 5 (CDN)
- PHPMailer (Email with attachment)
- TCPDF (Encrypted PDF payslip)

## Quick Start (XAMPP)

1. Put project in `c:\xampp\htdocs\New3`
2. Open browser:
   - `http://localhost/New3/setup.php` (run once)
   - `http://localhost/New3/`

## Install Dependencies (required for payslip workflow)

1. Open terminal in project folder
2. Run:
  - `composer install`

## Default Users (after setup)

- `itadmin / IT@1234`
- `hr01 / HR@1234`
- `acc01 / ACC@1234`

Change all default passwords immediately in User Admin.

## Payslip Workflow (Manual Trigger)

1. Accounting clicks `Pay`
2. System auto-generates encrypted PDF payslip (password = employee national ID)
3. HR/Accounting can click `Generate PDF` manually to regenerate
4. HR/Accounting can send with:
  - Email: PHPMailer + PDF attachment
  - LINE (optional): LINE Messaging API push with private download link

## LINE / Email Notes

- Set SMTP in `config.php`: `smtp_host`, `smtp_port`, `smtp_secure`, `smtp_username`, `smtp_password`
- Use Email-only mode by setting `line_enabled` to `false` in `config.php`
- If using LINE, set LINE Bot token in `config.php`: `line_channel_access_token`
- Set app URL in `config.php`: `app_url` for private download link
- Delivery logs are written to `storage/logs/delivery.log`.

## Main Files

- `index.php` login page
- `dashboard.php` summary dashboard
- `employees.php` employee management (HR)
- `payroll.php` payroll create/pay/generate PDF/send slip
- `user_admin.php` IT user management
- `system_logs.php` IT technical logs (finance-blind)
- `download_payslip.php` private payslip download endpoint
- `setup.php` database initialization and seed data
