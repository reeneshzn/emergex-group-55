"""
End-to-end journeys.

Verifies that the complete path a student takes works from one end to
the other, across several pages, rather than testing each feature in
isolation.

This is the level that catches what the others cannot. Every feature can
work perfectly on its own while the joins between them fail: the search
returns the right rooms, the shortlist saves them, the form accepts a
submission - and the shortlist never reaches the form. Only a test that
walks the whole path asks that question.

These four tests were previously held inside the shortlist and campus
files. They are gathered here because a journey is its own kind of test:
it belongs to no single feature, and reading them together describes how
the system is meant to be used.

    pytest -m e2e
"""

import json
import re
import uuid

import pytest
from playwright.sync_api import expect

from support import config


STORAGE_KEY = "rf-shortlist"


# ----------------------------------------------------------------------
# Helpers
# ----------------------------------------------------------------------


def stored_shortlist(page) -> list[str]:
    """Read the shortlist straight out of the browser's storage."""
    raw = page.evaluate(f"() => window.localStorage.getItem('{STORAGE_KEY}')")
    if not raw:
        return []
    try:
        return json.loads(raw)
    except ValueError:
        return []


def seed_shortlist(page, slugs: list[str]) -> None:
    """Put slugs into storage, then reload so the page reads them."""
    page.evaluate(
        f"(s) => window.localStorage.setItem('{STORAGE_KEY}', JSON.stringify(s))",
        slugs,
    )
    page.reload()


def save_from_search(search, db, slug: str) -> None:
    """
    Shortlist a room through its own campus's results.

    The control lives on the result card, and the search shows one campus
    at a time, so the test opens that room's campus first - which is also
    what a student does.
    """
    row = db.listing(slug)
    assert row is not None, f"{slug} is not in the listings table"

    search.open_filtered(campus=row["campus_id"])
    heart = search.heart(slug)
    assert heart.count(), f"No shortlist control for {slug} on its campus results"
    heart.click()


@pytest.fixture
def journey_campus(db):
    """A campus with at least two rooms, used for a complete journey."""
    row = db.one(
        """
        SELECT campus_id, COUNT(*) AS n
          FROM listings
         WHERE is_active = 1
      GROUP BY campus_id
        HAVING n >= 2
      ORDER BY n DESC, campus_id ASC
         LIMIT 1
        """
    )
    if not row:
        pytest.skip("No campus has two listings")
    return row["campus_id"]


@pytest.fixture
def two_slugs(db, journey_campus):
    """
    Two rooms AT THE SAME CAMPUS.

    BR-07 requires every room on a request to be at the chosen campus, so
    a journey that shortlists rooms from two different campuses cannot
    end in a valid submission - and would not be a journey a student
    could complete either.
    """
    rows = db.query(
        """SELECT slug FROM listings
            WHERE is_active = 1 AND campus_id = %s
         ORDER BY slug LIMIT 2""",
        (journey_campus,),
    )
    return [r["slug"] for r in rows]


# ======================================================================
# Journey 1 — landing page to filtered search
# ======================================================================


@pytest.mark.e2e
@pytest.mark.functional
def test_a_campus_card_opens_the_search_already_filtered(home, search):
    """
    TC-07 · FR1, FR2

    The first step of every journey. A student arrives knowing only which
    campus they attend, and the campus cards are the route from that into
    the catalogue.

    Landing on an unfiltered search would make the card pointless - they
    would have to choose the campus again having just chosen it.
    """
    home.open()
    target = home.campus_ids_shown()[0]

    home.campus_card(target).click()
    home.page.wait_for_load_state("networkidle")

    assert config.SEARCH in home.page.url
    assert search.campus_select.input_value() == target, (
        f"Opened the search for {search.campus_select.input_value()!r}, "
        f"expected {target!r}"
    )


# ======================================================================
# Journey 2 — the shortlist follows the student
# ======================================================================


