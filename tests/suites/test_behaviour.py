"""
Accessibility, responsiveness and fallback — TC-50 to TC-56, TC-59, TC-60.

Verifies NFR4 (WCAG 2.2 AA contrast, keyboard operability), NFR6 (no
horizontal overflow from 360px to 1920px), FR10 (keep working with no
database connected) and NFR5 as it applies to what the system claims
about itself.

TWO TESTS HERE ARE EXPECTED TO FAIL, marked `defect`:

    TC-59  the status column is not constrained to its four values
    TC-60  the guardian hint promises an email that is never sent

Both are recorded in Phase 3 - finding 4 and §3.6.1 - with the fixes
specified in Phase 4 §4.5.3 and §4.7.2.

THE FALLBACK TESTS ARE OPT-IN. TC-54 to TC-56 move the configuration
file aside to prove the site runs without a database. They restore it,
but an interrupted run would leave the site on sample data, so they only
run when asked:

    $env:RF_RUN_FALLBACK = "1"; pytest suites/test_behaviour.py

If a run is ever interrupted mid-test, rename
includes/config.php.testing-backup back to includes/config.php.
"""

import os
import shutil
import time

import pytest
import requests
from playwright.sync_api import expect

from support import config


CONFIG_PATH = os.path.join(config.APP_DIR, "includes", "config.php")
BACKUP_PATH = CONFIG_PATH + ".testing-backup"


# ======================================================================
# TC-50 — Responsive layout  (NFR6)
# ======================================================================


@pytest.mark.ui
@pytest.mark.regression
@pytest.mark.parametrize("width", config.RESPONSIVE_WIDTHS)
@pytest.mark.parametrize(
    "path",
    [config.HOME, config.SEARCH, config.APPLY],
    ids=["home", "search", "apply"],
)
def test_no_horizontal_overflow_at_any_width(page, path, width):
    """
    TC-50 · NFR6

    Phase 3 found most students search on a phone. Horizontal scrolling
    on a phone is not a cosmetic problem - content moves off screen and
    is never found, because nobody thinks to swipe sideways on a page
    that should scroll down.

    360px is the narrowest Android in common use; 1920px is a desktop
    monitor. Every width between is covered by the seven the project
    committed to.
    """
    from support.pages import BasePage

    page.set_viewport_size({"width": width, "height": 900})
    page.goto(config.url(path))

    overflow = BasePage(page).has_horizontal_overflow()
    if overflow:
        scroll_width = page.evaluate("() => document.documentElement.scrollWidth")
        culprit = page.evaluate(
            """() => {
                 const w = document.documentElement.clientWidth;
                 const wide = Array.from(document.querySelectorAll('*'))
                   .filter(e => e.getBoundingClientRect().right > w + 1)
                   .slice(0, 3)
                   .map(e => e.tagName.toLowerCase()
                        + (e.className ? '.' + String(e.className).split(' ')[0] : ''));
                 return wide;
               }"""
        )
        pytest.fail(
            f"{path} overflows at {width}px: document is {scroll_width}px wide. "
            f"Widest elements: {culprit}"
        )


# ======================================================================
# TC-51, TC-52, TC-53 — Keyboard and assistive technology  (NFR4)
# ======================================================================


@pytest.mark.ui
def test_the_first_tab_stop_skips_the_navigation(home):
    """
    TC-51 · NFR4

    Without a skip link, a keyboard user tabs through the whole header
    on every page before reaching the content. On a four-page site that
    is six stops each time.
    """
    home.open()
    home.page.keyboard.press("Tab")

    focused = home.page.evaluate(
        """() => {
             const el = document.activeElement;
             return el ? (el.textContent || '').trim().toLowerCase() : '';
           }"""
    )
    assert "skip" in focused, (
        f"The first tab stop is {focused!r}, not a skip link"
    )


@pytest.mark.ui
def test_the_skip_link_moves_focus_to_the_content(home):
    """
    TC-51 · NFR4

    A skip link that is present but does not move focus is worse than
    none, because it looks compliant and is not.
    """
    home.open()
    home.page.keyboard.press("Tab")
    home.page.keyboard.press("Enter")

    assert "#" in home.page.url, "The skip link did not move to a target"


