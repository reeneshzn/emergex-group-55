"""
Shortlist — TC-19 to TC-22.

Verifies FR5 (shortlist up to four listings, kept across page reloads)
and BR-05 (a student may shortlist at most four).

The shortlist is held in the browser's localStorage rather than on the
server. That is a deliberate design decision, not an oversight: with no
student accounts there is no stable identity to key a server-side
shortlist to, and issuing a tracking identifier purely to support a
convenience feature would collect personal data the system otherwise
avoids (NFR7, BR-12).

One consequence is worth stating, because the application's own source
flags it: the four-item cap is enforced in the browser only. That is
sufficient while the shortlist never reaches the server, but it would
have to be enforced server-side the day accounts are added. The
shortlists table already exists in the schema for that.
"""

import pytest
from playwright.sync_api import expect

from support import config


STORAGE_KEY = "rf-shortlist"


@pytest.fixture
def four_slugs(db):
    """
    Five listing slugs, so the four-item cap can be exceeded.

    They need not share a campus. The shortlist is held in the browser
    and persists across pages, so a room can be saved from its own
    detail page - which is also how a student actually fills a
    shortlist, a room at a time while browsing.
    """
    rows = db.query(
        "SELECT slug FROM listings WHERE is_active = 1 ORDER BY slug LIMIT 5"
    )
    if len(rows) < 5:
        pytest.skip("Need at least five listings to test the cap")
    return [r["slug"] for r in rows]


def seed_shortlist(page, slugs: list[str]) -> None:
    """Put slugs straight into storage, then reload so the page reads them."""
    page.evaluate(
        f"(s) => window.localStorage.setItem('{STORAGE_KEY}', JSON.stringify(s))",
        slugs,
    )
    page.reload()


def save_from_search(search, db, slug: str) -> None:
    """
    Toggle a room's shortlist control through its own campus's results.

    The control lives on the result card, not on the listing detail
    page, and the search shows one campus at a time. So to reach a
    particular room the test opens the search for that room's campus
    first - which is also what a student does.

    If the card is not on the page, fail with what was there instead.
    A bare click timeout says only that something was not found.
    """
    row = db.listing(slug)
    assert row is not None, f"{slug} is not in the listings table"

    campus = row["campus_id"]
    search.open_filtered(campus=campus)

    heart = search.heart(slug)
    if heart.count() == 0:
        raise AssertionError(
            f"No shortlist control for {slug!r} on the {campus} results.\n"
            f"  rent: R{row['rent']}, active: {row['is_active']}\n"
            f"  cards on the page: {search.card_names()}"
        )

    heart.scroll_into_view_if_needed()
    heart.click()


def stored_shortlist(page) -> list[str]:
    """Read the shortlist straight out of the browser's storage."""
    raw = page.evaluate(f"() => window.localStorage.getItem('{STORAGE_KEY}')")
    if not raw:
        return []
    import json

    try:
        return json.loads(raw)
    except ValueError:
        return []


# ======================================================================
# TC-19 — Adding a room
# ======================================================================


@pytest.mark.functional
@pytest.mark.ui
def test_a_room_can_be_shortlisted_from_the_search_results(search, db, busiest_slug):
    """
    TC-19 · FR5

    Shortlisting is how a student collects rooms worth viewing before
    committing to a request. It has to work from the results, which is
    where they are comparing.
    """
    search.open_filtered()
    search.clear_shortlist()

    assert search.saved_count() == 0, "The shortlist did not start empty"

    save_from_search(search, db, busiest_slug)

    expect(search.shortlist_count).to_have_text("1")
    assert stored_shortlist(search.page) == [busiest_slug]


@pytest.mark.functional
def test_shortlisting_again_removes_the_room(search, db, busiest_slug):
    """
    TC-19 · FR5

    The control is a toggle, not an add button. A student who changes
    their mind must be able to undo it from the same place.
    """
    search.open_filtered()
    search.clear_shortlist()

    save_from_search(search, db, busiest_slug)
    assert search.saved_count() == 1

    search.heart(busiest_slug).click()   # already on the right campus
    assert search.saved_count() == 0
    assert stored_shortlist(search.page) == []


@pytest.mark.ui
def test_the_control_reports_its_state_to_assistive_technology(
    search, db, busiest_slug
):
    """
    TC-19 · FR5, NFR4

    The heart is a toggle, so it carries aria-pressed. Without it a
    screen-reader user cannot tell whether a room is already saved -
    the visual fill means nothing to them.
    """
    search.open_filtered()
    search.clear_shortlist()
    search.open_filtered(campus=db.listing(busiest_slug)["campus_id"])

    heart = search.heart(busiest_slug)
    assert heart.get_attribute("aria-pressed") == "false"

    heart.click()
    expect(heart).to_have_attribute("aria-pressed", "true")


# ======================================================================
# TC-20 — The four-item cap
# ======================================================================


