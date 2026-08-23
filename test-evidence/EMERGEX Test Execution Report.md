# EMERGEX Runtime and Acceptance Test Execution Report

**Project:** EMERGEX — Centralized Student Accommodation & Secure Booking Application  
**Group:** 55  
**Execution date:** 18 August 2026; reporting/recovery regression 21 August 2026  
**Overall result:** **32 of 32 documented acceptance cases passed**

## Verified environment

- Official Apache Friends XAMPP 8.2.12 portable distribution
- PHP 8.2.12 with PDO MySQL, Fileinfo, cURL and session support
- MariaDB 10.4.32
- Local database port: 3307 for this isolated test runtime
- Test URL: `http://127.0.0.1:8080/emergex/public/`
- Browser validation: Codex in-app Chromium browser

The XAMPP runtime is installed in the workspace's `.project_runtime/xampp` folder. Because the parent course folder contains a long name with spaces and parentheses, Apache rejected that non-standard portable path. The acceptance run therefore used XAMPP's PHP 8.2 web server with the same XAMPP MariaDB database. A normal `C:\xampp\htdocs\emergex` deployment remains the documented submission setup.

## Results summary

| Test group | Cases | Result |
|---|---:|---:|
| Installation, registration and login | ST-01–ST-04 | 4/4 PASS |
| Search and property access | ST-05–ST-08 | 4/4 PASS |
| Authorization and CSRF | ST-09–ST-11 | 3/3 PASS |
| Booking and landlord workflow | ST-12–ST-16 | 5/5 PASS |
| Escrow state and transaction atomicity | ST-17–ST-20 | 4/4 PASS |
| Upload and XSS security | ST-21–ST-24 | 4/4 PASS |
| Session controls | ST-25–ST-26 | 2/2 PASS |
| Administrator workflow | ST-27–ST-29 | 3/3 PASS |
| Responsive, keyboard and offline checks | ST-30–ST-32 | 3/3 PASS |
| **Total** | **ST-01–ST-32** | **32/32 PASS** |

## Additional verification

- The supplied structural security suite passed all 7 checks.
- All 21 PHP files passed PHP syntax validation.
- The clean installer created 7 tables and 3 demonstration accounts.
- Prepared statements, password hashing, CSRF enforcement, role authorization, upload MIME inspection, transaction rollback and audit events were exercised at runtime.
- Responsive checks at 360 × 800, 768 × 900 and 1440 × 900 showed no horizontal overflow.
- The mobile navigation menu opened and updated its accessibility state.
- Home and listing pages referenced zero external resources; all local images loaded successfully.
- The administrator-only operational report returned live booking, property and audit data, exposed a print/save-as-PDF control and produced three valid CSV exports.
- The report page had no page-wide overflow at 360 × 800 or 1440 × 900; wide tables scroll only inside their own containers on a phone.
- `BACKUP_EMERGEX.ps1` created a timestamped SQL dump and the guarded `RESTORE_EMERGEX.ps1 -ConfirmRestore` flow restored all 7 tables successfully.

## Defect discovered and corrected

The first acceptance run found that an NSFAS upload was incorrectly subjected to booking lookup before upload processing. This produced `Booking not found` for a valid upload. The student dashboard POST flow was corrected so upload actions are handled independently, while booking lookup remains mandatory for escrow actions. ST-21, ST-22, ST-23 and the full regression suite passed after the fix.

## Reproducing the tests

After installing the application and starting the local database/web server:

1. Run `C:\xampp\php\php.exe tests\security_checks.php`.
2. Set `EMERGEX_BASE_URL` if the application is not at `http://127.0.0.1:8080/emergex/public`.
3. Run `C:\xampp\php\php.exe tests\acceptance_tests.php`.

The acceptance suite deliberately rebuilds the `emergex` database before execution. Back up any demonstration data that must be retained.

## Evidence files

- `01 - Runtime Acceptance Results.json` — detailed ST-01 to ST-29 results, versions, timestamps and evidence.
- `02 - Browser Acceptance Results.json` — detailed ST-30 to ST-32 responsive, keyboard and offline evidence.
- `03 - Information Session Control Results.json` — reporting, CSV, print/PDF, responsive report and backup/restore evidence added after the 20 August information session.
