"""
Listing detail — TC-14 to TC-18.

Verifies FR3 (show rent, room type, inspected status, amenities and both
travel times) and BR-02 (a listing's inspected status is shown exactly as
stored, never assumed).

This page is where a student decides whether a room is worth a journey
across the city, so two things matter more than the rest:

    the Inspected badge, because it is a claim about safety
    the travel times, because Phase 3 found distance is among the most
    influential factors in the decision

Both are tested against their source rather than merely checked for
presence - the badge against the stored flag, the times against an
independently calculated distance.
"""

import math

import pytest
import requests
from playwright.sync_api import expect

from support import config


# ----------------------------------------------------------------------
# Distance, calculated here rather than read from the application, so
# the two can be compared. Same constants as includes/functions.php.
# ----------------------------------------------------------------------

EARTH_RADIUS_KM = 6371
WALK_KMH = 5
DRIVE_KMH = 30


def haversine_km(lat1, lng1, lat2, lng2) -> float:
    """Great-circle distance in kilometres."""
    d_lat = math.radians(float(lat2) - float(lat1))
    d_lng = math.radians(float(lng2) - float(lng1))
    h = (
        math.sin(d_lat / 2) ** 2
        + math.sin(d_lng / 2) ** 2
        * math.cos(math.radians(float(lat1)))
        * math.cos(math.radians(float(lat2)))
    )
    return 2 * EARTH_RADIUS_KM * math.asin(math.sqrt(h))


def pace_minutes(km: float, kmh: float) -> int:
    """Travel time, never reported as zero."""
    if km <= 0:
        return 0
    return max(1, round((km / kmh) * 60))


# ======================================================================
# TC-14 — The page shows structured information
# ======================================================================


@pytest.mark.ui
@pytest.mark.functional
def test_listing_shows_name_rent_and_area(listing):
    """
    TC-14 · FR3

    Phase 3 weakness W4: property information in classified adverts is
    inconsistent and incomplete, because adverts are free text with no
    required fields. Every listing here carries the same structured
    facts, which is what makes rooms comparable.
    """
    listing.open_slug(config.KNOWN_LISTING)

    expect(listing.title).to_be_visible()
    expect(listing.rent).to_be_visible()
    expect(listing.beds).to_be_visible()

    assert listing.rent_value() > 0, "The listing shows no rent"


@pytest.mark.integration
def test_the_rent_shown_matches_the_stored_rent(db, listing):
    """
    TC-14 · FR3

    A displayed price that differs from the stored one would be a
    serious defect - the student budgets on what they read.
    """
    row = db.listing(config.KNOWN_LISTING)
    listing.open_slug(config.KNOWN_LISTING)

    assert listing.rent_value() == int(row["rent"]), (
        f"The page shows R{listing.rent_value()} but the database holds "
        f"R{row['rent']}"
    )


@pytest.mark.ui
@pytest.mark.integration
def test_every_stored_amenity_appears_as_a_chip(db, listing):
    """
    TC-14 · FR3

    Amenities drive the search filter, so the detail page must show the
    same set the filter matched on. A room returned for 'backup power'
    that does not list backup power would look like a filtering fault
    even when the filter was right.
    """
    stored = {
        r["amenity_id"]
        for r in db.query(
            "SELECT amenity_id FROM listing_amenities WHERE listing_slug = %s",
            (config.KNOWN_LISTING,),
        )
    }
    if not stored:
        pytest.skip("This listing has no amenities recorded")

    listing.open_slug(config.KNOWN_LISTING)
    chips = [t.strip().lower() for t in listing.amenity_chips.all_text_contents()]

    labels = {
        r["id"]: r["short"].lower()
        for r in db.query("SELECT id, short FROM amenities")
    }

    for amenity_id in stored:
        label = labels.get(amenity_id, amenity_id)
        assert any(label in chip for chip in chips), (
            f"{label!r} is recorded against this listing but no chip shows it. "
            f"Chips present: {chips}"
        )


@pytest.mark.ui
def test_the_area_is_a_suburb_not_a_street_address(db, listing):
    """
    TC-14 · BR-14, NFR7

    Listing locations are deliberately suburb level. A street address
    would identify a specific building occupied by students, which is
    both a privacy and a safety concern for a site that is not yet
    verified by the client.
    """
    row = db.listing(config.KNOWN_LISTING)
    area = row["area"]

    # A street address starts with a number: "12 Oak Road". A suburb
    # does not.
    assert not area.strip()[0].isdigit(), (
        f"The area {area!r} begins with a number, which suggests a street "
        "address rather than a suburb"
    )
    for word in ("street", "road", "avenue", "drive", "crescent"):
        assert word not in area.lower(), (
            f"The area {area!r} contains {word!r}, which suggests a street address"
        )


