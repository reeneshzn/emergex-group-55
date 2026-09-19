# EMERGEX testing — session handover

Paste this into a new chat to resume with full context.
Last updated: 19 September 2026.

---

## 1. Who and what

**User:** Mfundo, Group 55 (six members).
**Their responsibility:** Phase 5 sections **5.3 Testing** and **5.4 System
Testing** only. Phase 2 is owned by another member.
**Status: complete.** Both sections written, converted to .docx and .pdf.

**Project:** EMERGEX — a student accommodation platform addressing accommodation
fraud, with landlord verification, NSFAS accreditation filtering, and a simulated
deposit escrow. Plain PHP 8 + MySQL 8 under XAMPP.

- App: `C:\xampp\htdocs\emergex-group-55\application\public`
- Tests: `C:\xampp\htdocs\emergex-group-55\EMERGEX.Tests`
- Database: MySQL `emergex` on 127.0.0.1:3306, user `root`, no password

---

## 2. Project phases

| Phase | Due | Weight | Owner |
|---|---|---|---|
| 1 Proposal | — | 10%* | Submitted |
| 2 Planning | 18 Sept 2026 | 25% | Another member |
| 3 Analysis | 9 Oct 2026 | 20% | — |
| 4 System Design | 30 Oct 2026 | 20% | — |
| 5 Implementation | 9 Nov 2026 | 25% | **5.3 + 5.4 = Mfundo (done)** |

\* inferred; unconfirmed.

Phase 5 also contains 5.1 Introduction, 5.2 Coding and 5.5 Installation. **Not
written, owner unknown** — Mfundo was going to check whether these are his.

---

## 3. What was built

A six-layer automated suite: **36 test cases, 118 automated tests, 114 passing,
4 failing by design.** Runs in 46 seconds.

```
Support/                     framework — no tests
  TestConfig.cs              URL, credentials, seed constants (all env-overridable)
  BaseTest.cs                fresh browser context, sign-in helpers, PostAction
  Database.cs                MySqlConnector access + cleanup helpers
  TestFiles.cs               in-memory upload fixtures
  Pages/                     page objects — EVERY selector lives here
UITests/PresentationTests.cs                    25   [UI]
FunctionalTests/AuthenticationTests.cs          17   [Functional]
FunctionalTests/PropertySearchTests.cs          12
FunctionalTests/BookingAndUploadTests.cs         7
IntegrationTests/PersistenceTests.cs             9   [Integration]
End-to-EndTests/JourneyTests.cs                  5   [E2E]
RegressionTests/CriticalPathRegressionTests.cs  22   [Regression]
RegressionTests/DataIntegrityTests.cs            2
SecurityTests/SecurityTests.cs                  16   [Security]
```

Ten tests carry two categories, so level totals exceed 118.

**Tech:** Playwright for .NET 1.62, NUnit 4.6.1, MySqlConnector 2.4, net9.0.

**Conventions:** no selector in any test · no `Thread.Sleep` (web-first
assertions) · every test cleans up in `finally` or `[TearDown]` · integration
tests assert on MySQL rows, not screen text.

---

## 3a. Git — branch state (IMPORTANT)

The repository is **`emergex-group-55`**, shared by all six members.

**All testing work lives on the branch `testing`. `main` does not have it.**
Switching to `main` removes the entire `EMERGEX.Tests` source tree from disk —
that is normal Git behaviour, not data loss. Switch back to `testing` and it
returns.

State as of 19 September 2026:

| Branch | Contains |
|---|---|
| `main` | Application code from other members (last push 13 days ago), plus a `.gitignore` / `.gitattributes` commit |
| `testing` | Everything in `EMERGEX.Tests/`, plus the `config.php` base_url fix |

**The merge has not been done yet.** It is low risk — the testing work is in a
folder nobody else touches, and nobody has pushed to `main` in 13 days.

Before merging:

1. Tell the team that `application/app/config/config.php` changed —
   `base_url` was `/emergex/public`, which does not exist; it is now
   `/emergex-group-55/application/public`. The old value was broken, but
   teammates will pull the change.
2. Fetch origin first.
3. Merge `testing` into `main`, check History, then push.
4. Leave the `testing` branch in place for a week before deleting it.

**Known conflict:** `.gitignore` was modified on both branches. The version on
`testing` is the combined one — it keeps the original project rules
(`.project_runtime/`, `**/backups/*.sql`, `*.pid`, `*.log`) and adds build,
IDE, test-output and uploads rules. Resolve the conflict by taking the
`testing` version.

Also added at repo root: `.gitattributes`, which normalises line endings to LF
and silences the CRLF warnings.

---

## 4. Test cases

`TestCases/test_cases_v2.json` — 36 cases, TC-01 to TC-36, all automatable.