@pytest.mark.functional
def test_the_shortlist_stops_at_four(search, db, four_slugs):
    """
    TC-20 · FR5, BR-05

    The cap exists because the shortlist feeds a viewing request, and a
    request naming a dozen rooms is not one a provider can arrange in a
    single visit.

    Four must be accepted and the fifth refused - and refused quietly
    rather than by breaking.

    Rooms are saved through their own campus's results, because the
    listings are spread across eight campuses and the search shows one
    at a time. The shortlist persists across pages, which is exactly how
    a student fills it while browsing.
    """
    search.open_filtered()
    search.clear_shortlist()

    for slug in four_slugs[:4]:
        save_from_search(search, db, slug)

    assert search.saved_count() == config.SHORTLIST_LIMIT, (
        f"Expected four rooms saved, got {search.saved_count()}"
    )

    # The fifth control is not merely ignored - the application disables
    # it once the shortlist is full, so the student is told why rather
    # than clicking something that silently does nothing.
    fifth = four_slugs[4]
    search.open_filtered(campus=db.listing(fifth)["campus_id"])
    heart = search.heart(fifth)

    assert heart.is_disabled(), (
        f"The control for {fifth} is still enabled although the shortlist "
        f"holds {config.SHORTLIST_LIMIT} rooms"
    )

    title = (heart.get_attribute("title") or "").lower()
    assert "full" in title or "maximum" in title, (
        f"The disabled control gives no reason: title={title!r}. A control "
        "that cannot be used must say why."
    )

    saved = stored_shortlist(search.page)
    assert len(saved) == config.SHORTLIST_LIMIT
    assert fifth not in saved, (
        "The fifth room reached storage even though the shortlist was full"
    )


@pytest.mark.ui
@pytest.mark.functional
def test_controls_are_enabled_again_once_there_is_space(search, db, four_slugs):
    """
    TC-20 · FR5, BR-05

    The other half of the behaviour above. Disabling the controls when
    full is only correct if they come back when a room is removed -
    otherwise a student who fills the shortlist can never add a
    different room.
    """
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, four_slugs[:4])

    fifth = four_slugs[4]
    search.open_filtered(campus=db.listing(fifth)["campus_id"])
    assert search.heart(fifth).is_disabled(), "Expected it to start disabled"

    # Free a slot by unsaving one of the four.
    save_from_search(search, db, four_slugs[0])
    assert search.saved_count() == 3

    search.open_filtered(campus=db.listing(fifth)["campus_id"])
    assert not search.heart(fifth).is_disabled(), (
        "The control is still disabled although the shortlist has space"
    )


@pytest.mark.functional
def test_removing_one_makes_room_for_another(search, db, four_slugs):
    """
    TC-20 · BR-05

    The cap must be a limit on how many are held, not a lifetime total.
    A student who fills the shortlist and then changes their mind about
    one room has to be able to swap it.
    """
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, four_slugs[:4])
    assert search.saved_count() == 4

    # Remove one through its own campus results.
    save_from_search(search, db, four_slugs[0])   # toggles it back off
    assert search.saved_count() == 3, "Removing a room did not free a slot"

    save_from_search(search, db, four_slugs[4])
    assert search.saved_count() == 4, (
        "A room was refused even though the shortlist had space"
    )
    assert four_slugs[4] in stored_shortlist(search.page)


# ======================================================================
# TC-21 — It survives navigation
# ======================================================================


@pytest.mark.functional
def test_the_shortlist_survives_a_reload(search, db, busiest_slug):
    """
    TC-21 · FR5

    A student comparing rooms will reload, go back, and open things in
    new tabs. Losing the shortlist each time would make it useless.
    """
    search.open_filtered()
    search.clear_shortlist()

    save_from_search(search, db, busiest_slug)
    assert search.saved_count() == 1

    search.page.reload()
    assert search.saved_count() == 1, "The shortlist was lost on reload"
    assert stored_shortlist(search.page) == [busiest_slug]


@pytest.mark.ui
def test_a_saved_room_shows_as_saved_when_seen_again(search, db, busiest_slug):
    """
    TC-21 · FR5, NFR4

    Returning to the results after saving a room, its control must show
    the saved state. Without that a student cannot tell which rooms
    they have already collected and may try to save one twice.
    """
    search.open_filtered()
    search.clear_shortlist()
    save_from_search(search, db, busiest_slug)

    # Come back to the same results fresh.
    search.open_filtered(campus=db.listing(busiest_slug)["campus_id"])

    assert search.heart(busiest_slug).get_attribute("aria-pressed") == "true", (
        "A saved room does not show as saved when the page is revisited"
    )


@pytest.mark.functional
def test_clearing_the_shortlist_empties_it_everywhere(search, four_slugs):
    """
    TC-21 · FR5

    The drawer offers a clear action. It must empty the stored list, not
    just hide the drawer contents.
    """
    search.open_filtered()
    search.clear_shortlist()
    seed_shortlist(search.page, four_slugs[:2])
    assert search.saved_count() == 2

    search.open_drawer()
    search.drawer_clear.click()

    assert search.saved_count() == 0
    assert stored_shortlist(search.page) == [], (
        "The drawer was cleared but the stored shortlist remains"
    )


# ======================================================================
# TC-22 — It carries into the viewing request
# ======================================================================
#
# The two tests that walk the shortlist through to the booking form live
# in 06-e2e/test_journeys.py, because they cross three pages and belong
# to the journey rather than to this feature. The case below stays here:
# it checks what the form does with an EMPTY shortlist, which is about
# the form rather than about a journey.


@pytest.mark.functional
def test_an_empty_shortlist_preselects_nothing(apply_page):
    """
    TC-22 · FR6

    A student who comes straight to the form without shortlisting
    anything should find it blank, not pre-filled with a guess.
    """
    apply_page.open()
    apply_page.clear_shortlist()
    apply_page.page.reload()

    assert apply_page.checked_rooms() == [], (
        "Rooms were preselected although nothing was shortlisted"
    )
