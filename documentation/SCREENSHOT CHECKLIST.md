# Screenshot checklist — Appendix C

Keep this open on screen. Work down the list. Take 25 screenshots in this
order, then paste each one into the matching dashed box in Appendix C.

**To capture:** press `Win + Shift + S`, drag a box, then `Ctrl + V` into Word.

**Before you start:** open XAMPP and press Start on **Apache** and **MySQL**.

---

## Part 1 — VS Code (14 screenshots)

Open VS Code on the folder `C:\xampp\htdocs\emergex-group-55`.

Press `Ctrl` and `=` three times to make the text bigger. Do this before you
start or every screenshot will be unreadable in print.

| # | Open this file | Screenshot what |
| --- | --- | --- |
| **C.1** | — | The file explorer on the left, with `tests` open and `suites` expanded so you can see all seven folders: `00-smoke`, `01-ui`, `02-functional`, `03-logic`, `04-integration`, `05-security`, `06-e2e` |
| **C.2** | `tests\support\pages.py` | Any screen of it |
| **C.3** | `tests\conftest.py` | Any screen of it |
| **C.4** | `tests\suites\00-smoke\test_smoke.py` | The top of the file |
| **C.5** | `tests\suites\01-ui\test_campuses.py` | The top of the file |
| **C.6** | `tests\suites\01-ui\test_behaviour.py` | The top of the file |
| **C.7** | `tests\suites\02-functional\test_search.py` | The top of the file |
| **C.8** | `tests\suites\02-functional\test_listings.py` | The top of the file |
| **C.9** | `tests\suites\02-functional\test_shortlist.py` | The top of the file |
| **C.10** | `tests\suites\02-functional\test_validation.py` | The top of the file |
| **C.11** | `tests\suites\03-logic\test_calculator.py` | The top of the file |
| **C.12** | `tests\suites\04-integration\test_submission.py` | The top of the file |
| **C.13** | `tests\suites\05-security\test_security.py` | The top of the file |
| **C.14** | `tests\suites\06-e2e\test_journeys.py` | The top of the file |

For C.4 to C.14: just open the file and screenshot the code. Include the file
name tab at the top so it is obvious which file it is.

---

## Part 2 — The terminal and the reports (5 screenshots)

Open PowerShell and run these three lines:

```
cd "C:\xampp\htdocs\emergex-group-55\tests"
.venv\Scripts\Activate.ps1
pytest -v
```

| # | Screenshot what |
| --- | --- |
| **C.15** | While it is still running — any moment showing test names scrolling past with PASSED next to them |
| **C.16** | When it finishes — the last line, `224 passed, 6 skipped` |

Then open these two files in your browser:

| # | Open this in a browser | Screenshot what |
| --- | --- | --- |
| **C.17** | `C:\xampp\htdocs\emergex-group-55\tests\reports\TR-002\report.html` | The summary at the top showing 224 passed |
| **C.18** | `C:\xampp\htdocs\emergex-group-55\tests\reports\TR-001\report.html` | The summary showing 7 failed, then scroll to show some failures |

Back in PowerShell, run one more:

```
pytest -m security
```

| # | Screenshot what |
| --- | --- |
| **C.19** | The result — shows only the security tests ran |

---

## Part 3 — Your tool (5 screenshots)

Go to **https://beyondthebugs.netlify.app/** and click **Open Tool**.

| # | Click this in the tool | Screenshot what |
| --- | --- | --- |
| **C.20** | Dashboard | The row of summary cards at the top |
| **C.21** | Test Cases | The list of cases |
| **C.22** | Test Cases, then open any one case | The case showing its steps and expected result |
| **C.23** | Test Runs | The table showing TR-001 and TR-002 with their pass/fail numbers |
| **C.24** | Defects | The list showing DEF-01 to DEF-07 |

---

## Part 4 — GitHub (1 screenshot)

| # | Open this | Screenshot what |
| --- | --- | --- |
| **C.25** | https://github.com/reeneshzn/emergex-group-55 | The folder listing on the repository page |

---

## When all 25 are in

1. Delete every dashed grey box — they are only placeholders
2. Check each image has its italic caption underneath
3. Add **Appendix C** to the Table of Contents
4. Click the Table of Contents, then **References → Update Table → Update entire table**
5. Save as PDF

---

## Also still to do

Add the repository link in two places:

- **Title page**, under "Due 9 November 2026":
  `Project repository: https://github.com/reeneshzn/emergex-group-55`

- **Appendix B**, in the first line above the contents table.

Select the address and press `Ctrl + K`, paste the same address, so it is a
clickable link in the PDF.
