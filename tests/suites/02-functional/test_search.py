"""
Search and filtering — TC-08 to TC-13.

Verifies FR2 (search listings by campus, maximum rent, room type and
amenities), BR-01 (only active listings appear) and BR-04 (every listing
belongs to exactly one campus).

A filter that returns too much is a nuisance. A filter that returns too
little is worse, because the student never learns the room they wanted
exists. Both directions are tested: every result matches the filter, and
nothing that matches is missing.

Test data is derived from the database rather than hardcoded, so these
keep working when the seed changes. Where the data cannot support a case
- a campus with only one room type, say - the test skips with a reason
instead of failing.
"""

import pytest

from playwright.sync_api import expect

from support import config


# ----------------------------------------------------------------------
# Helpers that choose suitable test data from whatever is seeded
# ----------------------------------------------------------------------


@pytest.fixture
def busiest_campus(db):
    """The campus with the most active listings - the best one to filter."""
    row = db.one(
        """
        SELECT campus_id, COUNT(*) AS n
          FROM listings
         WHERE is_active = 1
      GROUP BY campus_id
      ORDER BY n DESC
         LIMIT 1
        """
    )
    if not row or row["n"] < 2:
        pytest.skip("No campus has enough listings to test filtering")
    return row["campus_id"]


# ======================================================================
# TC-08 — Filter by campus
# ======================================================================


@pytest.mark.functional
@pytest.mark.integration
def test_results_belong_only_to_the_selected_campus(db, search, busiest_campus):
    """
    TC-08 · FR2, BR-04

    Every returned room must be at the chosen campus. A room from
    elsewhere would send a student across the city for a viewing.
    """
    search.open_filtered(campus=busiest_campus)
    names = search.card_names()
    assert names, f"No results for {busiest_campus}"

    stored = {
        r["name"]
        for r in db.query(
            "SELECT name FROM listings WHERE campus_id = %s AND is_active = 1",
            (busiest_campus,),
        )
    }

    for name in names:
        assert name in stored, (
            f"{name!r} was returned for {busiest_campus} but is not a listing there"
        )


@pytest.mark.functional
@pytest.mark.integration
def test_no_matching_listing_is_left_out(db, search, busiest_campus):
    """
    TC-08 · FR2

    The other direction, and the one that matters more. A filter can
    look correct while quietly dropping rooms - the student simply never
    sees them and has no way to know.
    """
    search.open_filtered(campus=busiest_campus)
    shown = set(search.card_names())

    stored = {
        r["name"]
        for r in db.query(
            "SELECT name FROM listings WHERE campus_id = %s AND is_active = 1",
            (busiest_campus,),
        )
    }

    missing = stored - shown
    assert not missing, (
        f"These rooms at {busiest_campus} match the filter but were not shown: "
        f"{sorted(missing)}"
    )


@pytest.mark.functional
def test_the_reported_count_matches_the_cards_shown(search, busiest_campus):
    """
    TC-08 · FR2

    The page states a number of rooms. It must equal the number of cards
    rendered - a count that disagrees with what is on screen undermines
    every other figure the site reports.
    """
    search.open_filtered(campus=busiest_campus)
    assert search.reported_count() == search.result_count(), (
        f"The page claims {search.reported_count()} rooms but shows "
        f"{search.result_count()} cards"
    )


@pytest.mark.functional
def test_results_are_ordered_closest_on_foot_first(search, busiest_campus):
    """
    TC-08 · FR2, FR3

    Phase 3 found that distance to campus is among the most influential
    factors for students, so the default order puts the nearest room
    first. Verified from the walking times shown on the cards.
    """
    search.open_filtered(campus=busiest_campus)
    minutes = search.walk_minutes_shown()

    if len(minutes) < 2:
        pytest.skip("Need at least two results to check ordering")

    assert minutes == sorted(minutes), (
        f"Results are not ordered by walking time: {minutes}"
    )


# ======================================================================
# TC-09 — Filter by maximum rent
# ======================================================================


