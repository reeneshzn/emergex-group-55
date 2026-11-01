"""
Submission and persistence — TC-39 to TC-43.

Verifies FR6 (accept a viewing request and persist it), BR-10 (each
request gets a unique server-generated reference) and BR-11 (a request
and its rooms are saved together or not at all), plus the honesty
requirement NFR5 as it applies to the confirmation page.

This is the system's only write path. Everything else reads.

The distinction that matters here is between what the page SAYS and what
the database HOLDS. A confirmation reading 'your request has been saved'
proves nothing on its own - the page would print the same words if the
insert had silently failed. Every test in this file that claims
something was stored goes and looks.
"""

import re
import uuid

import pytest
from playwright.sync_api import expect

from support import config


def unique_email() -> str:
    return f"test.{uuid.uuid4().hex[:10]}@example.com"


# ======================================================================
# TC-39 — The reference
# ======================================================================


@pytest.mark.functional
def test_a_successful_submission_returns_a_reference(
    apply_page, submitted_references
):
    """
    TC-39 · FR6, BR-10

    The reference is the only thing the student leaves with. It is how
    they identify their request if they phone about it, so it has to be
    shown clearly and be in the agreed format.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.submit_form()

    expect(apply_page.success_panel).to_be_visible()
    reference = apply_page.reference_text()
    submitted_references.append(reference)

    assert re.match(config.REFERENCE_PATTERN, reference), (
        f"{reference!r} does not match the agreed format RF-YYMMDD-XXXX"
    )


@pytest.mark.functional
def test_the_reference_carries_todays_date(apply_page, submitted_references):
    """
    TC-39 · BR-10

    The date portion lets staff see at a glance how old a request is
    without opening it. A wrong date would make the reference
    misleading rather than merely opaque.
    """
    from datetime import date

    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.submit_form()

    reference = apply_page.reference_text()
    submitted_references.append(reference)

    assert reference[3:9] == date.today().strftime("%y%m%d"), (
        f"{reference} does not carry today's date "
        f"({date.today().strftime('%y%m%d')})"
    )


@pytest.mark.integration
def test_the_reference_shown_is_the_reference_stored(
    apply_page, db, submitted_references
):
    """
    TC-39 · FR6, BR-10

    If the page shows one reference and the database holds another, the
    student quotes a number nobody can find.
    """
    email = unique_email()
    apply_page.open()
    apply_page.fill_valid(email=email)
    apply_page.submit_form()

    shown = apply_page.reference_text()
    submitted_references.append(shown)

    row = db.one(
        "SELECT reference FROM viewing_requests WHERE email = %s", (email,)
    )
    assert row is not None, "The request was not stored at all"
    assert row["reference"] == shown, (
        f"The page showed {shown} but the database holds {row['reference']}"
    )


# ======================================================================
# TC-40 — Both tables, or neither  (BR-11)
# ======================================================================


@pytest.mark.integration
def test_the_request_is_stored_with_every_field_as_submitted(
    apply_page, db, submitted_references
):
    """
    TC-40 · FR6

    Each field must survive the round trip. A transposed phone number
    or a lost move-in month is not visible on the confirmation page -
    only a query shows it.
    """
    email = unique_email()
    name = "Lerato Dlamini"
    phone = "0821234567"
    notes = "Ground floor if possible."

    apply_page.open()
    apply_page.fill_valid(name=name, email=email, phone=phone, notes=notes)
    campus = apply_page.campus.input_value()
    move_in = apply_page.move_in.input_value()
    preferred = apply_page.date.input_value()
    apply_page.submit_form()

    submitted_references.append(apply_page.reference_text())

    row = db.one("SELECT * FROM viewing_requests WHERE email = %s", (email,))
    assert row is not None, "The request was not stored"

    assert row["full_name"] == name
    assert row["email"] == email
    assert row["phone"].replace(" ", "") == phone.replace(" ", "")
    assert row["campus_id"] == campus
    assert row["move_in_month"] == move_in
    assert str(row["preferred_date"]) == preferred
    assert row["notes"] == notes


@pytest.mark.integration
def test_every_named_room_is_linked_to_the_request(
    apply_page, db, submitted_references
):
    """
    TC-40 · FR6, BR-11

    The rooms are the substance of the request. A request stored without
    them is the BR-06 defect; a request stored with only some of them
    would be worse, because it looks complete.
    """
    # The rooms must share a campus. BR-07 requires every room on a
    # request to be at the chosen campus, so a selection spread across
    # the catalogue is not a request a student could submit.
    campus = db.one(
        """
        SELECT campus_id, COUNT(*) AS n
          FROM listings
         WHERE is_active = 1
      GROUP BY campus_id
      ORDER BY n DESC, campus_id ASC
         LIMIT 1
        """
    )["campus_id"]

    rooms = [
        r["slug"]
        for r in db.query(
            """SELECT slug FROM listings
                WHERE is_active = 1 AND campus_id = %s
             ORDER BY slug LIMIT 3""",
            (campus,),
        )
    ]
    if len(rooms) < 2:
        pytest.skip("Need at least two rooms at one campus")

    email = unique_email()

    apply_page.open()
    apply_page.fill_valid(email=email, rooms=rooms)
    apply_page.campus.select_option(campus)
    assert sorted(apply_page.checked_rooms()) == sorted(rooms)
    apply_page.submit_form()

    reference = apply_page.reference_text()
    submitted_references.append(reference)

    linked = db.rooms_for_request(reference)
    assert sorted(linked) == sorted(rooms), (
        f"Submitted {sorted(rooms)} but {sorted(linked)} were stored"
    )


@pytest.mark.integration
def test_a_request_and_its_rooms_are_written_together(
    apply_page, db, submitted_references
):
    """
    TC-40 · BR-11

    The two inserts happen inside one transaction, so the pair is
    atomic. This checks the committed result: a request row exists and
    its room rows exist, with no orphan on either side.
    """
    email = unique_email()
    apply_page.open()
    apply_page.fill_valid(email=email)
    apply_page.submit_form()

    reference = apply_page.reference_text()
    submitted_references.append(reference)

    request = db.viewing_request(reference)
    rooms = db.rooms_for_request(reference)

    assert request is not None, "A reference was shown but no request row exists"
    assert rooms, "The request was stored without the room it named"

    orphans = db.query(
        """
        SELECT r.request_id
          FROM viewing_request_rooms r
     LEFT JOIN viewing_requests v ON v.id = r.request_id
         WHERE v.id IS NULL
        """
    )
    assert orphans == [], (
        f"{len(orphans)} room rows reference a request that does not exist"
    )


# ======================================================================
# TC-41 — References are unique
# ======================================================================


@pytest.mark.integration
def test_successive_requests_get_different_references(
    apply_page, submitted_references
):
    """
    TC-41 · BR-10

    The reference is a date plus three random bytes, so a clash is
    unlikely rather than impossible. Three submissions in quick
    succession must produce three different references.
    """
    seen = []
    for _ in range(3):
        apply_page.open()
        apply_page.fill_valid(email=unique_email())
        apply_page.submit_form()
        reference = apply_page.reference_text()
        seen.append(reference)
        submitted_references.append(reference)

    assert len(set(seen)) == 3, f"Duplicate references issued: {seen}"


@pytest.mark.integration
def test_the_database_refuses_a_duplicate_reference(db, submitted_references):
    """
    TC-41 · BR-10

    Uniqueness must be enforced by the schema, not only by the code that
    generates the value. A UNIQUE constraint is what makes the guarantee
    hold if a second process ever writes to the table.
    """
    import pymysql

    row = db.one(
        "SELECT reference, campus_id FROM viewing_requests ORDER BY id DESC LIMIT 1"
    )
    if not row:
        pytest.skip("No existing request to clash with")

    with pytest.raises(pymysql.err.IntegrityError):
        db.execute(
            """
            INSERT INTO viewing_requests
                (reference, full_name, email, phone, campus_id,
                 preferred_date, move_in_month, status)
            VALUES (%s, 'Duplicate Test', 'dup@example.com', '0820000000',
                    %s, CURDATE(), '2026-12', 'new')
            """,
            (row["reference"], row["campus_id"]),
        )


# ======================================================================
# TC-42 — Defaults set by the server
# ======================================================================


@pytest.mark.integration
def test_a_new_request_starts_with_the_new_status(
    apply_page, db, submitted_references
):
    """
    TC-42 · FR6

    Status drives the administrative queue. A request arriving as
    anything other than 'new' would be skipped by whoever works through
    the list.
    """
    email = unique_email()
    apply_page.open()
    apply_page.fill_valid(email=email)
    apply_page.submit_form()
    submitted_references.append(apply_page.reference_text())

    row = db.one(
        "SELECT status FROM viewing_requests WHERE email = %s", (email,)
    )
    assert row["status"] == "new", (
        f"A new request was stored with status {row['status']!r}"
    )


@pytest.mark.integration
def test_the_created_timestamp_is_set_by_the_server(
    apply_page, db, submitted_references
):
    """
    TC-42 · FR6, NFR7

    created_at is set by the database rather than the browser, so it
    cannot be influenced by the client. It is also the basis for the
    POPIA retention period that Phase 4 §4.8.3 records as outstanding -
    a wrong timestamp would mean data kept too long or deleted early.
    """
    email = unique_email()
    apply_page.open()
    apply_page.fill_valid(email=email)
    apply_page.submit_form()
    submitted_references.append(apply_page.reference_text())

    row = db.one(
        """
        SELECT created_at,
               ABS(TIMESTAMPDIFF(MINUTE, created_at, NOW())) AS drift
          FROM viewing_requests WHERE email = %s
        """,
        (email,),
    )
    assert row["created_at"] is not None, "No creation timestamp was stored"
    assert row["drift"] <= 5, (
        f"created_at is {row['drift']} minutes from now, which suggests it "
        "was not set by the server"
    )


# ======================================================================
# TC-43 — The confirmation tells the truth
# ======================================================================


@pytest.mark.functional
@pytest.mark.integration
def test_the_confirmation_claims_storage_only_when_a_row_exists(
    apply_page, db, submitted_references
):
    """
    TC-43 · FR10, NFR5

    With a database connected the page says the request has been saved.
    That claim must be true, so the test checks the row is there.

    The companion case - that the page says the opposite when there is
    no database - is covered in the fallback suite, because it requires
    the configuration to be removed.
    """
    email = unique_email()
    apply_page.open()
    apply_page.fill_valid(email=email)
    apply_page.submit_form()

    reference = apply_page.reference_text()
    submitted_references.append(reference)

    fineprint = apply_page.fineprint.inner_text().lower()
    stored = db.viewing_request(reference) is not None

    if "saved" in fineprint and "not" not in fineprint:
        assert stored, (
            f"The page says the request was saved, but no row exists for "
            f"{reference}. Claiming storage that did not happen is the "
            "failure NFR5 exists to prevent."
        )
    else:
        assert not stored, (
            f"The page says the request was NOT saved, but {reference} is in "
            "the database. The wording must follow what actually happened."
        )


@pytest.mark.ui
def test_the_confirmation_explains_what_happens_next(
    apply_page, submitted_references
):
    """
    TC-43 · FR6

    A reference number on its own leaves the student unsure whether to
    wait or chase. The panel sets the expectation: the provider makes
    contact, and roughly when.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.submit_form()
    submitted_references.append(apply_page.reference_text())

    body = apply_page.success_panel.inner_text().lower()
    assert "provider" in body or "contact" in body, (
        "The confirmation does not say who will make contact"
    )


@pytest.mark.ui
@pytest.mark.functional
def test_the_confirmation_is_announced_to_assistive_technology(
    apply_page, submitted_references
):
    """
    TC-43 · NFR4

    The page changes from a form to a confirmation without a visible
    heading change at the top. role="status" is what tells a screen
    reader that something important has appeared.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.submit_form()
    submitted_references.append(apply_page.reference_text())

    assert apply_page.success_panel.get_attribute("role") == "status", (
        "The confirmation panel is not announced to assistive technology"
    )


@pytest.mark.functional
def test_another_request_can_be_started_from_the_confirmation(
    apply_page, submitted_references
):
    """
    TC-43 · FR6

    A student viewing rooms at two campuses needs two requests, because
    a provider arranges viewings for its own rooms. The route back must
    exist and must produce a clean form.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.submit_form()
    submitted_references.append(apply_page.reference_text())

    apply_page.page.get_by_role(
        "link", name=re.compile("send another", re.I)
    ).click()
    apply_page.page.wait_for_load_state("networkidle")

    expect(apply_page.full_name).to_be_visible()
    assert apply_page.full_name.input_value() == "", (
        "The new form still holds the previous request's details"
    )