| Layer | Cases | IDs |
|---|---|---|
| UI | 6 | TC-01 – TC-06 |
| Functional | 13 | TC-07 – TC-19 |
| Integration | 5 | TC-20 – TC-24 |
| End-to-end | 3 | TC-25 – TC-27 |
| Regression | 3 | TC-28 – TC-30 |
| Security | 6 | TC-31 – TC-36 |

**Case status: all 36 are `approved`.** That is the specification lifecycle
(draft → ready → approved), not the execution result — a case can be approved
and still fail, because approval means the specification is right, not that the
application satisfies it.

**Execution results belong to a test run,** not to the case. The run of record
is **TR-001** (6 September 2026). Results: **33 cases passed, 3 did not** —
TC-07, TC-12, TC-33. Four test *methods* failed; the fourth is DEF-010, which
has no case because the control it would verify does not exist in the build.

The report documents TR-001 formally in section 5.4.4 with environment,
preconditions, command and evidence.

JSON keeps the user's tool fields (`title`, `description`, `tags`, `date`) and
adds `id`, `layer`, `priority`, `status`, `preconditions`, `steps`,
`expected_result`.

**An earlier set of 30 was discarded.** Nine described features the build does
not have (listing creation, hide listing, fully-booked overlay, payment loading
UI, webhook sync, payment decline, reject-landlord, profile edit, missing rent
price). Replaced with real uncovered behaviour: CSRF, throttling, security
headers, cookie hardening, output escaping, audit logging, randomised upload
filenames, price tampering.

---

## 5. Defects found

All four found by automation; none visible through manual use.

| ID | Sev | Defect | Fix |
|---|---|---|---|
| DEF-011 | Critical | Uploaded identity documents written to `public/uploads`, served by Apache with no auth check. **Confirmed:** anonymous request returned HTTP 200 + file contents. Random filename is obscurity, not access control. | Store outside web root; stream via authenticated handler checking `user_documents.user_id` |
| DEF-010 | High | `book.php` never reads or decrements `available_rooms`. A zero-room property accepts unlimited bookings. `SMALLINT UNSIGNED` is a storage constraint, not a business rule. | Availability check + decrement inside the existing booking transaction |
| DEF-012 | Medium | `verify_csrf()` sets HTTP 419 — a Laravel convention, not IANA-registered. Apache rewrites it to 500. The block works; it just reports as a server crash and the "form expired" message is lost. | Return 403 in `bootstrap.php` |
| DEF-013 | Low | `app.js` sets `hidden` on `.student-only`, but `app.css` has `label { display: block }`, which beats the UA `[hidden]{display:none}`. Field stays visible and submittable for landlords. | Add `[hidden] { display: none !important; }` to app.css |

**These four tests must stay red.** Deleting them gives a 100% pass rate and
hides the defects. They are the acceptance criteria for the fixes.

| Failing test | Defect |
|---|---|
| `UploadedDocuments_AreNotReadableWithoutASession` | DEF-011 |
| `Booking_OnAPropertyWithNoRoomsAvailable_IsRefused` | DEF-010 |
| `LoginForm_WithoutCsrfToken_ReturnsACleanRejectionStatus` | DEF-012 |
| `RegistrationForm_HidesStudentNumberForLandlords` | DEF-013 |

---

## 6. Documents produced

All in `EMERGEX.Tests/Report/`, written as Word-compatible HTML because the docx
toolchain needs a Linux sandbox that will not start on this device.

**Convert:** right-click → Open with → Word → Save As → .docx.
Headings become real Word styles, so References → Table of Contents works.

| File | Covers | Status |
|---|---|---|
| `EMERGEX_Testing_Report.html` | Phase 5 §5.3 + §5.4, Appendix A (36 cases in full), Appendix B (reproduction) | **Converted to .docx and .pdf by user** |
| `Presentation_Brief_Testing.html` | Speaking notes, key figures, 12 anticipated questions with answers | New |
| `Phase2_2.4_Project_Planning.html` | Written before Phase 2 was reassigned — **hand to the Phase 2 owner.** Needs `Member A`–`Member F` replaced with real names; Phase 1 weighting unconfirmed | Unused |
| `Phase2_2.7_Data_Models.html` | ERD, 7 entities, data dictionary, state models, normalisation, constraints | Unused |

Also: `TRACEABILITY.md` (case→method map + defect register), `README.md` (setup),
`TestResults/` (TRX + HTML run artefacts).

---

## 7. Running the suite

```powershell
# Apache + MySQL running in XAMPP first.

# Reset to a known state
C:\xampp\php\php.exe C:\xampp\htdocs\emergex-group-55\application\database\install.php

# Recorded run — writes TRX + HTML to TestResults\
cd C:\xampp\htdocs\emergex-group-55\EMERGEX.Tests
dotnet test --settings .runsettings

# One layer
dotnet test --filter "Category=Security"
```

