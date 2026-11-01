"""
Smoke tests - run these first.

Their job is not to find defects. It is to prove the suite is wired to the
application correctly: the routes answer, the page objects find their
elements, and the database helpers reach MySQL.

If anything here fails, every other result is meaningless, so fix this
first. Run with:

    pytest suites/test_smoke.py -v
"""

import re

import pytest
import requests
from playwright.sync_api import expect

from support import config


# ======================================================================
# Routes - no browser needed, so these are fast and catch setup errors
# ======================================================================


@pytest.mark.parametrize(
    "path",
    ["", "search.php", "apply.php"],
    ids=["home", "search", "apply"],
)
def test_page_returns_200(path):
    """Every main route answers successfully."""
    response = requests.get(config.url(path), timeout=10)
    assert response.status_code == 200, (
        f"{config.url(path)} returned {response.status_code}"
    )


def test_known_listing_returns_200():
    """A seeded listing slug resolves."""
    response = requests.get(
        config.url(f"{config.LISTING}?slug={config.KNOWN_LISTING}"), timeout=10
    )
    assert response.status_code == 200, (
        f"Listing '{config.KNOWN_LISTING}' did not resolve. "
        "If the seed uses different slugs, set RF_KNOWN_LISTING."
    )


def test_unknown_listing_returns_404():
    """
    An unknown slug must 404, not 200 with an empty page.

    get_listing() returns null for a missing slug and listing.php relies
    on that to send the status - this guards that contract.
    """
    response = requests.get(
        config.url(f"{config.LISTING}?slug={config.MISSING_LISTING}"), timeout=10
    )
    assert response.status_code == 404, (
        f"Expected 404 for an unknown slug, got {response.status_code}"
    )


# ======================================================================
# Page objects - confirm the selectors actually match
# ======================================================================


@pytest.mark.ui
def test_home_page_renders(home):
    """The landing page loads and its main heading is visible."""
    home.open()
    expect(home.hero_heading).to_be_visible()


@pytest.mark.ui
def test_sample_data_banner_is_shown(home):
    """
    NFR5 (Honesty): sample data must never be presented as real stock.

    The banner is the mechanism, so it must be present on a page load.
    """
    home.open()
    expect(home.sample_data_banner).to_be_visible()


@pytest.mark.ui
def test_search_page_shows_results(search):
    """The search page renders listing cards."""
    search.open()
    assert search.result_count() > 0, (
        "No listing cards found. The card selector in pages.py may not "
        "match the markup - check includes/listing-card.php."
    )


@pytest.mark.ui
def test_listing_page_shows_detail(listing):
    """A listing page shows its heading and rent."""
    listing.open_slug(config.KNOWN_LISTING)
    expect(listing.title).to_be_visible()
    expect(listing.rent).to_be_visible()


@pytest.mark.ui
def test_apply_form_fields_are_present(apply_page):
    """Every field the viewing-request form needs is on the page."""
    apply_page.open()
    for field in (
        apply_page.full_name,
        apply_page.email,
        apply_page.phone,
        apply_page.guardian_email,
        apply_page.campus,
        apply_page.date,
        apply_page.move_in,
        apply_page.notes,
        apply_page.submit,
    ):
        expect(field).to_be_visible()


@pytest.mark.security
def test_apply_form_carries_a_csrf_token(apply_page):
    """FR9: the form must ship a token for the server to compare."""
    apply_page.open()
    token = apply_page.csrf_token.get_attribute("value")
    assert token and len(token) >= 32, (
        "CSRF token missing or too short - expected 64 hex characters"
    )


@pytest.mark.ui
def test_no_console_errors_on_home(home, console_errors):
    """A page that works but throws in the console is still defective."""
    home.open()
    home.page.wait_for_load_state("networkidle")
    assert console_errors == [], f"Console errors: {console_errors}"


# ======================================================================
# Database - skipped cleanly when MySQL is not running
# ======================================================================


@pytest.mark.integration
def test_database_has_the_expected_tables(db):
    """All nine tables from the Phase 3 data model exist."""
    expected = [
        "campuses",
        "room_types",
        "amenities",
        "listings",
        "listing_amenities",
        "faqs",
        "viewing_requests",
        "viewing_request_rooms",
        "shortlists",
    ]
    missing = [t for t in expected if not db.table_exists(t)]
    assert missing == [], f"Missing tables: {missing}"


@pytest.mark.integration
def test_seed_loaded_the_listings(db):
    """The seed ran and the catalogue is populated."""
    count = db.listing_count()
    assert count > 0, "No listings in the database - run database/seed.php"


@pytest.mark.integration
def test_known_listing_exists_in_database(db):
    """The slug the suite relies on is really in the data."""
    row = db.listing(config.KNOWN_LISTING)
    assert row is not None, (
        f"'{config.KNOWN_LISTING}' is not in the listings table. "
        "Set RF_KNOWN_LISTING to a slug that is."
    )


# ======================================================================
# Pure logic - no browser, no database
# ======================================================================


@pytest.mark.logic
def test_reference_pattern_accepts_a_real_reference():
    """
    BR-10 format guard.

    The README records RF-260903-F111 from a real submission, so the
    pattern the suite checks against must accept it.
    """
    assert re.match(config.REFERENCE_PATTERN, "RF-260903-F111")


@pytest.mark.logic
@pytest.mark.parametrize(
    "bad",
    ["RF-260903-f111", "RF-26093-F111", "XX-260903-F111", "RF-260903-F11"],
)
def test_reference_pattern_rejects_malformed_references(bad):
    assert not re.match(config.REFERENCE_PATTERN, bad)
