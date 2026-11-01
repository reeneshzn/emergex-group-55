# Test run TR-001

The run of record for Phase 5. Every figure in the implementation report
comes from this execution.

| Field | Value |
| --- | --- |
| Run identifier | **TR-001** |
| Date executed | **1 November 2026** |
| Executed by | Novithan Naidoo (402412304), Group 55 |
| Application under test | Richfield Student Accommodation interface |
| Environment | Windows 11 · Apache (XAMPP) · PHP 8 · MySQL 8 · Chromium, headless |
| Test runtime | Python 3.13.7 · pytest 8.3.4 · Playwright 0.6.2 |
| Preconditions | Reference data restored with `database/seed.php`; Apache and MySQL restarted |
| Command | `pytest` (whole suite, no filter) |
| Duration | **6 minutes 42 seconds** (402.27 s) |
| Evidence | `reports/report.html`, `reports/results.xml` |

## Result

| Measure | Value |
| --- | ---: |
| Test cases executed | 60 of 60 |
| Test functions | 149 |
| Executions collected | 230 |
| Executed | 224 |
| **Passed** | **217** |
| **Failed** | **7** |
| Skipped | 6 |
| Pass rate (of executed) | **96.9%** |
| Failures traced to a documented defect | 7 of 7 (100%) |
| **Unexplained failures** | **0** |

The six skips are the fallback tests (TC-54 to TC-56), which move the
application's configuration file aside to prove the site runs without a
database. They are opt-in because an interrupted run would leave the
site on sample data. FR10 is therefore unverified in this run.

## The seven failures

Every one asserts behaviour that Phase 3 identified as unenforced and
Phase 4 specifies adding. They are kept deliberately: deleting them
would raise the pass rate and conceal seven real defects. They are the
acceptance criteria for those corrections.

| Test | Case | Rule | Phase 3 | Phase 4 fix |
| --- | --- | --- | --- | --- |
| `test_a_name_longer_than_the_column_is_refused` | TC-31 | FR7 | finding 6 | §4.6.1 |
| `test_a_request_naming_no_rooms_is_refused` | TC-37 | BR-06 | finding 1 | §4.6.1 |
| `test_rooms_must_belong_to_the_selected_campus` | TC-38 | BR-07 | finding 2 | §4.6.1 |
| `test_the_move_in_month_is_checked_against_the_offered_range` | TC-57 | FR7 | finding 3 | §4.6.1 |
| `test_the_campus_is_checked_against_the_campus_table` | TC-58 | FR7, BR-04 | finding 3 | §4.6.1 |
| `test_the_status_column_only_accepts_its_defined_values` | TC-59 | FR6 | finding 4 | §4.5.3 |
| `test_the_guardian_hint_does_not_promise_an_email` | TC-60 | NFR5 | §3.6.1 | §4.7.2 |

### Evidence recorded during the run

Two failures produced stored artefacts, quoted from the run output:

- **TC-37** — reference `RF-261101-3591` was created and stored with
  **0 rooms attached**. A request the provider cannot act on, because
  nothing indicates what the student wants to view.
- **TC-38** — a request for campus `cape-town` naming
  `acacia-court-bryanston`, a room roughly 1 400 km away, was accepted.
- **TC-31** — a 200-character name was accepted and **stored truncated
  to 160 characters**. Silent data loss; neither the student nor the
  provider is told.

## Notes on the run

**The first attempt was discarded.** MySQL had not restarted with
Apache, so roughly thirty integration tests skipped rather than running.
A run in which the database layer is untested is not evidence of
anything, so the service was started and the suite re-run from the
beginning. This record is the second, complete execution.

**Reproducing it.** From the `tests` folder, with Apache and MySQL
running:

```
C:\xampp\php\php.exe "..\Richfield accommodation interface\database\seed.php"
pytest
```

To include the fallback tests:

```
$env:RF_RUN_FALLBACK = "1"
pytest
$env:RF_RUN_FALLBACK = ""
```