# ======================================================================
# TC-15 — The Inspected badge
# ======================================================================


@pytest.mark.ui
@pytest.mark.integration
def test_the_inspected_badge_matches_the_stored_flag(db, listing):
    """
    TC-15 · FR3, BR-02, NFR5

    The badge is the system's central trust claim: it tells a student a
    person has physically been to this room. It must therefore come
    from the stored verification flag and nothing else - not from the
    landlord, not from how complete the listing looks.
    """
    for slug in [
        r["slug"]
        for r in db.query(
            "SELECT slug FROM listings WHERE is_active = 1 ORDER BY slug LIMIT 6"
        )
    ]:
        row = db.listing(slug)
        listing.open_slug(slug)

        shown = listing.inspected_badge.count() > 0
        expected = bool(row["verified"])

        assert shown == expected, (
            f"{slug}: badge {'shown' if shown else 'absent'} but the stored "
            f"verified flag is {row['verified']}"
        )


@pytest.mark.integration
@pytest.mark.regression
def test_the_badge_follows_the_flag_when_it_changes(db, listing):
    """
    TC-15 · BR-02

    Proves the badge is derived rather than hardcoded per listing: turn
    verification off on a verified room, reload, and the badge must go.
    The original value is restored afterwards.
    """
    row = db.one(
        "SELECT slug FROM listings WHERE verified = 1 AND is_active = 1 LIMIT 1"
    )
    if not row:
        pytest.skip("No verified listing to toggle")
    slug = row["slug"]

    listing.open_slug(slug)
    assert listing.inspected_badge.count() > 0, "Expected a badge to begin with"

    try:
        db.execute("UPDATE listings SET verified = 0 WHERE slug = %s", (slug,))
        listing.open_slug(slug)
        assert listing.inspected_badge.count() == 0, (
            f"{slug} is no longer verified but still shows the Inspected badge"
        )
    finally:
        db.execute("UPDATE listings SET verified = 1 WHERE slug = %s", (slug,))


# ======================================================================
# TC-16 — Travel times
# ======================================================================


@pytest.mark.ui
@pytest.mark.functional
def test_both_travel_times_are_shown(listing):
    """
    TC-16 · FR3

    Phase 3 weakness W2: adverts rarely state distance, so students
    check it themselves in a separate map application. Showing both
    times removes that step.
    """
    listing.open_slug(config.KNOWN_LISTING)
    expect(listing.walk_time).to_be_visible()
    expect(listing.drive_time).to_be_visible()


@pytest.mark.integration
def test_travel_times_match_an_independent_calculation(db, listing):
    """
    TC-16 · FR3

    The times are computed from coordinates rather than stored, so they
    can be checked against the same arithmetic done here. Allowing one
    minute of difference for rounding.
    """
    import re

    row = db.listing(config.KNOWN_LISTING)
    campus = db.campus(row["campus_id"])

    km = haversine_km(row["lat"], row["lng"], campus["lat"], campus["lng"])
    expected_walk = pace_minutes(km, WALK_KMH)
    expected_drive = pace_minutes(km, DRIVE_KMH)

    listing.open_slug(config.KNOWN_LISTING)

    shown_walk = int(re.search(r"\d+", listing.walk_time.inner_text()).group())
    shown_drive = int(re.search(r"\d+", listing.drive_time.inner_text()).group())

    assert abs(shown_walk - expected_walk) <= 1, (
        f"Walk shown as {shown_walk} min, calculated {expected_walk} min "
        f"over {km:.2f} km"
    )
    assert abs(shown_drive - expected_drive) <= 1, (
        f"Drive shown as {shown_drive} min, calculated {expected_drive} min"
    )


@pytest.mark.functional
def test_driving_is_never_slower_than_walking(db, listing):
    """
    TC-16 · FR3

    A sanity rule that would catch the two figures being swapped - a
    mistake that produces entirely plausible-looking numbers and would
    pass unnoticed by eye.
    """
    import re

    for slug in [
        r["slug"]
        for r in db.query("SELECT slug FROM listings WHERE is_active = 1 LIMIT 5")
    ]:
        listing.open_slug(slug)
        walk = int(re.search(r"\d+", listing.walk_time.inner_text()).group())
        drive = int(re.search(r"\d+", listing.drive_time.inner_text()).group())

        assert drive <= walk, (
            f"{slug}: driving reported as {drive} min, walking {walk} min. "
            "The two figures may be the wrong way round."
        )


