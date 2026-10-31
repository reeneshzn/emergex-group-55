"""
Security — TC-44 to TC-49, TC-57 and TC-58.

Verifies FR9 (protect the form against cross-site request forgery),
NFR2 (every query parameterised), NFR8 (output escaped, errors logged
not shown) and NFR5 (the site must not be indexed under Richfield's
name without approval).

TWO TESTS HERE ARE EXPECTED TO FAIL, marked `defect`:

    TC-57  move_in_month is accepted without being checked
    TC-58  campus is accepted without being checked

Both are Phase 3 §3.4.5 finding 3, with the fix specified in Phase 4
§4.6.1.

A NOTE ON METHOD, AND IT MATTERS.

Several tests here post directly to the server with `requests` rather
than driving the browser. That is deliberate, and it is the difference
between a real security test and a reassuring one.

The campus field is a <select>. A browser will only ever submit one of
the options it was given, so testing through the interface proves that
Chromium renders a dropdown correctly - not that the server validates
what it receives. An attacker does not use the dropdown. They post
whatever they like.

The same reasoning applies to the injection tests: the payload has to
reach PHP to prove anything, so it is sent straight to PHP.

    Client-side validation is a usability feature.
    It is never a security control, because the attacker chooses the client.
"""

import re
import uuid

import pytest
import requests
from playwright.sync_api import expect

from support import config


# ----------------------------------------------------------------------
# Posting to the form without a browser
# ----------------------------------------------------------------------


def form_session() -> tuple[requests.Session, str]:
    """
    Open a session against the form and return it with a valid token.

    The session keeps the PHP session cookie, so the token it collects
    is the one the server will expect back.
    """
    session = requests.Session()
    page = session.get(config.url(config.APPLY), timeout=10)

    match = re.search(
        r'name=["\']csrf["\']\s+value=["\']([^"\']+)["\']', page.text
    )
    assert match, "No CSRF token found on the form"
    return session, match.group(1)


def valid_payload(**overrides) -> dict:
    """A submission that should pass every rule, before any overrides."""
    from datetime import date, timedelta

    payload = {
        "fullName": "Security Test",
        "email": f"sec.{uuid.uuid4().hex[:8]}@example.com",
        "phone": "0821234567",
        "guardianEmail": "",
        "campus": config.CONFIRMED_CAMPUS,
        "date": (date.today() + timedelta(days=7)).isoformat(),
        "moveIn": "",          # filled by the caller when it matters
        "notes": "",
        "listings[]": config.KNOWN_LISTING,
    }
    payload.update(overrides)
    return payload


def first_month_option() -> str:
    """The first move-in month the form itself offers."""
    page = requests.get(config.url(config.APPLY), timeout=10).text
    match = re.search(
        r'<select[^>]+id=["\']moveIn["\'][^>]*>.*?<option value=["\']([^"\']+)',
        page,
        re.S,
    )
    return match.group(1) if match else ""


# ======================================================================
# TC-44 — Cross-site request forgery  (FR9)
# ======================================================================


@pytest.mark.security
def test_a_submission_without_a_token_is_refused(db):
    """
    TC-44 · FR9

    The form writes rows to the database. Without a token, any other
    site could post to it from a visitor's browser and create requests
    in their name - which is what cross-site request forgery is.
    """
    before = db.request_count()

    payload = valid_payload(moveIn=first_month_option())
    payload.pop("csrf", None)

    response = requests.post(config.url(config.APPLY), data=payload, timeout=10)

    assert "viewing request sent" not in response.text.lower(), (
        "A submission with no CSRF token was accepted"
    )
    assert db.request_count() == before, (
        "A submission with no CSRF token created a row"
    )


@pytest.mark.security
def test_a_submission_with_the_wrong_token_is_refused(db):
    """
    TC-44 · FR9

    A token that is present but wrong must be refused exactly as a
    missing one is. The comparison is constant-time, so the response
    gives nothing away about how nearly correct the guess was.
    """
    session, _ = form_session()
    before = db.request_count()

    payload = valid_payload(moveIn=first_month_option())
    payload["csrf"] = "0" * 64

    response = session.post(config.url(config.APPLY), data=payload, timeout=10)

    assert "viewing request sent" not in response.text.lower()
    assert db.request_count() == before