@pytest.mark.e2e
@pytest.mark.functional
def test_the_shortlist_follows_the_student_between_pages(
    search, listing, apply_page, db, busiest_slug
):
    """
    TC-21 · FR5

    The count lives in the shared header, so it must read the same
    everywhere. A student who saves a room while searching, then opens
    the listing, then the booking form, should see it saved at each step.

    Three pages, one piece of state.
    """
    search.open_filtered()
    search.clear_shortlist()
    save_from_search(search, db, busiest_slug)
    assert search.saved_count() == 1

    listing.open_slug(busiest_slug)
    assert listing.saved_count() == 1, (
        "The shortlist count did not carry across to the listing page"
    )

    apply_page.open()
    assert apply_page.saved_count() == 1, (
        "The shortlist count did not carry across to the booking form"
    )


# ======================================================================
# Journey 3 — shortlist into the request form
# ======================================================================


@pytest.mark.e2e
@pytest.mark.functional
def test_shortlisted_rooms_arrive_preselected_on_the_form(
    search, apply_page, two_slugs
):
    """
    TC-22 · FR5, FR6

    The point of the shortlist. A student who has collected rooms should
    not have to find them again in a list of eighteen on the booking
    form.
    """
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, two_slugs)

    search.open_drawer()
    search.drawer_book_all.click()
    search.page.wait_for_load_state("networkidle")

    assert config.APPLY in search.page.url, "The drawer did not reach the form"

    checked = apply_page.checked_rooms()
    assert sorted(checked) == sorted(two_slugs), (
        f"The form arrived with {sorted(checked)} ticked, expected {sorted(two_slugs)}"
    )


@pytest.mark.e2e
def test_no_other_room_is_preselected(search, apply_page, two_slugs):
    """
    TC-22 · FR6

    Just as important as the right rooms being ticked: nothing else is. A
    stray selection would send a student to view a room they never asked
    about, and they would not notice until they arrived.
    """
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, [two_slugs[0]])

    search.open_drawer()
    search.drawer_book_all.click()
    search.page.wait_for_load_state("networkidle")

    assert apply_page.checked_rooms() == [two_slugs[0]]


# ======================================================================
# Journey 4 — the whole path, search to stored request
# ======================================================================


@pytest.mark.e2e
@pytest.mark.integration
def test_the_complete_journey_from_search_to_a_stored_request(
    search, apply_page, db, journey_campus, two_slugs, submitted_references
):
    """
    FR2, FR5, FR6, BR-10, BR-11 — the whole system in one test.

    A student filters the search, shortlists two rooms, opens the booking
    form from the drawer, completes it and submits. The test then checks
    the database: the request exists with the details typed, and both
    rooms are linked to it.

    Every step here is covered individually elsewhere. This test exists
    because covering each step individually does not prove the path
    works, and the path is what the student actually uses.
    """
    email = f"journey.{uuid.uuid4().hex[:8]}@example.com"
    name = "Journey Test Student"

    # 1 — search, and shortlist two rooms
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, two_slugs)
    assert search.saved_count() == 2

    # 2 — carry them into the booking form
    search.open_drawer()
    search.drawer_book_all.click()
    search.page.wait_for_load_state("networkidle")
    assert sorted(apply_page.checked_rooms()) == sorted(two_slugs)

    # 3 — complete and submit, leaving the rooms as they arrived.
    #     The campus must match the rooms (BR-07). The form pre-selects
    #     the campus of the first shortlisted room, so this is normally
    #     already right - setting it explicitly makes the test say so.
    apply_page.fill_valid(name=name, email=email, rooms=[])
    apply_page.campus.select_option(journey_campus)
    apply_page.submit_form()

    expect(apply_page.success_panel).to_be_visible()
    reference = apply_page.reference_text()
    submitted_references.append(reference)

    assert re.match(config.REFERENCE_PATTERN, reference), (
        f"{reference!r} is not in the agreed RF-YYMMDD-XXXX format"
    )

    # 4 — and prove it reached the database
    request = db.viewing_request(reference)
    assert request is not None, (
        f"The confirmation showed {reference} but no request row exists"
    )
    assert request["full_name"] == name
    assert request["email"] == email
    assert request["status"] == "new"

    rooms = db.rooms_for_request(reference)
    assert sorted(rooms) == sorted(two_slugs), (
        f"The request was stored with {sorted(rooms)}, but the student "
        f"shortlisted {sorted(two_slugs)}"
    )