@pytest.mark.ui
def test_every_form_field_has_a_label_bound_to_it(apply_page):
    """
    TC-52 · NFR4

    Placeholder text disappears the moment a field is focused and is
    announced inconsistently. A bound label is what tells a screen
    reader what a field is for.
    """
    apply_page.open()

    unlabelled = apply_page.page.evaluate(
        """() => {
             const fields = Array.from(
               document.querySelectorAll(
                 'form input:not([type=hidden]), form select, form textarea'
               )
             );
             return fields
               .filter(f => {
                 if (f.type === 'checkbox' && f.closest('label')) return false;
                 if (f.getAttribute('aria-label')) return false;
                 return !(f.id && document.querySelector(
                   'label[for="' + CSS.escape(f.id) + '"]'
                 ));
               })
               .map(f => f.name || f.id || f.type);
           }"""
    )

    assert unlabelled == [], f"These fields have no bound label: {unlabelled}"


@pytest.mark.ui
def test_the_whole_form_can_be_completed_by_keyboard(apply_page):
    """
    TC-52 · NFR4

    Every control must be reachable by tabbing. A field that can only
    be set with a mouse makes the form impossible for a keyboard user
    to submit.
    """
    apply_page.open()

    reachable = apply_page.page.evaluate(
        """() => {
             const needed = ['fullName','email','phone','guardianEmail',
                             'campus','date','moveIn','notes'];
             return needed.filter(id => {
               const el = document.getElementById(id);
               return el && el.tabIndex >= 0 && !el.disabled;
             }).length;
           }"""
    )
    assert reachable == 8, (
        f"Only {reachable} of the 8 form fields are reachable by keyboard"
    )


@pytest.mark.ui
def test_the_result_count_is_announced_when_it_changes(search):
    """
    TC-53 · NFR4

    Filtering updates the results without a visible page change at the
    top. A live region is what tells a screen-reader user that the
    number of rooms has changed.
    """
    search.open_filtered()
    live = search.results_count_text.get_attribute("aria-live")

    assert live in ("polite", "assertive"), (
        f"The result count has aria-live={live!r}, so a change in the number "
        "of rooms is not announced"
    )


# ======================================================================
# TC-56 — What the system says about itself  (NFR5)
# ======================================================================


@pytest.mark.functional
def test_the_privacy_notice_describes_storage_when_connected(apply_page):
    """
    TC-56 · NFR5, NFR7, BR-12

    With a database connected the notice says details are stored and
    shared with the provider. That is true, and it is the disclosure
    POPIA's openness condition requires at the point of collection.
    """
    apply_page.open()
    notice = apply_page.privacy_hint.inner_text().lower()

    assert "stored" in notice, (
        f"The privacy notice does not mention storage: {notice!r}"
    )
    assert "provider" in notice, (
        "The notice does not say who the details are shared with"
    )


@pytest.mark.functional
@pytest.mark.defect
def test_the_guardian_hint_does_not_promise_an_email(apply_page):
    """
    TC-60 · NFR5 — EXPECTED TO FAIL (Phase 3 §3.6.1)

    The hint beneath the guardian field reads:

        'They receive the same confirmation and lease summary you do.'

    No email is sent by this release. The address is stored so staff can
    include the guardian when they follow up, and nothing more.

    This matters more than its severity suggests. Phase 3 weakness W5 is
    that the parent who pays sees the costs last - so a parent who is
    told they will receive a confirmation, and does not, is failed by
    the exact feature meant to address that weakness.

    Phase 4 §4.7.2 specifies the corrected wording.
    """
    apply_page.open()
    hint = apply_page.guardian_hint.inner_text().lower()

    promises = any(
        phrase in hint
        for phrase in ("receive the same", "receives the same", "they receive")
    )

    assert not promises, (
        f"The guardian hint reads {hint!r}, which promises a confirmation "
        "the system does not send. Phase 3 section 3.6.1; the corrected "
        "wording is specified in Phase 4 section 4.7.2."
    )


