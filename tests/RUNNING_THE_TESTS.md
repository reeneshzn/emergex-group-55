# Running the EMERGEX test suite

Every command you need, including how to watch Playwright drive the browser
live. This file is deliberately kept out of the Phase 5 report — the report
describes what was tested and what was found; this describes how to run it.

Run everything from the `tests` folder with the virtual environment active.

---

## 0 · Before anything else

Start **Apache** and **MySQL** in the XAMPP control panel. Both must show as
running. If either is down the suite stops immediately with a message saying
so, rather than letting thirty tests skip quietly — that check exists because
of what happened on the first attempt at TR-001.

Then open PowerShell:

```powershell
cd "C:\xampp\htdocs\emergex-group-55\tests"
.venv\Scripts\Activate.ps1
```

Your prompt should now begin with `(.venv)`.

If PowerShell blocks the activation script, this allows it for that window
only — it does not change any system setting:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

### First time on a new machine

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
playwright install chromium
```

`playwright install` downloads the browser binary (around 130 MB). It is a
one-off.

### Restore the data before a run of record

```powershell
C:\xampp\php\php.exe "..\Richfield accommodation interface\database\seed.php"
```

The suite cleans up after itself, but a run that is going to be recorded
should start from a known state so the figures mean something.

---

## 1 · The basics

```powershell
pytest                          # the whole suite - this is the recorded run
pytest -v                       # one line per test with its name
pytest -q                       # quiet: dots only
pytest --co -q                  # collect only; list every test, run nothing
```

`pytest` on its own is what produced TR-001 and TR-002. It writes
`reports/report.html` and `reports/results.xml` automatically — that is
configured in `pytest.ini`, not something you pass each time.

Expect **224 passed, 6 skipped** in about 7–8 minutes against the corrected
build.

---

## 2 · Watching it work — the live run

This is the one to use in a demonstration.

```powershell
pytest --headed --slowmo 500
```

`--headed` opens a real visible Chromium window instead of running it
hidden. `--slowmo 500` pauses half a second between actions so a person can
follow what is happening — without it the browser moves far too fast to read.

The whole suite headed takes considerably longer than seven minutes, so pick
a slice:

```powershell
# One complete student journey, slowly, in a visible browser
pytest suites/06-e2e --headed --slowmo 600 -v

# The shortlist, which is the most visual part of the application
pytest suites/02-functional/test_shortlist.py --headed --slowmo 500 -v

# A single test, as slowly as you like
pytest suites/06-e2e/test_journeys.py::test_the_complete_journey_from_search_to_a_stored_request --headed --slowmo 800 -v
```

Other flags worth knowing for a live run:

```powershell
--browser firefox               # or webkit, if installed
--browser-channel chrome        # your real Chrome rather than bundled Chromium
--device "iPhone 13"            # emulate a phone
--screenshot on                 # save a screenshot for every test
--video on                      # record video of every test
--tracing on                    # full trace: DOM snapshots, network, console
```

Videos, screenshots and traces land in `test-results/`.

---

## 3 · Running one layer

The level is recorded as a **marker**, not a folder, so a test belongs to
every level it genuinely belongs to. `-m` selects by marker and works across
the whole suite:

```powershell
pytest -m ui                    # rendering, layout, accessibility
pytest -m functional            # business rules and validation
pytest -m integration           # data reaching and leaving MySQL
pytest -m logic                 # the cost calculator, no browser needed
pytest -m security              # injection, CSRF, tampering, disclosure
pytest -m regression            # guards on previously fixed defects
pytest -m e2e                   # complete journeys
pytest -m defect                # the tests that found DEF-01 to DEF-07
```

Markers combine:

```powershell
pytest -m "ui or logic"
pytest -m "security and not slow"
pytest -m "functional and not defect"
```

Folders work too, when you want the file rather than the level:

```powershell
pytest suites/00-smoke
pytest suites/01-ui
pytest suites/02-functional
pytest suites/03-logic
pytest suites/04-integration
pytest suites/05-security
pytest suites/06-e2e
```

---

## 4 · Running one file, one test, one case

```powershell
# A whole file
pytest suites/02-functional/test_validation.py -v