@pytest.mark.security
def test_a_submission_with_a_valid_token_is_accepted(db, submitted_references):
    """
    TC-44 · FR9 — the control must not simply refuse everything.

    A CSRF check that rejected valid submissions too would pass the two
    tests above and make the form useless.
    """
    session, token = form_session()

    payload = valid_payload(moveIn=first_month_option())
    payload["csrf"] = token

    response = session.post(config.url(config.APPLY), data=payload, timeout=10)

    match = re.search(r"RF-\d{6}-[0-9A-F]{4}", response.text)
    assert match, (
        "A submission with a valid token was refused, so the control is "
        "blocking legitimate use"
    )
    submitted_references.append(match.group())


@pytest.mark.security
@pytest.mark.ui
def test_removing_the_token_from_the_form_is_refused(apply_page, db):
    """
    TC-44 · FR9

    The same control, exercised the way an attacker in a browser would:
    strip the hidden field out of the DOM and submit.
    """
    before = db.request_count()

    apply_page.open()
    apply_page.fill_valid(email=f"csrf.{uuid.uuid4().hex[:8]}@example.com")
    apply_page.page.evaluate(
        "() => { const t = document.querySelector('input[name=csrf]');"
        " if (t) t.remove(); }"
    )
    apply_page.submit_form()

    assert not apply_page.submitted_successfully(), (
        "The form was accepted after its CSRF token was removed"
    )
    assert db.request_count() == before


# ======================================================================
# TC-45, TC-46 — SQL injection  (NFR2)
# ======================================================================


INJECTION_PAYLOADS = [
    "' OR '1'='1",
    "'; DROP TABLE listings; --",
    "' UNION SELECT NULL, NULL, NULL --",
    "1' OR '1'='1' /*",
    "admin'--",
    "\" OR \"\"=\"",
]


@pytest.mark.security
@pytest.mark.parametrize("payload", INJECTION_PAYLOADS)
def test_the_campus_parameter_resists_injection(db, payload):
    """
    TC-45 · NFR2

    campus goes into a WHERE clause. With parameterised statements the
    payload is compared as a literal string, matches no campus id, and
    the page renders normally.
    """
    before = db.listing_count()

    response = requests.get(
        config.url(config.SEARCH), params={"campus": payload}, timeout=10
    )

    assert response.status_code == 200, (
        f"{payload!r} produced HTTP {response.status_code}"
    )
    for leak in ("SQLSTATE", "SQL syntax", "mysqli", "PDOException", "Warning:"):
        assert leak not in response.text, (
            f"{payload!r} produced a database error containing {leak!r}"
        )
    assert db.listing_count() == before, (
        f"{payload!r} changed the number of listings"
    )
    assert db.table_exists("listings"), f"{payload!r} affected the schema"


@pytest.mark.security
@pytest.mark.parametrize("payload", INJECTION_PAYLOADS[:3])
def test_the_rent_and_amenity_parameters_resist_injection(db, payload):
    """
    TC-45 · NFR2

    Every filter reaches the query, so every filter is a way in. The
    amenity list is the interesting one: it builds an IN() clause whose
    length varies with the input, which is where concatenation is
    tempting.
    """
    before = db.listing_count()

    response = requests.get(
        config.url(config.SEARCH),
        params={
            "campus": config.CONFIRMED_CAMPUS,
            "max": payload,
            "amenity[]": payload,
            "type[]": payload,
        },
        timeout=10,
    )

    assert response.status_code == 200
    assert "SQLSTATE" not in response.text
    assert db.listing_count() == before


@pytest.mark.security
@pytest.mark.parametrize("payload", INJECTION_PAYLOADS)
def test_the_listing_slug_resists_injection(db, payload):
    """
    TC-46 · NFR2

    The slug is a primary key lookup, so an injection here would read
    arbitrary rows. A tautology must not return a listing - it must be
    treated as a slug that does not exist.
    """
    response = requests.get(
        config.url(config.LISTING), params={"slug": payload}, timeout=10
    )

    assert response.status_code == 404, (
        f"{payload!r} returned HTTP {response.status_code} rather than 404"
    )
    assert db.table_exists("listings")


