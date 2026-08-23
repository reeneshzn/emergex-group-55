# EMERGEX live demonstration runbook

This sequence follows the 20 August 2026 IT Project 600/700 information-session guidance: demonstrate the working system rather than the five phase documents, use about 15 minutes for the walkthrough, retain the remaining panel time for questions, and ensure every team member can explain the full project.

## Before the panel joins

1. Run `runtime-tools/START_EMERGEX.ps1` on at least two team laptops.
2. Confirm the home page, database connection and all three demo accounts.
3. Run `runtime-tools/BACKUP_EMERGEX.ps1` and keep the resulting SQL file visible.
4. Open the application, administrator report centre, database viewer, source modules and the backup folder.
5. Keep the presentation and final report open only as supporting evidence.

## Fifteen-minute walkthrough

| Time | Lead | Demonstration and evidence |
|---|---|---|
| 00:00-00:45 | Tristan | Introduce the housing problem, the EMERGEX objective and the no-real-money boundary. |
| 00:45-02:15 | Thingo | Show responsive public discovery, search filters, verified listing evidence and clear form labels. |
| 02:15-04:30 | Tristan | Sign in as the student; create an Instant Book and explain the visible booking state. |
| 04:30-06:15 | Reenesh | Demonstrate the simulated deposit hold and check-in release; explain CSRF, sessions, ownership and transactions. |
| 06:15-08:00 | Novithan | Show the seven-table database, foreign keys, the new booking/escrow rows and the audit record. |
| 08:00-09:30 | Joyce | Open the administrator report centre; generate the booking/property report, download CSV and show Print / Save as PDF. |
| 09:30-11:00 | Lungelo | Show the 32/32 test evidence, security checks and the corrected upload regression. |
| 11:00-12:15 | Lungelo | Run a fresh database backup and identify the restore command and backup location. |
| 12:15-13:30 | Joyce | Explain business value, report usefulness, scope limits and production dependencies. |
| 13:30-15:00 | All members | Each member states one owned contribution and one control they can defend; Tristan hands over to questions. |

## Six code modules every member must understand

1. `app/includes/bootstrap.php` - configuration, PDO, sessions, CSRF, authorization, escaping and audit helpers.
2. `public/login.php` and `public/register.php` - password hashing/verification, session rotation, duplicate handling and role routing.
3. `public/properties.php` and `public/property.php` - prepared search filters, publication rules and listing evidence.
4. `public/book.php` and `public/dashboard.php` - booking creation, escrow state transitions, upload validation and transactional rollback.
5. `public/landlord.php` and `public/admin.php` - ownership enforcement, decisions, verification and audit recording.
6. `public/reports.php` - role-protected operational summaries, CSV exports and printable/PDF-ready reporting.

## Questions every member should be ready to answer

- Why was the hybrid Waterfall-Agile SDLC selected?
- How do the seven tables preserve ownership and transactional consistency?
- How are passwords, sessions, CSRF tokens, uploads and role permissions secured?
- Why is escrow simulated, and what would be required for production payment handling?
- How do the 32 tests prove the critical user journeys and negative security cases?
- How is the system backed up and restored?
- How does the report centre help administrators and auditors?

