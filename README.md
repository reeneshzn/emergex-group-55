# EMERGEX — Group 55 Completed Project

This folder contains the complete project package for **EMERGEX: Centralized Student Accommodation & Secure Booking Application**, prepared against the IT Project 700 Semester 2 (2026) guidelines.

## Deliverables

- `documentation/` — five phase submissions, a 53-page integrated report in Word and PDF, and 19 original system/project diagrams.
- `application/` — a PHP/MySQL prototype with student, landlord and administrator workflows, an operational report centre, database schema and seed data, installation instructions, and a 32-case test register.
- `presentation/` — a 16-slide supporting deck and a timed 15-minute live-demonstration runbook.
- `administration/` — the completed Group 55 list workbook, information-session compliance review, meeting controls, supervisor approval checklist and MS Project-compatible schedule.
- `test-evidence/` — the signed-off 32-case runtime/browser execution results and test report.
- `runtime-tools/` — convenience scripts for starting/stopping the installed runtime and backing up/restoring the EMERGEX database.

## Application quick start

1. Install XAMPP with PHP 8.1 or later and MySQL/MariaDB.
2. Copy the `application` folder into `C:\xampp\htdocs\emergex`.
3. Start Apache and MySQL.
4. Open `http://localhost/emergex/database/install.php` once to create and seed the database.
5. Open `http://localhost/emergex/public/`.

Demo credentials and detailed installation/troubleshooting steps are in `application/README.md`.

For the runtime installed in this workspace, double-click `runtime-tools/START EMERGEX.cmd`. It starts the application and opens `http://127.0.0.1:8080/emergex/public/` automatically. Double-click `runtime-tools/STOP EMERGEX.cmd` when finished. These launchers work even when Windows has no `.ps1` file association. Use `runtime-tools/BACKUP_EMERGEX.ps1` to create a demonstration backup.

## Final checks completed

- All supplied source files and the official guideline were reviewed.
- All 105 generated document pages were rendered and visually inspected; the updated 12-page Phase 5 report and 53-page integrated report were rerendered after the information-session improvements.
- The presentation passed automated overflow testing and all 16 slides were visually inspected.
- The group-list workbook was rendered and visually inspected.
- The live XAMPP-based runtime passed all 32 documented acceptance cases, all 7 structural security checks and syntax validation for all 21 PHP files.
- Runtime testing identified and corrected an upload-routing defect; the complete regression run then passed 32/32.
- The 20 August 2026 IT Project 600/700 information session and transcript were reviewed. Reporting, backup retrieval, MS Project scheduling and presentation-control evidence were strengthened in response.

## Actions for Group 55 before submission

1. Confirm that **Tristan Moses** is the elected group leader; this package records him as the provisional leader because he was first in the supplied group list.
2. Replace the workbook's WhatsApp placeholder with the real group invitation link.
3. Review the dated evidence in `test-evidence/` and rerun the supplied acceptance suite after any code change.
4. Review personal details, lecturer/supervisor details, dates, and institutional submission rules.
5. Record genuine meeting minutes and obtain the supervisor's approval before each Moodle submission.
6. Copy the verified system to at least one backup laptop and rehearse the 15-minute live demonstration using `presentation/EMERGEX Live Demonstration Runbook.md`.
7. Ensure every member can explain the six representative code modules, database relationships, security controls, reporting and backup process.

## Recommended submission order

1. Final integrated report PDF
2. Five phase documents
3. Working application and database files
4. Final presentation
5. Completed group-list workbook