@pytest.mark.security
def test_injection_through_the_form_does_not_reach_the_database(
    db, submitted_references
):
    """
    TC-45 · NFR2

    The write path matters as much as the read path. A payload in a
    text field must be stored as the literal characters typed, not
    executed - and the row count must move by exactly one.
    """
    session, token = form_session()
    before = db.request_count()

    payload = valid_payload(moveIn=first_month_option())
    payload["csrf"] = token
    payload["fullName"] = "Robert'); DROP TABLE viewing_requests; --"

    response = session.post(config.url(config.APPLY), data=payload, timeout=10)

    assert db.table_exists("viewing_requests"), (
        "The viewing_requests table no longer exists"
    )

    match = re.search(r"RF-\d{6}-[0-9A-F]{4}", response.text)
    if match:
        submitted_references.append(match.group())
        stored = db.viewing_request(match.group())
        assert stored["full_name"] == payload["fullName"], (
            "The payload was altered on the way in, which suggests it was "
            "not treated as a plain value"
        )
        assert db.request_count() == before + 1


# ======================================================================
# TC-47 — Output escaping  (NFR8)
# ======================================================================


@pytest.mark.security
def test_a_script_in_a_form_field_is_rendered_as_text(apply_page):
    """
    TC-47 · NFR8

    The redisplayed form echoes back what the student typed. If that is
    not escaped, anything they can be persuaded to paste becomes code -
    which is how a reflected cross-site scripting attack works.
    """
    payload = "<script>window.__xss = true;</script>"

    apply_page.open()
    apply_page.fill_valid(name=payload)
    apply_page.date.fill("")           # force a refusal so it redisplays
    apply_page.submit_form()

    executed = apply_page.page.evaluate("() => window.__xss === true")
    assert not executed, "A script submitted in the name field executed"

    assert apply_page.full_name.input_value() == payload, (
        "The payload came back altered rather than escaped as text"
    )


@pytest.mark.security
@pytest.mark.integration
def test_a_script_stored_in_a_listing_name_is_rendered_as_text(db, listing):
    """
    TC-47 · NFR8

    Stored cross-site scripting is the more serious form: the payload
    sits in the database and runs for every visitor who views the page,
    not only for the person who was tricked into submitting it.
    """
    row = db.one("SELECT slug, name FROM listings WHERE is_active = 1 LIMIT 1")
    slug, original = row["slug"], row["name"]
    payload = "<script>window.__stored_xss = true;</script>"

    try:
        db.execute("UPDATE listings SET name = %s WHERE slug = %s", (payload, slug))
        listing.open_slug(slug)

        executed = listing.page.evaluate("() => window.__stored_xss === true")
        assert not executed, (
            "A script stored in a listing name executed when the page was viewed"
        )
        assert payload in listing.page.content() or "&lt;script&gt;" in (
            listing.page.content()
        ), "The payload neither executed nor appeared as text"
    finally:
        db.execute("UPDATE listings SET name = %s WHERE slug = %s", (original, slug))


# ======================================================================
# TC-48 — Error responses disclose nothing  (NFR8)
# ======================================================================


@pytest.mark.security
@pytest.mark.parametrize(
    "path, params",
    [
        (config.LISTING, {"slug": "no-such-room"}),
        (config.SEARCH, {"campus": "no-such-campus"}),
        (config.SEARCH, {"max": "not-a-number"}),
        ("does-not-exist.php", {}),
    ],
    ids=["unknown-slug", "unknown-campus", "bad-rent", "missing-page"],
)
def test_no_response_leaks_internal_detail(path, params):
    """
    TC-48 · NFR8

    A file path tells an attacker where the application lives on disk.
    A SQL error tells them the shape of the query. A stack trace tells
    them the framework and version. None of it helps the student.
    """
    response = requests.get(config.url(path), params=params, timeout=10)
    body = response.text

    for leak in (
        "C:\\xampp",
        "/var/www",
        "Stack trace",
        "SQLSTATE",
        "PDOException",
        "Fatal error",
        "Warning:",
        "Notice:",
        "Deprecated:",
        "mysqli",
    ):
        assert leak not in body, (
            f"{path} with {params} disclosed {leak!r} in the response"
        )


# ======================================================================
# TC-49 — The site is not indexed  (NFR5)
# ======================================================================


