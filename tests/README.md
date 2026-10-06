# Automated test suite — Richfield Student Accommodation

Python · pytest · Playwright

Tests the application in `../Richfield accommodation interface`.

---

## First-time setup

Run these once, from this `tests` folder, in PowerShell.

**1. Check Python is installed** (needs 3.9 or newer):

```powershell
python --version
```

No output, or "not recognised"? Install it from
<https://www.python.org/downloads/> and **tick "Add python.exe to PATH"**
on the first screen of the installer. Then open a *new* terminal.

**2. Create a virtual environment** — keeps these packages out of your
system Python:

```powershell
cd "C:\xampp\htdocs\emergex-group-55\tests"
python -m venv .venv
.venv\Scripts\Activate.ps1
```

Your prompt should now start with `(.venv)`.

> If PowerShell blocks the activate script, run
> `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass` and try again.

**3. Install the packages and the browser:**

```powershell
pip install -r requirements.txt
playwright install chromium
```

---

## Running the tests

Apache must be running in XAMPP first.

```powershell
pytest suites/test_smoke.py -v
```

Start with the smoke suite. It proves the suite is wired to the
application correctly — routes answer, selectors match, database reachable.
If it fails, nothing else is meaningful.

Then the whole suite:

```powershell
pytest
```

One level at a time:

```powershell
pytest -m security
pytest -m "ui or regression"
pytest -m logic          # no browser needed, runs in under a second
```

Watch it happen in a real browser:

```powershell
pytest --headed --slowmo 500
```

Re-run only what failed last time:

```powershell
pytest --lf
```

---

## Reports

Every run writes to `reports/`:

| File | What |
| --- | --- |
| `report.html` | Browsable results — open it in a browser |
| `results.xml` | JUnit XML, for the record |

Both are regenerated each run, so copy them somewhere else to keep a
dated record of a particular run.

---

## The six test levels

Every test carries at least one marker. These are the levels described in
Phase 5.

| Marker | Question it answers |
| --- | --- |
| `ui` | Does the interface render correctly and accessibly? |
| `functional` | Does the application follow its own rules? |
| `integration` | Does what the browser did actually reach MySQL? |
| `e2e` | Do complete journeys work end to end? |
| `regression` | Has a previously fixed defect come back? |
| `security` | Do the controls hold under hostile input? |
| `logic` | Is the arithmetic right? (no browser) |

A test may carry two — proving a student cannot submit an invalid phone
number is both a functional rule and input handling.

---

## Running without a database

The application deliberately works with or without MySQL, falling back to
the sample arrays in `includes/data.php`. The suite respects that: the
integration tests **skip** with an explanation rather than failing.

To run them, import `database/schema.mysql.sql` through phpMyAdmin and
run `php database/seed.php`.

---

## Configuration

Nothing in the suite hardcodes a URL or a credential — it is all in
`support/config.py`, and every value can be overridden with an
environment variable:

```powershell
$env:RF_BASE_URL = "http://localhost:8000"
pytest
```

| Variable | Default |
| --- | --- |
| `RF_BASE_URL` | `http://localhost/Richfield%20accommodation%20interface` |
| `RF_DB_NAME` | `richfield_accommodation` |
| `RF_DB_USER` | `root` |
| `RF_DB_PASSWORD` | *(blank)* |
| `RF_KNOWN_LISTING` | `acacia-court-bryanston` |

---

## Layout

```
tests/
  pytest.ini            markers, report settings
  requirements.txt      pinned versions
  conftest.py           shared fixtures
  support/
    config.py           every environment value and seeded constant
    database.py         direct MySQL access for the integration layer
    pages.py            page objects — EVERY selector lives here
  suites/
    test_smoke.py       wiring check — run this first
  reports/              generated, not committed
```

**No test contains a CSS selector.** They all live in `support/pages.py`,
so a markup change is a one-line fix rather than a search across the
suite.