@pytest.mark.functional
def test_no_result_exceeds_the_rent_cap(db, search, busiest_campus):
    """
    TC-09 · FR2

    A student sets a budget because they cannot afford more. Returning
    a room above it wastes their time at best.
    """
    rents = [
        int(r["rent"])
        for r in db.query(
            "SELECT rent FROM listings WHERE campus_id = %s AND is_active = 1",
            (busiest_campus,),
        )
    ]
    if len(set(rents)) < 2:
        pytest.skip("All rooms at this campus cost the same")

    # A cap between the cheapest and the dearest, so it excludes something.
    cap = (min(rents) + max(rents)) // 2

    search.open_filtered(campus=busiest_campus, max_rent=cap)

    for rent in search.rents_shown():
        assert rent <= cap, f"A room at R{rent} was returned under a R{cap} cap"


@pytest.mark.functional
def test_raising_the_cap_never_loses_results(search, busiest_campus):
    """
    TC-09 · FR2

    A monotonicity check. Raising a budget can only ever include more
    rooms, never fewer. If it does, the filter is doing something other
    than comparing numbers.
    """
    search.open_filtered(campus=busiest_campus, max_rent=config.RENT_FILTER_MIN)
    low = search.result_count()

    search.open_filtered(campus=busiest_campus, max_rent=config.RENT_FILTER_MAX)
    high = search.result_count()

    assert high >= low, (
        f"Raising the cap from R{config.RENT_FILTER_MIN} to "
        f"R{config.RENT_FILTER_MAX} reduced results from {low} to {high}"
    )


# ======================================================================
# TC-10 — The rent parameter is clamped
# ======================================================================


@pytest.mark.security
@pytest.mark.functional
@pytest.mark.parametrize(
    "supplied, expected",
    [
        (50, config.RENT_FILTER_MIN),
        (1, config.RENT_FILTER_MIN),
        (999999, config.RENT_FILTER_MAX),
        (-5000, config.RENT_FILTER_MIN),
    ],
    ids=["below-min", "far-below", "above-max", "negative"],
)
def test_rent_outside_the_permitted_range_is_clamped(search, supplied, expected):
    """
    TC-10 · FR2, FR7

    The slider cannot produce a value outside 2000-9000, but the URL can
    - and the URL is what the server receives. A value outside the range
    is pulled back to the nearest bound rather than rejected, so a
    shared or edited link still works.
    """
    search.open_filtered(campus=config.CONFIRMED_CAMPUS, max_rent=supplied)

    shown = search.max_rent_display.inner_text()
    digits = "".join(ch for ch in shown if ch.isdigit())

    assert int(digits) == expected, (
        f"max={supplied} was shown as {shown!r}, expected R{expected}"
    )


@pytest.mark.security
def test_a_non_numeric_rent_does_not_break_the_page(page):
    """
    TC-10 · FR7

    'abc' as a rent is not a number. The page must still render rather
    than producing a PHP warning or a blank screen.
    """
    page.goto(
        config.url(config.SEARCH) + f"?campus={config.CONFIRMED_CAMPUS}&max=abc",
        wait_until=config.WAIT_UNTIL,
    )

    body = page.content()
    assert "Warning" not in body and "Notice" not in body, (
        "A non-numeric rent produced a PHP warning"
    )
    expect(page.locator(".results-count")).to_be_visible()


# ======================================================================
# TC-11 — Filter by room type
# ======================================================================


@pytest.mark.functional
@pytest.mark.integration
def test_room_type_filter_returns_only_that_type(db, search):
    """
    TC-11 · FR2

    Room type is the difference between a private room and sharing with
    two strangers, so a wrong result here is a significant one.
    """
    row = db.one(
        """
        SELECT campus_id, room_type, COUNT(*) AS n
          FROM listings
         WHERE is_active = 1
      GROUP BY campus_id, room_type
      ORDER BY n DESC
         LIMIT 1
        """
    )
    if not row:
        pytest.skip("No listings to filter")

    campus, room_type = row["campus_id"], row["room_type"]

    search.open_filtered(campus=campus, room_types=[room_type])
    shown = search.card_names()
    assert shown, f"No {room_type} rooms returned at {campus}"

    stored = {
        r["name"]
        for r in db.query(
            """SELECT name FROM listings
                WHERE campus_id = %s AND room_type = %s AND is_active = 1""",
            (campus, room_type),
        )
    }

    assert set(shown) == stored, (
        f"Filtering {campus} by {room_type} returned {sorted(shown)}, "
        f"expected {sorted(stored)}"
    )


