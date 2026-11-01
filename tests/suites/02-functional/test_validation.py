"""
Viewing-request validation — TC-30 to TC-38.

Verifies FR7 (validate all input server-side) and business rules BR-06
to BR-09. This is the only place the system accepts data a person typed,
so it is the heaviest test surface in the application.

READ THIS BEFORE INTERPRETING THE RESULTS.

Four tests in this file are expected to FAIL against the current build.
They are marked `defect` and each names the finding it proves:

    TC-31  a name longer than the 160-character column is accepted
    TC-37  a request naming no rooms is accepted        (BR-06)
    TC-38  rooms from another campus are accepted       (BR-07)

These are not mistakes in the tests. Each asserts behaviour that Phase 3
§3.4.5 identified as unenforced and Phase 4 §4.6.1 specifies adding.
They are kept deliberately: deleting them would raise the pass rate and
hide three real defects. They are the acceptance criteria for those
fixes - when the corrections are made, these turn green without anything
else turning red.

A note on method. The form is marked `novalidate`, so the browser does
not block submissions and every rule is evaluated by PHP. That is what
makes server-side validation testable through the interface at all; a
form relying on browser validation would need the browser bypassed, as
the SQL injection tests do.
"""

from datetime import date, timedelta

import pytest
from playwright.sync_api import expect

from support import config


# ----------------------------------------------------------------------
# Helpers
# ----------------------------------------------------------------------


def unique_email() -> str:
    """A fresh address per submission, so rows can be found afterwards."""
    import uuid

    return f"test.{uuid.uuid4().hex[:10]}@example.com"


def submit_with(apply_page, db=None, references=None, **overrides):
    """
    Fill the form with valid data, apply overrides, submit.

    Returns the page object. When a submission unexpectedly succeeds -
    which is the point of the defect tests - the reference is recorded
    so the row is removed afterwards rather than left behind.
    """
    apply_page.open()

    rooms = overrides.pop("rooms", None)
    apply_page.fill_valid(**overrides, rooms=rooms)
    apply_page.submit_form()

    if references is not None and apply_page.submitted_successfully():
        references.append(apply_page.reference_text())

    return apply_page


# ======================================================================
# TC-30 — Name, lower bound
# ======================================================================


@pytest.mark.functional
@pytest.mark.parametrize("name", ["", "A", " "], ids=["empty", "one-char", "space"])
def test_a_name_shorter_than_two_characters_is_refused(
    apply_page, db, submitted_references, name
):
    """
    TC-30 · FR7

    A name is how the provider addresses the student when they call. A
    single character is not one, and whitespace is not one either -
    which is why the value is trimmed before it is measured.
    """
    email = unique_email()
    submit_with(
        apply_page,
        references=submitted_references,
        name=name,
        email=email,
    )

    assert not apply_page.submitted_successfully(), (
        f"A name of {name!r} was accepted"
    )
    expect(apply_page.error_for("fullName")).not_to_be_empty()

    if db:
        assert db.one(
            "SELECT id FROM viewing_requests WHERE email = %s", (email,)
        ) is None, "A row was created despite the name being refused"


@pytest.mark.functional
def test_a_two_character_name_is_accepted(apply_page, submitted_references):
    """
    TC-30 · FR7 — the boundary itself.

    Two characters is the minimum, so it must be accepted. Short names
    exist, and refusing them would exclude real people.
    """
    submit_with(
        apply_page,
        references=submitted_references,
        name="Jo",
        email=unique_email(),
    )

    assert apply_page.submitted_successfully(), (
        f"A two-character name was refused: {apply_page.visible_errors()}"
    )