# One test by name
pytest suites/02-functional/test_validation.py::test_a_request_naming_no_rooms_is_refused -v

# Every test whose name contains "shortlist"
pytest -k shortlist -v

# Name matching combines too
pytest -k "campus and not injection" -v

# One parameterised variation
pytest "suites/03-logic/test_calculator.py::test_termly_instalment_count_rounds_up[10-4]" -v
```

`-k` matches the test function name; `-m` matches the marker. They are
different things and can be used together.

---

## 5 · When something fails

```powershell
pytest --lf                     # last-failed: only what failed last time
pytest --ff                     # failed-first, then the rest
pytest -x                       # stop at the first failure
pytest --maxfail=3              # stop after three
pytest -l                       # show local variable values in the traceback
pytest --tb=long                # full traceback instead of the short default
pytest -s                       # let print() and input() through
pytest --pdb                    # drop into the debugger at the point of failure
```

The most useful pair while fixing something:

```powershell
pytest --lf -x --headed --slowmo 400 -v
```

Only the tests that failed, stopping at the first, in a visible browser slow
enough to watch. That is how the thirty campus-mismatch failures were traced
to one cause.

For a failure you cannot reproduce by watching:

```powershell
pytest --lf --tracing retain-on-failure
playwright show-trace test-results\<folder>\trace.zip
```

The trace viewer gives a frame-by-frame recording with the DOM, the network
log and the console at every step.

---

## 6 · The three fallback tests

TC-54 to TC-56 verify FR10 by moving the application's configuration file
aside to prove the site still runs without a database. They are excluded from
an ordinary run, because an interrupted execution would leave the site on
sample data. They are recorded as **blocked**, not passed or failed, since
not running a test tells you nothing about it either way.

To run them deliberately:

```powershell
$env:RF_RUN_FALLBACK = "1"
pytest -m ui
$env:RF_RUN_FALLBACK = ""
```

Clear the variable afterwards, and check the application still loads.

---

## 7 · Reports and evidence

```powershell
pytest                                  # writes reports/report.html + results.xml
start reports\report.html               # open the HTML report
python tools/layer_index.py             # writes reports/layer-index.md
```

To archive a run so it is not overwritten by the next one:

```powershell
mkdir reports\TR-003
copy reports\report.html reports\TR-003\
copy reports\results.xml reports\TR-003\
```

`reports/TR-001/` and `reports/TR-002/` were archived exactly this way.

---

## 8 · Changing the environment without editing code

Every environment value is read from `support/config.py`, which reads an
environment variable first and falls back to a default. Nothing needs editing
to point the suite somewhere else:

```powershell
$env:RF_BASE_URL = "http://localhost/emergex-group-55/Richfield%20accommodation%20interface"
$env:RF_DB_NAME  = "richfield_accommodation"
$env:RF_KNOWN_LISTING = "acacia-court-bryanston"
$env:RF_CONFIRMED_CAMPUS = "bryanston"
$env:RF_RUN_FALLBACK = ""
```

`RF_BASE_URL` is the important one. It points at the copy of the application
**inside the repository** — the copy that gets submitted. It used to point at
a duplicate under `htdocs`, which meant the suite was verifying one file and
the project was delivering another.

---

## 9 · Speed

```powershell
pytest -n auto                  # parallel, if pytest-xdist is installed
pytest --durations=10           # the ten slowest tests
```

Parallel execution is not used for a run of record: several tests write to
the database and reseed, so running them simultaneously makes results depend
on timing. A fast run that cannot be trusted is worth less than a slow one
that can.

---

## Quick reference

| Goal | Command |
| --- | --- |
| The recorded run | `pytest` |
| Watch it live | `pytest --headed --slowmo 500` |
| Live, one journey | `pytest suites/06-e2e --headed --slowmo 600 -v` |
| One layer | `pytest -m security` |
| One file | `pytest suites/02-functional/test_validation.py -v` |
| One test | `pytest path::test_name -v` |
| By name | `pytest -k shortlist -v` |
| Only what failed | `pytest --lf` |
| Stop at first failure | `pytest -x` |
| List without running | `pytest --co -q` |
| Slowest tests | `pytest --durations=10` |
| Open the report | `start reports\report.html` |
