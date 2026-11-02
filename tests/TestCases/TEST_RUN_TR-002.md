# Test run TR-002 — corrections verified

The second run of record. TR-001 found seven defects; this run confirms
all seven are corrected and that correcting them broke nothing else.

| Field | Value |
| --- | --- |
| Run identifier | **TR-002** |
| Date executed | **1 November 2026** |
| Executed by | Novithan Naidoo (402412304), Group 55 |
| Build under test | Richfield accommodation interface, after the seven corrections |
| Environment | Windows 11 · Apache (XAMPP) · PHP 8 · MySQL 8 · Chromium, headless |
| Command | `pytest` |
| Duration | **7 minutes 48 seconds** (468.03 s) |
| Evidence | `reports/TR-002/report.html`, `reports/TR-002/results.xml` |

## Result

| Measure | TR-001 | TR-002 |
| --- | ---: | ---: |
| Test functions | 152 | 152 |
| Executions collected | 230 | 230 |
| Executed | 224 | 224 |
| **Passed** | 217 | **224** |
| **Failed** | 7 | **0** |
| Skipped | 6 | 6 |
| Pass rate | 96.9% | **100%** |
| Open defects | 7 | **0** |

The six skips are the fallback cases (TC-54 to TC-56), unchanged between
runs. They remain opt-in.

## The seven corrections

| Defect | Case | Correction made | TR-002 |
| --- | --- | --- | ---: |
| DEF-01 | TC-31 | Maximum length of 160 on `fullName` | Pass |
| DEF-02 | TC-37 | At least one room required, with an error element in that section | Pass |
| DEF-03 | TC-38 | Each room's campus compared against the submitted campus | Pass |
| DEF-04 | TC-57 | Move-in month validated against the offered twelve | Pass |
| DEF-05 | TC-58 | Campus validated against the campus table, error shown beside the field | Pass |
| DEF-06 | TC-59 | `status` changed to `ENUM('new','contacted','viewed','closed')` | Pass |
| DEF-07 | TC-60 | Guardian wording corrected on the form and the landing page | Pass |

All seven are in `apply.php` apart from DEF-06, which is a schema change
applied with `ALTER TABLE`, and DEF-07, which also touches `index.php`.

## What correcting them revealed

**Thirty tests failed on the first attempt after the corrections**, and
every one for the same reason. `fill_valid()` selected a room at Umhlanga
while the form's campus defaulted to Bryanston. Until DEF-03 was fixed
the build accepted that mismatch, so the suite had been submitting an
invalid combination throughout and never noticed.

Enforcing BR-07 made those submissions invalid, which is correct — and
the regression layer reported it within seven minutes. The fixtures were
changed to select rooms at the campus being submitted, which is also what
a student would do.

This is worth recording for two reasons. It is the clearest evidence in
the project that the regression layer does its job: one change, thirty
immediate reports, one cause. And it shows that a defect can hide a second
problem — the unrealistic test data was invisible while the rule went
unenforced.

**A second finding, unrelated to the corrections.** Until this run the
suite was testing a copy of the application held directly under `htdocs`,
not the copy inside the repository. They were almost certainly identical,
but nothing kept them so: an edit to the repository copy would not have
been tested, and the suite would have kept passing against the stale one.
`RF_BASE_URL` now points at the repository copy — the one that is
submitted. **Test what you ship.**

**A third, in the suite itself.** TC-59 initially still failed after the
column was changed to an ENUM. The test watched for the write to throw,
but XAMPP's MySQL does not run in strict mode: an invalid ENUM value is
coerced to an empty string with a warning rather than refused. The
constraint was working; the test was watching the wrong signal. It now
checks what is actually stored, which gives the same answer on a strict
server.

## Reproducing

From the `tests` folder, with Apache and MySQL running:

```
C:\xampp\php\php.exe "..\Richfield accommodation interface\database\seed.php"
pytest
```

Expect 224 passed, 6 skipped, none failed.
