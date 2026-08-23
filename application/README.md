# EMERGEX - Centralized Student Accommodation and Secure Booking Application

EMERGEX is a complete academic LAMP prototype for Group 55. It demonstrates a secure student-accommodation workflow from verified property search through booking, a simulated deposit hold and confirmed check-in.

## What is included

- Responsive student-facing property search and detail pages
- Student, landlord and administrator account roles
- Standard booking requests with a 12-hour response deadline
- Instant Book for approved properties
- Escrow status simulator (no payment gateway and no real funds)
- Student booking dashboard and check-in confirmation
- Landlord request dashboard and accept/decline controls
- Administrator verification, document review and audit dashboard
- Administrator operational report centre with printable/PDF-ready output and booking, property and audit CSV exports
- NSFAS evidence upload with MIME, size and filename controls
- MySQL schema, constraints, indexes, seed data and CLI installer
- Static security checks and a documented manual/system test suite

## Requirements

- Windows with XAMPP (Apache, PHP 8.1+ and MySQL/MariaDB)
- PHP extensions: PDO, PDO MySQL and Fileinfo
- A modern browser

## Installation on XAMPP

For the portable runtime included beside the completed project, double-click `runtime-tools/START EMERGEX.cmd`; double-click `runtime-tools/STOP EMERGEX.cmd` when finished. No PowerShell file association is required.

1. Copy the `application` folder to `C:\xampp\htdocs\emergex`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Review `app/config/config.php`. The included values match a standard local XAMPP installation.
4. Open Command Prompt in `C:\xampp\htdocs\emergex`.
5. Run `C:\xampp\php\php.exe database\install.php`.
6. Browse to `http://localhost/emergex/public/`.

If the folder name or web path changes, update `base_url` in `app/config/config.php`.

## Demonstration accounts

| Role | Email | Password |
|---|---|---|
| Student | `student@emergex.test` | `Student@55` |
| Landlord | `landlord@emergex.test` | `Landlord@55` |
| Administrator | `admin@emergex.test` | `Admin@55Secure` |

These accounts are for local assessment only. Do not reuse the passwords or expose this seeded database publicly.

## Demonstration flow

1. Sign in as the student and browse the verified listings.
2. Open an Instant Book property, choose a move-in date and confirm the booking.
3. On the student dashboard, select **Simulate deposit hold**.
4. Select **Confirm check-in & release** to complete the escrow lifecycle.
5. Sign in as the landlord to review the non-instant seeded request.
6. Sign in as the administrator to inspect verification queues and audit events.
7. Open **Reports**, download a CSV and use **Print / Save as PDF** to generate assessment evidence.

## Security design

- PDO prepared statements with native MySQL prepares
- `password_hash()` and `password_verify()` for credentials
- Server-side role and ownership checks
- CSRF tokens verified with constant-time comparison
- Session ID rotation on login and secure cookie attributes
- Contextual HTML escaping
- Content Security Policy and anti-framing headers
- Upload allow-listing, MIME inspection, 5 MB limit and random stored names
- InnoDB foreign keys, checks, transactions and audit trails
- Generic login errors and basic session-based throttling

## Important project boundary

The escrow feature is deliberately a simulator. A production system must use a regulated payment service provider and obtain legal, security, privacy and financial-compliance review. Real identity or NSFAS documents must not be uploaded to the academic prototype.

## Running the supplied checks

After installing PHP, run:

`C:\xampp\php\php.exe tests\security_checks.php`

Then run the complete repeatable acceptance suite:

`C:\xampp\php\php.exe tests\acceptance_tests.php`

The suite rebuilds the `emergex` database and executes ST-01 to ST-29. Responsive, keyboard and offline browser evidence for ST-30 to ST-32 is included in the delivered `test-evidence` folder. The verified 18 August 2026 regression result is 32/32 PASS.

## Resetting the demo

Running `database\install.php` again drops and recreates the EMERGEX tables and restores the seeded demonstration data. Back up any data you wish to keep before rerunning the installer.

From the completed project folder, `runtime-tools\BACKUP_EMERGEX.ps1` creates a timestamped SQL backup. A restore is deliberately guarded and requires `runtime-tools\RESTORE_EMERGEX.ps1 -BackupFile <file.sql> -ConfirmRestore`.
