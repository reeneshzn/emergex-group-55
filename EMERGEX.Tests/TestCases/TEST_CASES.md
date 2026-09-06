# EMERGEX test cases (v2)

30 test cases re-derived from the behaviour the application actually implements.
Every case is automatable; the previous set contained nine that described
features absent from the build.

Import file: `test_cases_v2.json` — full steps, preconditions and expected
results per case. This document is the quick reference.

---

## Distribution

| Layer | Cases | IDs |
|---|---|---|
| UI | 6 | TC-01 – TC-06 |
| Functional | 13 | TC-07 – TC-19 |
| Integration | 5 | TC-20 – TC-24 |
| End-to-end | 3 | TC-25 – TC-27 |
| Regression | 3 | TC-28 – TC-30 |
| Security | 6 | TC-31 – TC-36 |
| **Total** | **36** | |

By priority: 28 high, 6 medium, 2 low.

---

## UI — does it render correctly

| ID | Title | Why it matters |
|---|---|---|
| TC-01 | Verified Landlord Trust Badge Rendering | Students rely on the badge to distinguish vetted landlords |
| TC-02 | NSFAS Accreditation Badge Visibility | A false badge sends a funded student to accommodation their bursary won't cover |
| TC-03 | Booking Call-to-Action Reflects Booking Mode | The student must know before clicking whether confirmation is immediate |
| TC-04 | Search Grid Mobile Responsiveness | Most students browse on a phone |
| TC-05 | Role-Aware Header Navigation | Navigation leaking admin links is an information disclosure |
| TC-06 | Accessible Empty State and Status Messaging | A blank grid reads as a broken site |

## Functional — does it behave correctly

| ID | Title | Why it matters |
|---|---|---|
| TC-07 | Register New Student Account | Core onboarding path |
| TC-08 | Enforce Password Policy on Registration | Weak passwords compromise student accounts holding ID documents |
| TC-09 | Reject Duplicate Email Registration | Prevents account collision |
| TC-10 | Reject Invalid Login Credentials | Generic errors prevent account enumeration |
| TC-11 | Throttle Repeated Failed Login Attempts | Makes brute-force attacks impractical |
| TC-12 | Reject Form Submission Without CSRF Token | Prevents third-party sites acting as the user |
| TC-13 | Student Logout and Session Termination | A live session after sign-out is a shared-computer risk |
| TC-14 | Block Unauthorised URL Access by Role | Privilege escalation is the highest-impact failure here |
| TC-15 | Filter Properties by NSFAS Status | Wrong results waste a funded student's application |
| TC-16 | Filter Properties by Maximum Rent | Budget is the primary search constraint |
| TC-17 | Keyword Search and Empty Result Handling | Search is the main entry point to the product |
| TC-18 | Reject Invalid Booking Submission | Bad data reaching bookings corrupts the landlord's queue |
| TC-19 | Reject Malicious File Uploads | An executable upload on a PHP host is remote code execution |

## Integration — does it reach the database correctly

| ID | Title | Why it matters |
|---|---|---|
| TC-20 | Search Grid Matches Database Contents | Proves the page reflects live data, not a cache |
| TC-21 | Registration Stores Hashed Password and Correct Role | Plain-text passwords would be a reportable breach |
| TC-22 | Instant Booking Persists Booking and Escrow Records | Money-adjacent state must be written correctly |
| TC-23 | Escrow Simulation Transitions Through Every State | Out-of-order state means funds held or released wrongly |
| TC-24 | Document Upload and Audit Log Persistence | Randomised filenames prevent enumeration of student ID documents |

## End-to-end — do the journeys work

| ID | Title | Roles involved |
|---|---|---|
| TC-25 | Complete Instant Book and Simulated Payment Journey | Student |
| TC-26 | Accommodation Request, Landlord Decision and Deposit Journey | Student, Landlord |
| TC-27 | Landlord Verification and Document Review Journey | Landlord, Administrator, Student |

## Regression — has anything broken

| ID | Title | Guards against |
|---|---|---|
| TC-28 | Security Headers and Session Cookie Hardening | Clickjacking, MIME sniffing, cookie theft via XSS |
| TC-29 | Output Escaping and Currency Formatting | Stored XSS; incorrect money display |
| TC-30 | Prevent Price Tampering via Request Parameters | A student paying a deposit they chose themselves |

## Security — do the controls hold under hostile input

These are defensive tests: each asserts that a control holds. Payloads are
standard and inert, and are sent only at the application's own interfaces.

| ID | Title | OWASP category |
|---|---|---|
| TC-31 | Resist SQL Injection on Public Inputs | A03 Injection |
| TC-32 | Prevent Account Enumeration and Credential Disclosure | A07 Identification & Authentication Failures |
| TC-33 | Enforce Access Control on Documents and Bookings | A01 Broken Access Control |
| TC-34 | Harden File Upload Handling | A03 Injection / A04 Insecure Design |
| TC-35 | Protect Session Transport and Prevent Caching | A05 Security Misconfiguration |
| TC-36 | Suppress Internal Detail in Error Responses | A05 Security Misconfiguration |

Security coverage is not confined to this layer. Ten tests living in the
Functional, Integration and Regression suites also carry `[Category("Security")]`
— CSRF enforcement, login throttling, role routing, session regeneration,
password hashing, output escaping, disguised uploads and cross-account booking
actions — so `--filter "Category=Security"` returns the complete picture.

---

## What changed from v1

**Removed — described features the build does not have (9):**
Landlord Creates Property Listing · Prevent Submission with Missing Rent Price ·
Admin Override – Hide Inappropriate Listing · Display "Fully Booked" Overlay ·
Payment Processing Loading UI · Backend Webhook Payment Sync ·
Handle Payment Sandbox Decline · Admin Rejects Landlord Verification ·
Retain Landlord Verification on Profile Edit

**Added — real behaviour v1 did not cover:**
CSRF token enforcement · login throttling · security response headers ·
session cookie hardening · output escaping · audit log persistence ·
randomised upload filenames · price tampering via request parameters ·
role-aware navigation · accessible empty states

**Kept and expanded:** registration, login rejection, logout, unauthorised URL
access, NSFAS and rent filtering, empty results, malicious uploads, search-grid
database integration, upload path storage, escrow persistence, both booking
journeys, mobile responsiveness, verified badge rendering.

---

## Note on case-to-script ratio

30 cases are implemented by roughly 95 automated test methods. This is expected:
a case with four expected results needs four assertions, and a case listing
several inputs becomes several parameterised runs. `TRACEABILITY.md` maps each
case to the specific methods that implement it.

## Known defects found during test design

**DEF-010 — bookings are not checked against room availability.**
`available_rooms` is displayed by `property.php`, `landlord.php` and
`reports.php` but is never decremented, and `book.php` never checks it. A
property showing zero rooms accepts unlimited bookings. Not written as a test
case because the control does not exist to be tested.

**DEF-011 — uploaded identity documents are served from the web root.**
`dashboard.php` writes uploads to `public/uploads`, which Apache serves
directly, with no authorisation check on retrieval. The stored filename is 40
random hex characters, so it cannot be guessed — but an unguessable URL is
obscurity, not access control. Anyone who obtains the link (a shared
screenshot, a Referer header, browser history sync) can read another student's
ID document with no session at all.

TC-33 asserts the secure behaviour and is **expected to fail** against the
current build. A failing test that documents a real weakness is a finding, and
is stronger evidence than omitting the case.

Suggested fix: store uploads outside the web root and stream them through an
authenticated PHP handler that checks `user_documents.user_id` against the
session before sending bytes.