# ======================================================================
# TC-31 — Name, upper bound  [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.functional
@pytest.mark.defect
def test_a_name_longer_than_the_column_is_refused(
    apply_page, db, submitted_references
):
    """
    TC-31 · FR7 — EXPECTED TO FAIL (Phase 3 finding 6)

    full_name is VARCHAR(160). A longer value has to be refused before
    it reaches the database, because the alternatives are both bad: the
    database truncates it silently, or it rejects the insert and the
    student sees a failure they cannot act on.

    Phase 4 §4.6.1 adds a maximum-length check to the validation
    sequence. Until that is implemented this test fails, which is the
    record of the defect.
    """
    email = unique_email()
    long_name = "A" * 200

    submit_with(
        apply_page,
        references=submitted_references,
        name=long_name,
        email=email,
    )

    stored = db.one(
        "SELECT full_name FROM viewing_requests WHERE email = %s", (email,)
    )

    assert not apply_page.submitted_successfully(), (
        "A 200-character name was accepted. full_name holds 160 characters, "
        f"and the stored value is {len(stored['full_name']) if stored else 0} "
        "characters — the rest was lost. Phase 3 finding 6; the fix is "
        "specified in Phase 4 section 4.6.1."
    )


# ======================================================================
# TC-32 — Email
# ======================================================================


@pytest.mark.functional
@pytest.mark.parametrize(
    "email",
    ["not-an-email", "missing@domain", "@example.com", "spaces in@example.com", ""],
    ids=["no-at", "no-tld", "no-local", "spaces", "empty"],
)
def test_an_invalid_email_is_refused(apply_page, submitted_references, email):
    """
    TC-32 · FR7

    The email address is one of only two ways the provider can reach the
    student. An invalid one means the request is received and cannot be
    acted on, which is worse than it being refused.
    """
    apply_page.open()
    apply_page.fill_valid(email="placeholder@example.com")
    apply_page.email.fill(email)
    apply_page.submit_form()

    assert not apply_page.submitted_successfully(), (
        f"{email!r} was accepted as an email address"
    )
    expect(apply_page.error_for("email")).not_to_be_empty()


@pytest.mark.functional
def test_a_valid_email_is_accepted(apply_page, submitted_references):
    """TC-32 · FR7 — the positive case, so the rule is not simply refusing all."""
    submit_with(
        apply_page, references=submitted_references, email="student.one@example.co.za"
    )
    assert apply_page.submitted_successfully(), (
        f"A valid address was refused: {apply_page.visible_errors()}"
    )


# ======================================================================
# TC-33 — Phone  (BR-08)
# ======================================================================


@pytest.mark.functional
@pytest.mark.parametrize(
    "phone",
    ["0821234567", "+27821234567", "082 123 4567", "0721234567", "0611234567"],
    ids=["local", "international", "spaced", "07-prefix", "06-prefix"],
)
def test_valid_south_african_mobile_numbers_are_accepted(
    apply_page, submitted_references, phone
):
    """
    TC-33 · FR7, BR-08

    South African mobile numbers start 06, 07 or 08, locally or with the
    +27 country code. Spaces are stripped before the check, because
    people write their numbers with spaces and refusing that would be
    pedantry rather than validation.
    """
    submit_with(
        apply_page,
        references=submitted_references,
        phone=phone,
        email=unique_email(),
    )

    assert apply_page.submitted_successfully(), (
        f"{phone!r} is a valid South African mobile number but was refused: "
        f"{apply_page.visible_errors()}"
    )