# ======================================================================
# TC-59 — The status column  [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.integration
@pytest.mark.defect
def test_the_status_column_only_accepts_its_defined_values(db):
    """
    TC-59 · FR6 — EXPECTED TO FAIL (Phase 3 finding 4)

    status drives the administrative queue and holds one of four
    values: new, contacted, viewed, closed. The column is VARCHAR(20),
    so it accepts anything at all.

    Nothing reads the column yet, which is why this is low severity. It
    stops being low the moment an administrative view is built: a
    request whose status reads 'New' or 'in progress' simply disappears
    from a filtered list, and nobody notices a request nobody can see.

    Phase 4 §4.5.3 specifies ENUM('new','contacted','viewed','closed').
    """
    import pymysql

    row = db.one("SELECT id FROM viewing_requests ORDER BY id DESC LIMIT 1")
    if not row:
        pytest.skip("No request to test against")

    original = db.one(
        "SELECT status FROM viewing_requests WHERE id = %s", (row["id"],)
    )["status"]

    rejected = False
    try:
        db.execute(
            "UPDATE viewing_requests SET status = %s WHERE id = %s",
            ("not-a-real-status", row["id"]),
        )
    except (pymysql.err.DataError, pymysql.err.IntegrityError,
            pymysql.err.OperationalError):
        rejected = True
    finally:
        db.execute(
            "UPDATE viewing_requests SET status = %s WHERE id = %s",
            (original, row["id"]),
        )

    assert rejected, (
        "The status column accepted 'not-a-real-status'. It should hold only "
        "new, contacted, viewed or closed. Phase 3 finding 4; the fix is "
        "specified in Phase 4 section 4.5.3."
    )


# ======================================================================
# TC-54, TC-55 — Running without a database  (FR10)   [OPT-IN]
# ======================================================================


@pytest.fixture
def without_database():
    """
    Move the configuration aside so the site falls back to sample data.

    Restored in a finally block. If a run is interrupted between the two,
    rename includes/config.php.testing-backup back by hand - the message
    below says so.
    """
    if not config.RUN_FALLBACK_TESTS:
        pytest.skip(
            "Fallback tests move the configuration file. Enable with "
            "RF_RUN_FALLBACK=1 when you can watch the run."
        )
    if not os.path.isfile(CONFIG_PATH):
        pytest.skip(f"No configuration file at {CONFIG_PATH}")

    shutil.move(CONFIG_PATH, BACKUP_PATH)
    time.sleep(0.5)          # let any in-flight request finish
    try:
        yield
    finally:
        if os.path.isfile(BACKUP_PATH):
            shutil.move(BACKUP_PATH, CONFIG_PATH)
            time.sleep(0.5)


@pytest.mark.functional
@pytest.mark.parametrize(
    "path",
    [config.HOME, config.SEARCH, config.APPLY],
    ids=["home", "search", "apply"],
)
def test_every_page_still_renders_without_a_database(without_database, path):
    """
    TC-54 · FR10

    The repository is shared between six students. A teammate without
    MySQL running must still be able to open the site, which is why the
    application falls back to the sample arrays rather than failing.
    """
    response = requests.get(config.url(path), timeout=10)

    assert response.status_code == 200, (
        f"{path} returned HTTP {response.status_code} with no database"
    )
    for bad in ("Fatal error", "Warning:", "SQLSTATE", "PDOException"):
        assert bad not in response.text, (
            f"{path} disclosed {bad!r} when the database was unavailable"
        )


@pytest.mark.functional
def test_listings_are_still_shown_without_a_database(without_database, search):
    """
    TC-54 · FR10

    Falling back is only useful if there is something to fall back to.
    The sample arrays must produce a working catalogue, not an empty one.
    """
    search.open_filtered()
    assert search.result_count() > 0, (
        "No listings are shown when the database is unavailable"
    )


@pytest.mark.functional
def test_the_form_validates_but_does_not_claim_storage(
    without_database, apply_page
):
    """
    TC-54, TC-43 · FR10, NFR5

    The companion to the confirmation test in the submission suite. With
    no database the form still validates and still issues a reference,
    but the page must say plainly that nothing was saved.

    Claiming a request had been stored when it had not is the precise
    failure NFR5 exists to prevent - a student would wait for a call
    that nobody knows to make.
    """
    apply_page.open()
    apply_page.fill_valid(email="fallback.test@example.com")
    apply_page.submit_form()

    assert apply_page.submitted_successfully(), (
        "The form did not accept a valid submission without a database"
    )

    fineprint = apply_page.fineprint.inner_text().lower()
    assert "not saved" in fineprint or "no database" in fineprint, (
        f"With no database the confirmation reads {fineprint!r}. It must say "
        "the request was not stored."
    )


@pytest.mark.functional
def test_the_privacy_notice_changes_when_nothing_is_stored(
    without_database, apply_page
):
    """
    TC-56 · NFR5, NFR7

    The notice above the submit button is a promise about what happens
    to the student's data. With no database nothing is transmitted or
    stored, and the wording must follow the behaviour rather than being
    written once and left.
    """
    apply_page.open()
    notice = apply_page.privacy_hint.inner_text().lower()

    assert "no database" in notice or "nothing is transmitted" in notice, (
        f"With no database the privacy notice still reads {notice!r}"
    )