@pytest.mark.security
@pytest.mark.regression
@pytest.mark.parametrize(
    "path",
    [config.HOME, config.SEARCH, config.APPLY],
    ids=["home", "search", "apply"],
)
def test_every_page_asks_not_to_be_indexed(path):
    """
    TC-49 · NFR5

    The site carries Richfield's identity without their approval. If a
    search engine indexes it, that identity is published - which is
    exactly what the project undertook not to do until the client signs
    it off.
    """
    response = requests.get(config.url(path), timeout=10)
    match = re.search(
        r'<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)',
        response.text,
        re.I,
    )

    assert match, f"{path} carries no robots directive"
    assert "noindex" in match.group(1).lower(), (
        f"{path} has robots={match.group(1)!r}, which permits indexing"
    )


@pytest.mark.regression
def test_the_listing_page_also_asks_not_to_be_indexed():
    """TC-49 · NFR5 — checked separately because it needs a slug."""
    response = requests.get(
        config.url(config.LISTING),
        params={"slug": config.KNOWN_LISTING},
        timeout=10,
    )
    assert "noindex" in response.text.lower()


# ======================================================================
# TC-57 — The move-in month is validated   [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.security
@pytest.mark.defect
def test_the_move_in_month_is_checked_against_the_offered_range(
    db, submitted_references
):
    """
    TC-57 · FR7 — EXPECTED TO FAIL (Phase 3 finding 3)

    moveIn is a <select> offering the next twelve months, so a browser
    can only submit one of those. The server does not check, which means
    anything posted directly is stored.

    This is why the test posts rather than clicks: through the interface
    it would pass, and prove only that the dropdown renders.

    Phase 4 §4.6.1 specifies validating the value against the generated
    month list.
    """
    session, token = form_session()

    payload = valid_payload(moveIn="1999-01")   # long past, never offered
    payload["csrf"] = token
    payload["email"] = f"month.{uuid.uuid4().hex[:8]}@example.com"

    response = session.post(config.url(config.APPLY), data=payload, timeout=10)
    match = re.search(r"RF-\d{6}-[0-9A-F]{4}", response.text)

    if match:
        submitted_references.append(match.group())
        stored = db.viewing_request(match.group())

    assert not match, (
        "A move-in month of '1999-01' was accepted and stored as "
        f"{stored['move_in_month']!r}. The form offers only the next twelve "
        "months, so no legitimate submission can contain this. Phase 3 "
        "finding 3; the fix is specified in Phase 4 section 4.6.1."
    )


# ======================================================================
# TC-58 — The campus is validated   [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.security
@pytest.mark.defect
def test_the_campus_is_checked_against_the_campus_table(db, submitted_references):
    """
    TC-58 · FR7, BR-04 — EXPECTED TO FAIL (Phase 3 finding 3)

    campus is also a <select>, and also unchecked. A value that is not a
    campus should be refused with a message beside the field.

    The foreign key will stop a genuinely invalid id from being written,
    but that produces a database error rather than a validation message
    - the student sees a failure they cannot act on. The check belongs
    in the validation sequence, which is what Phase 4 §4.6.1 specifies.
    """
    session, token = form_session()

    payload = valid_payload(moveIn=first_month_option())
    payload["csrf"] = token
    payload["campus"] = "not-a-real-campus"
    payload["email"] = f"campus.{uuid.uuid4().hex[:8]}@example.com"

    response = session.post(config.url(config.APPLY), data=payload, timeout=10)
    body = response.text.lower()

    match = re.search(r"RF-\d{6}-[0-9A-F]{4}", response.text)
    if match:
        submitted_references.append(match.group())

    assert match is None, (
        "A campus of 'not-a-real-campus' was accepted and stored. "
        "Phase 3 finding 3; the fix is specified in Phase 4 section 4.6.1."
    )

    # Being refused is not enough. There are two ways to refuse it, and
    # only one is correct:
    #
    #   validated   - "Choose a campus from the list", beside the field,
    #                 with the student's other entries preserved
    #   not handled - the insert reaches the foreign key, throws, and the
    #                 student is told the request could not be saved,
    #                 with no indication of what to change
    #
    # The second is what an unvalidated campus produces, so the presence
    # of the generic failure message is the evidence of the defect.
    generic_failure = "could not save your request" in body

    assert not generic_failure, (
        "An invalid campus was refused, but by the database rather than by "
        "validation: the student is told the request 'could not be saved' "
        "with no indication of what to change. The value is never offered "
        "by the form, so it should be caught in the validation sequence and "
        "reported beside the campus field. Phase 3 finding 3; the fix is "
        "specified in Phase 4 section 4.6.1."
    )