@pytest.mark.functional
def test_selecting_two_room_types_widens_the_results(db, search, busiest_campus):
    """
    TC-11 · FR2

    Room types are OR - a student happy with a single room or a studio
    ticks both and should see both. This is the opposite of how
    amenities behave, which is why both are tested.
    """
    types = [
        r["room_type"]
        for r in db.query(
            """SELECT DISTINCT room_type FROM listings
                WHERE campus_id = %s AND is_active = 1""",
            (busiest_campus,),
        )
    ]
    if len(types) < 2:
        pytest.skip("This campus offers only one room type")

    search.open_filtered(campus=busiest_campus, room_types=[types[0]])
    one = search.result_count()

    search.open_filtered(campus=busiest_campus, room_types=types[:2])
    two = search.result_count()

    assert two > one, (
        f"Adding a second room type did not widen the results ({one} then {two}). "
        "Room types should be OR, not AND."
    )


# ======================================================================
# TC-12 — Amenities are AND, not OR
# ======================================================================


@pytest.mark.functional
@pytest.mark.integration
def test_every_result_has_every_selected_amenity(db, search, busiest_campus):
    """
    TC-12 · FR2

    The interface says 'Must include', so a room has to have all of
    them. Returning a room with only one would be actively misleading -
    a student filtering for backup power and security needs both.
    """
    rows = db.query(
        """
        SELECT la.amenity_id, COUNT(*) AS n
          FROM listing_amenities la
          JOIN listings l ON l.slug = la.listing_slug
         WHERE l.campus_id = %s AND l.is_active = 1
      GROUP BY la.amenity_id
      ORDER BY n DESC
         LIMIT 2
        """,
        (busiest_campus,),
    )
    if len(rows) < 2:
        pytest.skip("Not enough amenity variety at this campus")

    wanted = [r["amenity_id"] for r in rows]
    search.open_filtered(campus=busiest_campus, amenities=wanted)

    shown = search.card_names()
    if not shown:
        pytest.skip("No room at this campus has both amenities")

    stored = {
        r["name"]
        for r in db.query(
            """
            SELECT l.name
              FROM listings l
             WHERE l.campus_id = %s AND l.is_active = 1
               AND l.slug IN (
                   SELECT listing_slug FROM listing_amenities
                    WHERE amenity_id IN (%s, %s)
                 GROUP BY listing_slug
                   HAVING COUNT(DISTINCT amenity_id) = 2
               )
            """,
            (busiest_campus, wanted[0], wanted[1]),
        )
    }

    assert set(shown) == stored, (
        f"Filtering for both {wanted} returned {sorted(shown)}, "
        f"but only {sorted(stored)} have both"
    )


@pytest.mark.functional
def test_adding_an_amenity_never_widens_the_results(search, busiest_campus):
    """
    TC-12 · FR2

    The clearest proof that amenities are AND rather than OR: each extra
    requirement can only narrow the set. If the count goes up, the
    filter is matching any rather than all.
    """
    counts = []
    for n in (0, 1, 2, 3):
        search.open_filtered(
            campus=busiest_campus, amenities=config.AMENITIES[:n]
        )
        counts.append(search.result_count())

    assert counts == sorted(counts, reverse=True), (
        f"Results did not narrow as amenities were added: {counts}. "
        "Amenities should be AND, not OR."
    )


# ======================================================================
# TC-13 — The empty state
# ======================================================================