@pytest.mark.functional
def test_a_very_close_room_still_reports_at_least_one_minute(db):
    """
    TC-16 · FR3

    A room a hundred metres from campus rounds to zero minutes, which
    reads as missing data rather than as 'very close'. The calculation
    has a floor of one minute for exactly that reason.
    """
    rows = db.query(
        """
        SELECT l.slug, l.lat, l.lng, c.lat AS clat, c.lng AS clng
          FROM listings l
          JOIN campuses c ON c.id = l.campus_id
         WHERE l.is_active = 1
        """
    )
    for r in rows:
        km = haversine_km(r["lat"], r["lng"], r["clat"], r["clng"])
        assert pace_minutes(km, WALK_KMH) >= 1, (
            f"{r['slug']} is {km:.3f} km from campus and would report zero minutes"
        )


# ======================================================================
# TC-17 — Unknown slugs
# ======================================================================


@pytest.mark.functional
@pytest.mark.security
@pytest.mark.parametrize(
    "slug",
    [
        "no-such-room-anywhere",
        "../../../etc/passwd",
        "",
        "a" * 300,
    ],
    ids=["unknown", "traversal", "empty", "very-long"],
)
def test_an_unresolvable_slug_returns_404(slug):
    """
    TC-17 · FR3, NFR8

    A missing room must return 404, not 200 with an empty page. The
    lookup returns null for an unknown slug and the page relies on that
    to send the status, so this also guards that contract.

    The traversal and over-length cases are here because the slug comes
    straight from the URL: whatever is sent must be treated as a key to
    look up, never as a path or a command.
    """
    response = requests.get(
        config.url(config.LISTING), params={"slug": slug}, timeout=10
    )

    assert response.status_code == 404, (
        f"slug={slug!r} returned HTTP {response.status_code}, expected 404"
    )


@pytest.mark.security
def test_a_404_page_leaks_no_internal_detail():
    """
    TC-17 · NFR8

    An error page that prints a file path tells an attacker where the
    application lives on disk. One that prints a SQL error tells them
    the shape of the query.
    """
    response = requests.get(
        config.url(config.LISTING), params={"slug": "definitely-not-a-room"}, timeout=10
    )
    body = response.text

    for leak in (
        "C:\\xampp",
        "/var/www",
        "Stack trace",
        "SQLSTATE",
        "Warning:",
        "Notice:",
        "Fatal error",
        "Deprecated",
    ):
        assert leak not in body, f"The 404 response discloses {leak!r}"


# ======================================================================
# TC-18 — Deactivated listings
# ======================================================================


@pytest.mark.integration
def test_a_deactivated_listing_is_not_reachable_by_url(db):
    """
    TC-18 · BR-01

    Hiding a room from search is not enough if its address still works.
    Anyone holding the link - from a shortlist, a shared message, a
    browser history - would still reach a room that is no longer offered.
    """
    row = db.one("SELECT slug FROM listings WHERE is_active = 1 LIMIT 1")
    slug = row["slug"]

    assert (
        requests.get(
            config.url(config.LISTING), params={"slug": slug}, timeout=10
        ).status_code
        == 200
    ), "The listing should load while active"

    try:
        db.execute("UPDATE listings SET is_active = 0 WHERE slug = %s", (slug,))
        response = requests.get(
            config.url(config.LISTING), params={"slug": slug}, timeout=10
        )
        assert response.status_code == 404, (
            f"A deactivated listing returned HTTP {response.status_code} "
            "when requested directly by URL"
        )
    finally:
        db.execute("UPDATE listings SET is_active = 1 WHERE slug = %s", (slug,))


@pytest.mark.integration
def test_deactivating_is_a_soft_delete(db):
    """
    TC-18 · BR-01

    The row must survive. Phase 4 finding 5 changed the deletion rule
    on viewing_request_rooms to RESTRICT precisely so that removing a
    listing cannot erase it from a student's past requests - which only
    works if listings are retired rather than deleted.
    """
    row = db.one("SELECT slug FROM listings WHERE is_active = 1 LIMIT 1")
    slug = row["slug"]

    try:
        db.execute("UPDATE listings SET is_active = 0 WHERE slug = %s", (slug,))
        assert db.listing(slug) is not None, (
            "Deactivating removed the row entirely; it should remain for history"
        )
    finally:
        db.execute("UPDATE listings SET is_active = 1 WHERE slug = %s", (slug,))