@pytest.mark.functional
@pytest.mark.parametrize(
    "phone",
    ["0123456789", "12345", "082123456789", "08212345", "abcdefghij", ""],
    ids=["landline", "too-short", "too-long", "short-by-one", "letters", "empty"],
)
def test_numbers_that_are_not_sa_mobiles_are_refused(
    apply_page, submitted_references, phone
):
    """
    TC-33 · FR7, BR-08

    012 is a Pretoria landline, not a mobile. The length cases matter
    because a number one digit short looks right at a glance and would
    fail only when someone tried to call it.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.phone.fill(phone)
    apply_page.submit_form()

    assert not apply_page.submitted_successfully(), (
        f"{phone!r} was accepted as a South African mobile number"
    )
    expect(apply_page.error_for("phone")).not_to_be_empty()


# ======================================================================
# TC-34 — Guardian email
# ======================================================================


@pytest.mark.functional
@pytest.mark.integration
def test_a_blank_guardian_email_is_accepted_and_stored_as_null(
    apply_page, db, submitted_references
):
    """
    TC-34 · FR6, FR7

    The guardian address is optional, so blank must be accepted. It must
    also be stored as NULL rather than an empty string - 'no guardian
    named' and 'a guardian named as nothing' are different states, and
    only one of them is true.
    """
    email = unique_email()
    submit_with(
        apply_page, references=submitted_references, email=email, guardian=""
    )

    assert apply_page.submitted_successfully(), (
        f"A blank guardian address was refused: {apply_page.visible_errors()}"
    )

    row = db.one(
        "SELECT guardian_email FROM viewing_requests WHERE email = %s", (email,)
    )
    assert row is not None, "The request was not stored"
    assert row["guardian_email"] is None, (
        f"A blank guardian address was stored as {row['guardian_email']!r} "
        "rather than NULL"
    )


@pytest.mark.functional
def test_an_invalid_guardian_email_is_refused(apply_page, submitted_references):
    """
    TC-34 · FR7

    Optional does not mean unvalidated. A guardian address that is
    present but wrong would be used and would fail silently.
    """
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.guardian_email.fill("not-an-address")
    apply_page.submit_form()

    assert not apply_page.submitted_successfully()
    expect(apply_page.error_for("guardianEmail")).not_to_be_empty()


@pytest.mark.integration
def test_a_valid_guardian_email_is_stored_as_given(
    apply_page, db, submitted_references
):
    """TC-34 · FR6 — the address must survive the round trip unchanged."""
    email = unique_email()
    guardian = "parent.one@example.co.za"

    submit_with(
        apply_page, references=submitted_references, email=email, guardian=guardian
    )
    assert apply_page.submitted_successfully()

    row = db.one(
        "SELECT guardian_email FROM viewing_requests WHERE email = %s", (email,)
    )
    assert row["guardian_email"] == guardian


# ======================================================================
# TC-35 — Preferred date  (BR-09)
# ======================================================================


@pytest.mark.functional
def test_a_date_in_the_past_is_refused(apply_page, submitted_references):
    """
    TC-35 · FR7, BR-09

    A viewing cannot be arranged for yesterday. The rule excludes the
    past, not the present - today is still a valid request.
    """
    yesterday = (date.today() - timedelta(days=1)).isoformat()

    submit_with(
        apply_page,
        references=submitted_references,
        email=unique_email(),
        date=yesterday,
    )

    assert not apply_page.submitted_successfully(), (
        f"A preferred date of {yesterday} was accepted"
    )
    expect(apply_page.error_for("date")).not_to_be_empty()


@pytest.mark.functional
@pytest.mark.parametrize("days", [0, 1, 30], ids=["today", "tomorrow", "next-month"])
def test_today_and_future_dates_are_accepted(apply_page, submitted_references, days):
    """
    TC-35 · BR-09 — the boundary.

    Today must be accepted. A rule written as 'later than today' rather
    than 'not before today' would refuse a student asking to view
    something this afternoon.
    """
    when = (date.today() + timedelta(days=days)).isoformat()

    submit_with(
        apply_page, references=submitted_references, email=unique_email(), date=when
    )

    assert apply_page.submitted_successfully(), (
        f"A preferred date of {when} was refused: {apply_page.visible_errors()}"
    )


@pytest.mark.functional
def test_a_missing_date_is_refused(apply_page, submitted_references):
    """TC-35 · FR7 — the field is required; the provider needs a day to offer."""
    apply_page.open()
    apply_page.fill_valid(email=unique_email())
    apply_page.date.fill("")
    apply_page.submit_form()

    assert not apply_page.submitted_successfully()
    expect(apply_page.error_for("date")).not_to_be_empty()


# ======================================================================
# TC-36 — Notes
# ======================================================================


@pytest.mark.functional
@pytest.mark.parametrize("length", [499, 500], ids=["499", "500-exactly"])
def test_notes_at_or_below_the_limit_are_accepted(
    apply_page, db, submitted_references, length
):
    """
    TC-36 · FR7 — at the boundary.

    The column holds 500 characters, so 500 must be accepted. Refusing
    the limit itself is the classic off-by-one, and it is invisible
    until someone writes exactly that much.
    """
    email = unique_email()
    submit_with(
        apply_page,
        references=submitted_references,
        email=email,
        notes="n" * length,
    )

    assert apply_page.submitted_successfully(), (
        f"{length} characters of notes were refused: {apply_page.visible_errors()}"
    )

    row = db.one("SELECT notes FROM viewing_requests WHERE email = %s", (email,))
    assert row and len(row["notes"]) == length, (
        f"{length} characters were submitted but "
        f"{len(row['notes']) if row and row['notes'] else 0} were stored"
    )


@pytest.mark.functional
def test_notes_above_the_limit_are_refused(apply_page, submitted_references):
    """
    TC-36 · FR7

    501 characters must be refused rather than silently cut to 500 - a
    student would not know the end of their message had been lost.
    """
    submit_with(
        apply_page,
        references=submitted_references,
        email=unique_email(),
        notes="n" * 501,
    )

    assert not apply_page.submitted_successfully(), (
        "501 characters of notes were accepted; the field holds 500"
    )
    expect(apply_page.error_for("notes")).not_to_be_empty()


# ======================================================================
# TC-37 — At least one room  (BR-06)  [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.functional
@pytest.mark.defect
def test_a_request_naming_no_rooms_is_refused(
    apply_page, db, submitted_references
):
    """
    TC-37 · FR6, FR7, BR-06 — EXPECTED TO FAIL (Phase 3 finding 1)

    The single most consequential defect in the application. BR-06 says
    a viewing request must name at least one room. The build does not
    enforce it, so a student can submit a request that names nothing.

    What that produces is a request the campus cannot act on: a name, a
    phone number, a date, and no indication of what the student wants to
    see. The student receives a reference number and waits for a call
    that cannot usefully be made.

    Evidenced in Phase 4 Figure 4.15 - the form shows errors on four
    fields and nothing beside 'Rooms to view'. The fix is specified in
    Phase 4 §4.6.1 as a room-count check before the transaction opens.
    """
    email = unique_email()

    apply_page.open()
    apply_page.fill_valid(email=email, rooms=[])   # explicitly none
    assert apply_page.checked_rooms() == [], "The form pre-selected a room"
    apply_page.submit_form()

    if apply_page.submitted_successfully():
        submitted_references.append(apply_page.reference_text())

    row = db.one(
        "SELECT id, reference FROM viewing_requests WHERE email = %s", (email,)
    )
    rooms = (
        db.query(
            "SELECT listing_slug FROM viewing_request_rooms WHERE request_id = %s",
            (row["id"],),
        )
        if row
        else []
    )

    assert not apply_page.submitted_successfully(), (
        "A request naming no rooms was accepted. "
        f"Reference {row['reference'] if row else '?'} was stored with "
        f"{len(rooms)} rooms attached — the provider has no way to know what "
        "the student wants to view. BR-06; Phase 3 finding 1; the fix is "
        "specified in Phase 4 section 4.6.1."
    )


# ======================================================================
# TC-38 — Rooms must match the campus  (BR-07)  [DEFECT EXPECTED]
# ======================================================================


@pytest.mark.functional
@pytest.mark.defect
def test_rooms_must_belong_to_the_selected_campus(
    apply_page, db, submitted_references
):
    """
    TC-38 · FR6, FR7, BR-07 — EXPECTED TO FAIL (Phase 3 finding 2)

    BR-07 says the rooms named on a request must be at the campus the
    student selected. The build checks the rooms exist, but not where
    they are.

    The consequence is a request that routes to the wrong campus. A
    student studying in Durban asks to view a room in Cape Town, and
    whoever picks the request up has to work out that it is wrong.

    The fix is specified in Phase 4 §4.6.1: resolve each submitted slug
    and compare its campus_id with the submitted campus.
    """
    row = db.one(
        """
        SELECT a.id AS campus_a, b.slug AS room_at_b
          FROM campuses a
          JOIN listings b ON b.campus_id <> a.id AND b.is_active = 1
         LIMIT 1
        """
    )
    if not row:
        pytest.skip("Need listings at two different campuses")

    campus_a, room_at_b = row["campus_a"], row["room_at_b"]
    email = unique_email()

    apply_page.open()
    apply_page.fill_valid(email=email, rooms=[room_at_b])
    apply_page.campus.select_option(campus_a)
    apply_page.submit_form()

    if apply_page.submitted_successfully():
        submitted_references.append(apply_page.reference_text())

    assert not apply_page.submitted_successfully(), (
        f"A request for campus {campus_a!r} naming {room_at_b!r}, which is at "
        "a different campus, was accepted. BR-07; Phase 3 finding 2; the fix "
        "is specified in Phase 4 section 4.6.1."
    )


# ======================================================================
# Cross-cutting behaviour of the validation sequence
# ======================================================================


@pytest.mark.functional
@pytest.mark.ui
def test_every_problem_is_reported_at_once(apply_page):
    """
    FR7 — errors are collected, not thrown.

    Submitting an empty form must report every failing field together.
    Stopping at the first would make the student fix one problem per
    submission, which is how people give up on a form.
    """
    apply_page.open()
    apply_page.submit_form()

    errors = apply_page.visible_errors()
    assert len(errors) >= 3, (
        f"An empty submission reported only {len(errors)} error(s): {errors}. "
        "Every failing field should be reported together."
    )


@pytest.mark.functional
@pytest.mark.ui
def test_the_students_entries_survive_a_refusal(apply_page):
    """
    FR7 — a refused form keeps what was typed.

    Clearing the form because one field failed is the most reliable way
    to lose a submission entirely. Everything valid must come back.
    """
    apply_page.open()
    apply_page.fill_valid(
        name="Thandiwe Mokoena",
        email=unique_email(),
        phone="0821234567",
        notes="Please arrange an afternoon viewing.",
    )
    apply_page.date.fill("")       # the one failure
    apply_page.submit_form()

    assert not apply_page.submitted_successfully()
    assert apply_page.full_name.input_value() == "Thandiwe Mokoena", (
        "The name was cleared when the form was refused"
    )
    assert apply_page.notes.input_value() == (
        "Please arrange an afternoon viewing."
    ), "The notes were cleared when the form was refused"


@pytest.mark.ui
@pytest.mark.functional
def test_a_failing_field_is_marked_invalid(apply_page):
    """
    FR7, NFR4 — the error has to be findable without sight of the layout.

    A red message beside a field is invisible to a screen reader unless
    the field itself is marked.
    """
    apply_page.open()
    apply_page.submit_form()

    assert apply_page.full_name.get_attribute("aria-invalid") == "true", (
        "A field in error does not carry aria-invalid"
    )


@pytest.mark.functional
@pytest.mark.integration
def test_a_refused_submission_stores_nothing(apply_page, db):
    """
    FR7, BR-11 — nothing reaches the database until every rule passes.

    Validation that happens after a partial write leaves rows behind
    that nobody will ever reconcile.
    """
    before = db.request_count()

    apply_page.open()
    apply_page.submit_form()      # entirely empty

    assert not apply_page.submitted_successfully()
    assert db.request_count() == before, (
        "A refused submission changed the number of stored requests"
    )
