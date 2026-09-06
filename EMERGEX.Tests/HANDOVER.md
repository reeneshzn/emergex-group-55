# EMERGEX test suite — handover summary

Paste this into a new chat to resume where we left off.

---

## Project

Automated test suite for **EMERGEX**, a student accommodation prototype.

- App: plain PHP + MySQL, running under XAMPP
  at `C:\xampp\htdocs\emergex-group-55\application\public`
- Tests: C# / NUnit / Playwright for .NET (net9.0)
  at `C:\xampp\htdocs\emergex-group-55\EMERGEX.Tests`
- Database: MySQL `emergex` on 127.0.0.1:3306, user `root`, no password

## What was built

A ~95-test suite across five layers, replacing 24 placeholder stub files that
had been written against selectors which do not exist in the app. All selectors
were read from the actual PHP source, so they match the real markup.

```
Support/
  TestConfig.cs      base URL, demo credentials, seeded-data constants
  BaseTest.cs        fresh browser context per test, sign-in/out helpers
  Database.cs        MySqlConnector access for the integration layer
  TestFiles.cs       in-memory upload fixtures (PDF, PNG, disguised PHP shell)
  Pages/
    PropertiesPage.cs
    BookingPages.cs  booking, dashboards (student/landlord/admin), detail page
UITests/PresentationTests.cs                    25 tests   [Category("UI")]
FunctionalTests/AuthenticationTests.cs          15 tests   [Category("Functional")]
FunctionalTests/PropertySearchTests.cs          12 tests
FunctionalTests/BookingAndUploadTests.cs         7 tests
IntegrationTests/PersistenceTests.cs             9 tests   [Category("Integration")]
End-to-EndTests/JourneyTests.cs                  5 tests   [Category("E2E")]
RegressionTests/CriticalPathRegressionTests.cs  22 tests   [Category("Regression")]
RegressionTests/DataIntegrityTests.cs            2 tests   [Category("Regression")]
SecurityTests/SecurityTests.cs                  13 tests   [Category("Security")]
TestCases/test_cases_v2.json    the 36 test cases, for import into the tool
TestCases/TEST_CASES.md         readable version of the same
TRACEABILITY.md    case -> test mapping (NEEDS REBUILDING for the v2 IDs)
.runsettings       Playwright + NUnit config, trx and html reporters
README.md          setup, run commands, conventions, coverage gaps
```

Ten tests in the Functional, Integration and Regression suites also carry
`[Category("Security")]`, so a Security filter returns the full security picture
without duplicating any test.

All selectors live in page objects only — no selector appears in a test.
Tests use web-first assertions (`Expect(...)`), no `Thread.Sleep`.
Everything created is cleaned up in `finally` blocks or `[TearDown]`.

## Demo accounts (from `application/database/install.php`)

- student@emergex.test / Student@55
- landlord@emergex.test / Landlord@55
- admin@emergex.test / Admin@55Secure

## Seeded properties (from `seed.sql`)

- Emerald House Hatfield — instant book, NSFAS, R5 200
- Braamfontein Studio Living — request flow, NSFAS, R6 100
- Durban North Student Lofts — instant book, no NSFAS, R4 550
- Mowbray Campus Rooms — request flow, NSFAS, R4 800

4 published, 3 NSFAS-accredited, 2 instant-book, 2 under R5 000.

## Environment gotchas already solved

1. `base_url` in `config.php` pointed at `/emergex/public`, which does not
   exist. Fixed to `/emergex-group-55/application/public`, and
   `TestConfig.BaseUrl` now defaults to the matching absolute URL, so no
   environment variable is required.
2. `php` is not on PATH — use `C:\xampp\php\php.exe` in full.
   `install.php` is CLI-only (it 403s in a browser) and is safe to re-run,
   since `schema.sql` drops all tables first.
3. `pwsh` is not installed (Windows PowerShell 5.1 only) — install the
   Playwright browser with:
   `powershell -ExecutionPolicy Bypass -File bin\Debug\net9.0\playwright.ps1 install chromium`
4. The 24 old stub files were excluded via `<Compile Remove>` in the csproj and
   should be deleted; that ItemGroup can then be removed.

## Where this fits in the IT project

The project runs in five phases. This testing work is **Phase 5 —
Implementation**, due **9 November 2026**:

- **5.3 Testing** — strategy, the six layers, the framework, how tests are run
- **5.4 System Testing** — the 36 cases, traceability matrix, execution
  results, defects found

Phase 1 is submitted. Phase 2 (Planning, sections 2.1–2.7) is due
**18 September 2026** and is the nearer deadline.

