# EMERGEX automated test suite

Playwright for .NET (C# / NUnit) test automation for the EMERGEX student
accommodation prototype.

---

## 1. What the suite covers

| Layer | Folder | Category filter | What it proves |
|---|---|---|---|
| UI | `UITests/` | `UI` | Rendering, badges, responsiveness, accessible structure |
| Functional | `FunctionalTests/` | `Functional` | Business rules: auth, validation, filtering, access control |
| Integration | `IntegrationTests/` | `Integration` | Browser → PHP → MySQL agree; rows are persisted correctly |
| End-to-end | `End-to-EndTests/` | `E2E` | Complete multi-role user journeys |
| Regression | `RegressionTests/` | `Regression` | Smoke pack plus one guard per fixed defect |

Shared code lives in `Support/`:

- `TestConfig.cs` — base URL, demo credentials, seeded data constants
- `BaseTest.cs` — fresh browser context per test, sign-in helpers
- `Database.cs` — read-only-ish MySQL access for the integration suite
- `TestFiles.cs` — in-memory upload fixtures
- `Support/Pages/` — page objects; **all selectors live here and nowhere else**

---

## 2. One-time setup

```powershell
# From the EMERGEX.Tests folder
dotnet build
pwsh bin/Debug/net9.0/playwright.ps1 install chromium
```

Then load the demo database (from a terminal, not the browser):

```powershell
php ..\application\database\install.php
```

That creates the three demo accounts and four seeded properties the tests
assert against.

---

## 3. Configure the base URL — read this before your first run

`application/app/config/config.php` sets `base_url` to `/emergex/public`, but the
project currently sits in `htdocs/emergex-group-55/application/public`. Every
link the app renders therefore points at a path that does not exist, so
click-through navigation will 404.

**This has already been done** for this checkout: `config.php` sets

```php
'base_url' => '/emergex-group-55/application/public',
```

and `TestConfig.BaseUrl` defaults to the matching
`http://localhost/emergex-group-55/application/public`, so no environment
variable is needed.

If you move the project to a different folder, keep those two in step. Both can
be overridden without editing code:

```powershell
$env:EMERGEX_BASE_URL   = "http://localhost/your/path"
$env:EMERGEX_DB_CONNECTION = "server=127.0.0.1;port=3306;database=emergex;user=root;password=;"
```

---

## 4. Running the tests

```powershell
# Everything
dotnet test --settings .runsettings

# One layer at a time
dotnet test --filter "Category=UI"
dotnet test --filter "Category=Functional"
dotnet test --filter "Category=Integration"
dotnet test --filter "Category=E2E"
dotnet test --filter "Category=Regression"

# Watch it happen (useful for a demo or a screen recording)
$env:HEADED = "1"; dotnet test --filter "Category=E2E" -- Playwright.LaunchOptions.Headless=false
```

Results are written to `TestResults/` as a `.trx` file and an HTML report.

---

## 5. Test case traceability

Each test carries its test-case id in a comment (`TC-U01`, `TC-F04`, `TC-I07`,
`TC-E02`, `TC-R08`). Use these to link back to the 30 cases in your test
management tool, so the report shows automated coverage per case.

Regression defect guards additionally carry `[Property("Defect", "DEF-00n")]`
so a failing test names the defect it protects.

---

## 6. Test cases that could not be automated as written

These are worth stating explicitly in your report — "not automated, with a
documented reason" is a stronger finding than a test that quietly passes
against nothing.

| Original test case | Why it cannot run against this build |
|---|---|
| **Landlord Creates Property Listing** | The prototype has no listing-creation form. `landlord.php` only lists properties and responds to booking requests; properties come from `seed.sql`. Either build the form or reclassify this as a manual/deferred case. |
| **Prevent Submission with Missing Rent Price** | Same reason — there is no rent field to leave blank. Replaced by equivalent server-side validation tests on the booking form (`TC-F21`, `TC-F22`). |
| **Admin Override – Hide Inappropriate Listing** | `admin.php` can verify and publish a property but has no unpublish or hide action. |
| **Display "Fully Booked" Overlay** | `available_rooms` is displayed as a number; there is no sold-out overlay or state in the UI. |
| **Payment Processing Loading UI** | The deposit simulation is a plain synchronous form POST with no loading/spinner state to assert on. |

The remaining cases are covered. If you add any of the missing features, the
page objects in `Support/Pages/` are where the new selectors belong.

---

## 7. Conventions worth defending in a viva

- **No selector appears in a test.** Tests read as intent; page objects hold the
  DOM detail, so a markup change is a one-line fix.
- **Web-first assertions.** `Expect(locator).ToBeVisibleAsync()` retries until
  the timeout instead of failing on a race, so there are no `Thread.Sleep` calls.
- **Tests clean up after themselves.** Anything created is removed in a
  `finally` block or `[TearDown]`, so the suite is repeatable without reseeding.
- **Negative and security cases are first-class.** CSRF (419), throttling (429),
  privilege escalation (403), XSS escaping and disguised file uploads are all
  covered, not just the happy path.
- **Integration means integration.** Those tests assert on MySQL rows, not on
  screen text, which is the only way to prove persistence actually happened.