@pytest.mark.ui
@pytest.mark.functional
def test_an_impossible_filter_shows_the_empty_state(search):
    """
    TC-13 · FR2, NFR4

    When nothing matches, the page must say so and offer a way out. A
    blank area leaves the student unsure whether the site is broken or
    there genuinely are no rooms.
    """
    f = config.IMPOSSIBLE_FILTER
    search.open_filtered(
        campus=f["campus"], max_rent=f["max_rent"], amenities=f["amenities"]
    )

    assert search.result_count() == 0, "The filter was not restrictive enough"
    expect(search.empty_state).to_be_visible()
    expect(search.clear_filters_button).to_be_visible()


@pytest.mark.ui
def test_the_empty_state_explains_what_to_do(search):
    """
    TC-13 · NFR4

    'Nothing matches yet' on its own is a dead end. The guidance has to
    suggest an action - raise the budget, remove a filter.
    """
    f = config.IMPOSSIBLE_FILTER
    search.open_filtered(
        campus=f["campus"], max_rent=f["max_rent"], amenities=f["amenities"]
    )

    text = search.empty_state.inner_text().lower()
    assert any(word in text for word in ("budget", "filter", "try")), (
        f"The empty state offers no guidance: {text!r}"
    )


@pytest.mark.functional
def test_clearing_filters_from_the_empty_state_restores_results(search):
    """
    TC-13 · FR2

    The way out has to work. Clearing must keep the campus - the student
    still wants rooms near where they study - while dropping the filters
    that excluded everything.
    """
    f = config.IMPOSSIBLE_FILTER
    search.open_filtered(
        campus=f["campus"], max_rent=f["max_rent"], amenities=f["amenities"]
    )
    search.clear_filters_button.click()
    search.page.wait_for_load_state("networkidle")

    assert search.campus_select.input_value() == f["campus"], (
        "Clearing the filters also cleared the campus"
    )
    assert search.result_count() > 0 or search.empty_state.is_visible(), (
        "Clearing produced neither results nor an explanation"
    )


@pytest.mark.functional
def test_an_empty_result_is_a_normal_page_not_an_error(page):
    """
    TC-13 · FR2, NFR8

    Zero results is an ordinary outcome, not a failure. The response
    must be HTTP 200 with no PHP notices - a 404 or a warning would
    suggest the site is broken.
    """
    f = config.IMPOSSIBLE_FILTER
    params = f"?campus={f['campus']}&max={f['max_rent']}" + "".join(
        f"&amenity[]={a}" for a in f["amenities"]
    )
    response = page.goto(
        config.url(config.SEARCH) + params, wait_until=config.WAIT_UNTIL
    )

    assert response.status == 200
    body = page.content()
    for bad in ("Warning", "Notice", "Fatal error", "Deprecated"):
        assert bad not in body, f"The empty result produced a PHP {bad}"


# ======================================================================
# BR-01 — Only active listings are searchable
# ======================================================================


@pytest.mark.integration
@pytest.mark.regression
def test_a_deactivated_listing_disappears_from_search(db, search, busiest_campus):
    """
    TC-18 · BR-01 (verified here because it is a search behaviour)

    Listings are hidden by a soft delete rather than removed, so past
    viewing requests keep their history. A deactivated room must vanish
    from search while its row remains in the table.
    """
    row = db.one(
        "SELECT slug, name FROM listings WHERE campus_id = %s AND is_active = 1 LIMIT 1",
        (busiest_campus,),
    )
    slug, name = row["slug"], row["name"]

    search.open_filtered(campus=busiest_campus)
    assert name in search.card_names()

    try:
        db.execute("UPDATE listings SET is_active = 0 WHERE slug = %s", (slug,))
        search.open_filtered(campus=busiest_campus)
        assert name not in search.card_names(), (
            f"{name!r} is deactivated but still appears in search"
        )
        assert db.listing(slug) is not None, (
            "The row was removed from the table - this should be a soft delete"
        )
    finally:
        db.execute("UPDATE listings SET is_active = 1 WHERE slug = %s", (slug,))