Two findings also feed earlier phases: DEF-010 belongs in **3.4 Data Analysis
(Data Integrity & Constraints)**, and DEF-011 in **4.8 Security backup design
(Software concern)**.

## Progress so far

- [x] Suite written; builds clean
- [x] Chromium installed via Playwright
- [x] Demo database installed; Apache and MySQL running
- [x] UI layer ran 25 / 25 green — **but the run was not saved to a file**, so
      it does not count as evidence and will be repeated
- [x] 36 test cases written (v2), replacing the original 30
- [x] Security layer added: 13 new tests plus 10 existing ones re-tagged
- [x] Old stub files deleted; csproj cleaned
- [x] TC-30 (price tampering) and DEF-010 (room capacity) tests written
- [x] **Clean recorded run complete — 6 Sept 2026, 118 tests, 114 passed,
      4 failed, 46.5s.** All four failures are documented defects.
      Artefacts in `TestResults/` (`.trx` + HTML report)
- [x] `TRACEABILITY.md` rebuilt: 36 of 36 cases automated, with per-case results
      and the defect register
- [ ] **NEXT STEP: the Word report for sections 5.3 and 5.4**

## The clean recorded run

Nothing has been saved to disk yet. Visual Studio's Test Explorer keeps results
in memory only and largely ignores the `<LoggerRunSettings>` block, so the run
that produces report evidence must go through the command line once.

```powershell
# 1. Apache and MySQL running in the XAMPP Control Panel.

# 2. Reset the database so every number comes from a known state.
C:\xampp\php\php.exe C:\xampp\htdocs\emergex-group-55\application\database\install.php

# 3. One full recorded run.
cd C:\xampp\htdocs\emergex-group-55\EMERGEX.Tests
dotnet test --settings .runsettings
```

Writes to `EMERGEX.Tests\TestResults\`:

- `*.trx` — every test with pass/fail, duration and error message
- `emergex-test-report.html` — browsable report, screenshot-friendly

Use Test Explorer for fixing individual failures; use the command above for the
run that goes in the report.

## Expected failures (deliberate)

Four tests assert correct behaviour the build does not implement. They are
findings, not broken tests, and must stay red until the code is fixed:

| Test | Defect |
|---|---|
| `Booking_OnAPropertyWithNoRoomsAvailable_IsRefused` | DEF-010 |
| `UploadedDocuments_AreNotReadableWithoutASession` | DEF-011 |
| `LoginForm_WithoutCsrfToken_ReturnsACleanRejectionStatus` | DEF-012 |
| `RegistrationForm_HidesStudentNumberForLandlords` | DEF-013 |

Anything else red is a genuine bug in the test code and should be fixed.

## Test cases: v1 replaced by v2

The original 30 cases contained nine that described features the build does not
have (listing creation, hide listing, fully-booked overlay, payment loading UI,
webhook sync, payment decline, reject-landlord, profile edit, missing rent
price). They were replaced.

`TestCases/test_cases_v2.json` holds **36 cases, TC-01 to TC-36**, all
automatable:

| Layer | Cases | IDs |
|---|---|---|
| UI | 6 | TC-01 – TC-06 |
| Functional | 13 | TC-07 – TC-19 |
| Integration | 5 | TC-20 – TC-24 |
| End-to-end | 3 | TC-25 – TC-27 |
| Regression | 3 | TC-28 – TC-30 |
| Security | 6 | TC-31 – TC-36 |

The JSON keeps the tool's original fields (`title`, `description`, `tags`,
`date`) and adds `id`, `layer`, `priority`, `status`, `preconditions`, `steps`
and `expected_result`.

Note: 36 cases are implemented by roughly 110 test methods. That ratio is normal
— a case with four expected results needs four assertions, and a case listing
several inputs becomes several parameterised runs.

## Defects found

Full detail and reproduction in `TRACEABILITY.md`.

| ID | Severity | Summary | Report section |
|---|---|---|---|
| DEF-010 | High | `book.php` never reads or decrements `available_rooms`; zero-room properties accept unlimited bookings | 3.4 |
| DEF-011 | Critical | Uploaded identity documents served from `public/uploads` with no authorisation check — confirmed HTTP 200 anonymously | 4.8 |
| DEF-012 | Medium | `verify_csrf()` sets HTTP 419, which Apache rewrites to 500. The block works but reports as a server crash | 4.8 |
| DEF-013 | Low | `label { display: block }` in app.css overrides the `hidden` attribute, so the student-number field stays visible for landlords | 4.7 |
