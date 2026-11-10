# Phase 5 — repository link and evidence appendix

Everything needed to add the two missing items to `Phase 5-Implementation.docx`
without rewriting any of it. Take the screenshots in the order below, paste the
captions underneath each one, re-export the PDF.

---

## 1 · The repository link

Add it in **two** places so a marker finds it wherever they look first.

**On the title page**, directly under "Due 9 November 2026":

> Project repository: https://github.com/reeneshzn/emergex-group-55

**In Appendix B**, above the "Repository contents" table, replace the opening
line with:

> The complete source — the application, the automated test suite, both run
> records and this documentation — is published at
> **https://github.com/reeneshzn/emergex-group-55**. The paths below are
> relative to the root of that repository.

In Word, select the URL and press **Ctrl+K**, then paste the same address, so
it is a working hyperlink in the PDF rather than plain text.

---

## 2 · Evidence appendix

Add this as **Appendix D**, after Appendix C and before References. Add the two
lines to the Table of Contents as well.

### Heading

> **Appendix D — Evidence of implementation and testing**
>
> The screenshots below record the suite as it exists in the repository and as
> it behaves when executed. Figures D.1 to D.4 show the code; D.5 to D.9 show
> the two runs and their results; D.10 and D.11 show the test management tool
> and the published repository.

---

### Group 1 — The code (4 screenshots)

**D.1 — The suite structure**
Open the `tests` folder in VS Code with `suites` expanded so all seven layer
folders are visible (`00-smoke` through `06-e2e`), plus `support`, `tools` and
`TestCases`.

> *Figure D.1 — The test suite in the repository. The seven layer folders
> correspond to the test levels in Table 5.3.1; the layer is also recorded as a
> pytest marker on every test, so any level can be run on its own.*

**D.2 — The Page Object Model**
Open `tests/support/pages.py`, scrolled to any block of selectors.

> *Figure D.2 — support/pages.py. Every CSS selector in the suite is declared
> here and nowhere else, so a markup change is one edit rather than a search
> through eleven test files.*

**D.3 — Shared fixtures**
Open `tests/conftest.py`, scrolled to the `application_is_running` fixture.

> *Figure D.3 — conftest.py. The session fixture stops the entire run with one
> clear message if Apache or MySQL is down, rather than allowing thirty
> integration tests to skip unnoticed.*

**D.4 — A test that found a defect**
Open `tests/suites/02-functional/test_validation.py` at
`test_a_request_naming_no_rooms_is_refused`.

> *Figure D.4 — The test that found DEF-02. It asserts the business rule from
> Phase 3 (BR-06) that a viewing request must name at least one room.*

---

### Group 2 — The testing (5 screenshots)

**D.5 — A run in progress**
In PowerShell, from the `tests` folder with the virtual environment active, run
`pytest -v` and capture mid-run, showing test names passing.

> *Figure D.5 — Execution in progress. Each line is one of the 230 executions
> produced by the suite's 152 test functions.*

**D.6 — The TR-002 summary**
Capture the end of the run showing `224 passed, 6 skipped`.

> *Figure D.6 — Test run TR-002, the verification run. 224 passed, none failed.
> The six skips are the opt-in fallback cases TC-54 to TC-56.*

**D.7 — The TR-002 HTML report**
Open `tests/reports/TR-002/report.html` in a browser.

> *Figure D.7 — The generated TR-002 report. pytest writes this and results.xml
> on every run; both are archived per run so earlier evidence is never
> overwritten.*

**D.8 — The TR-001 failures**
Open `tests/reports/TR-001/report.html` and filter to the failed tests.

> *Figure D.8 — Test run TR-001, before correction. Seven failures, each traced
> to one of the defects DEF-01 to DEF-07 in Table 5.4.8.*

**D.9 — Running a single layer**
Run `pytest -m security` and capture the result.

> *Figure D.9 — One test level executed on its own. Because the level is a
> marker rather than a folder, a test can belong to several levels and still be
> run as part of any of them.*

---

### Group 3 — Tool and repository (2 screenshots)

**D.10 — BeyondTheBugs**
Open https://beyondthebugs.netlify.app/ and select **Open Tool**. Capture the
test case list showing the sixty cases, and if space allows a second shot of
the defect register.

> *Figure D.10 — BeyondTheBugs, the test management tool built for this
> project. It holds the sixty case specifications, both runs with a result
> recorded against every case, and the seven defects linked to the cases that
> found them.*

**D.11 — The repository**
Open https://github.com/reeneshzn/emergex-group-55 showing the folder listing.

> *Figure D.11 — The published repository. The application, the test suite and
> all documentation are version-controlled together.*

---

## 3 · Capturing

**Win + Shift + S** opens the Windows snipping tool. Drag a rectangle; the
image goes to the clipboard and can be pasted straight into Word with Ctrl+V.

For the code screenshots, VS Code's zoom (**Ctrl + =**) two or three notches up
makes the text readable once the image is scaled to page width. Code that
cannot be read in print is a figure that proves nothing.

In Word, select each image and set **Picture Format → Wrap Text → In Line with
Text**, then centre it. Put the caption directly underneath in italics.

---

## 4 · Checklist before re-export

- [ ] Repository link on the title page
- [ ] Repository link in Appendix B, as a working hyperlink
- [ ] Appendix D added, 11 figures, each with its caption
- [ ] Appendix D added to the Table of Contents
- [ ] Table of Contents updated (References → Update Table → Entire table)
- [ ] Re-exported to PDF
- [ ] Both files saved into `documentation/`