Visual Studio Test Explorer works for interactive use (group by **Traits** to see
the six levels) but **does not honour the logger settings**, so it cannot produce
a results file. Recorded runs must go through the CLI.

Demo accounts: `student@emergex.test` / `Student@55` ·
`landlord@emergex.test` / `Landlord@55` ·
`admin@emergex.test` / `Admin@55Secure`

Seeded properties: Emerald House Hatfield (instant, NSFAS, R5 200) ·
Braamfontein Studio Living (request, NSFAS, R6 100) ·
Durban North Student Lofts (instant, no NSFAS, R4 550) ·
Mowbray Campus Rooms (request, NSFAS, R4 800).
4 published, 3 NSFAS, 2 instant, 2 under R5 000.

---

## 8. Environment gotchas already solved

1. `config.php` `base_url` was `/emergex/public`, which doesn't exist. Fixed to
   `/emergex-group-55/application/public`; `TestConfig.BaseUrl` defaults to the
   matching absolute URL, so no env var is needed.
2. `php` is not on PATH — use `C:\xampp\php\php.exe`. `install.php` is CLI-only
   (403s in a browser) and safe to re-run (schema drops all tables first).
3. `pwsh` is not installed. Use
   `powershell -ExecutionPolicy Bypass -File bin\Debug\net9.0\playwright.ps1 install chromium`.
4. The Linux sandbox does not start on this device — no bash, node, pandoc or
   soffice. All documents are hand-written HTML.

---

## 9. Two findings worth repeating

**Client-side validation caused a false pass.** The SQL injection test appeared
to pass through the UI, but the email field is `type="email" required`, so the
browser blocked the payload before it reached the server. The test was proving
Chromium validates emails, not that the app resists injection. Rewritten to POST
directly via `BaseTest.PostAction`. Client-side validation is a usability
feature, never a security control — the attacker chooses the client.

**A test written to fail is a legitimate deliverable.** See §5.

---

## 10. Advice given for a future rebuild

The 36 test cases survive a rebuild — they describe behaviour, not
implementation. Page objects would need rewriting if markup changes, but that's
two files, not 118 tests. Strongest recommendation: add `data-testid` attributes
to markup during development so tests target attributes that exist purely for
testing rather than CSS classes that change with a redesign.

---

## 10a. Separate project — BeyondTheBugs portfolio

Unrelated to EMERGEX, but worked on in the same sessions. Lives at
`C:\xampp\htdocs\BeyondTheBugs\BeyondTheBugs-main`, deployed to Netlify from
the GitHub repo `NovithanNaidoo/BeyondTheBugs` (downloaded as a ZIP, so the
local folder is **not** a git repo — files are uploaded through the GitHub web
interface).

Work done: Supabase cloud sync for the BYB Testing Tool (replacing
localStorage), mobile responsive layout, terminal-style page headings, flying
bug logo, WebGL fluid cursor trail, matrix rain background, a live test-run
terminal replacing the old bug-hunt game, defect showcase cards, and a compact
footer.

Outstanding on that project: the Trello API key and token are still hardcoded
in `BYB_TestingTool/index.html` around line 820 and deployed publicly — anyone
viewing source gets write access to the board. Revoke or move server-side.

---

## 11. When the real application is built

The current EMERGEX build is a demo. Mfundo will re-test the final application
and has asked for **everything below to be updated at that point** — not
rewritten from scratch.

**What carries over unchanged:**
- The 36 test cases (they describe behaviour, not implementation)
- The six-level strategy and the framework structure
- The four defects, as things the rebuild should avoid
- The presentation brief's reasoning (only the figures change)

**What needs rework:**
- `Support/Pages/*` — all selectors, if the markup changes. This is the main job,
  and it is two files rather than 118 tests
- `Support/Database.cs` — if the schema changes
- `TestConfig.cs` — URLs, seeded-data constants
- Flash message text assertions, if wording changes

**Documents to regenerate after the new run:**
1. `TRACEABILITY.md` — new results per case, new defect register
2. `Report/EMERGEX_Testing_Report.html` — §5.4.3 results tables, §5.4.4 defects,
   §5.4.5 evaluation
3. `Report/Presentation_Brief_Testing.html` — the six figures and any answer
   citing a specific defect
4. This handover

**Tell the builders first:** add `data-testid` attributes to the markup during
development. Page objects then target attributes that exist purely for testing,
and a redesign stops breaking tests. Free if done while writing the HTML.

## 12. Open items

- [ ] Confirm who owns Phase 5 sections **5.1, 5.2, 5.5** — not testing work,
      but unwritten
- [ ] Hand `Phase2_2.4` and `Phase2_2.7` documents to the Phase 2 owner
- [ ] Fill in the personal background section of `Presentation_Brief_Testing.html`
- [ ] Optional: cross-browser execution (Firefox/WebKit) — config change only,
      would triple UI coverage
- [ ] Optional: report the four defects to whoever maintains the PHP code
