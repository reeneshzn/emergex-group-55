"""
Campuses and the landing page — TC-01 to TC-07.

Verifies FR1 (display every campus with its location and bed count),
BR-03 (campuses whose location is unconfirmed are labelled as such) and
NFR5 (never present sample data as real, or claim an affiliation not
held).

The theme running through this file is honesty. Richfield owns no
residences and has not approved this prototype, so several of these
tests verify what the system admits rather than what it offers:

    TC-02  campuses located from a directory say so
    TC-04  the sample-data banner and non-affiliation notice
    TC-03  the headline figures are computed, so they cannot drift
           from the data they claim to summarise

A statistic that contradicts the data below it destroys a reader's trust
in everything else on the page, which is why TC-03 changes the data and
checks the figure follows.
"""

import pytest

from playwright.sync_api import expect

from support import config


# ======================================================================
# TC-01 — Every campus is listed with its location and bed count
# ======================================================================


@pytest.mark.ui
@pytest.mark.functional
def test_all_campuses_are_listed(home):
    """
    TC-01 · FR1

    Richfield operates eight campuses across four provinces. All eight
    must appear - a student whose campus is missing cannot use the site
    at all.
    """
    home.open()
    count = home.campus_cards.count()

    assert count == config.EXPECTED_CAMPUS_COUNT, (
        f"{count} campus cards are shown, expected "
        f"{config.EXPECTED_CAMPUS_COUNT}"
    )


@pytest.mark.ui
def test_each_campus_card_shows_name_place_and_beds(home):
    """
    TC-01 · FR1

    Each card must carry the campus name, its city and province, and a
    bed count. Those three are what let a student decide whether a
    campus is relevant to them.
    """
    home.open()
    first = home.campus_cards.first

    expect(first.locator(".campus-card__name")).not_to_be_empty()
    expect(first.locator(".campus-card__place")).not_to_be_empty()
    expect(first.locator(".campus-card__stats")).to_be_visible()

    # The place line is "City, Province", so it must contain a comma.
    place = first.locator(".campus-card__place").inner_text()
    assert "," in place, f"Expected 'City, Province', got {place!r}"


@pytest.mark.ui
def test_every_campus_card_links_to_its_own_filtered_search(home):
    """
    TC-01 · FR1, FR2

    A campus card is a route into the search, not decoration. Each must
    carry its own campus id so the search opens already filtered.
    """
    home.open()
    ids = home.campus_ids_shown()

    assert len(ids) == home.campus_cards.count(), (
        "Some campus cards do not link to a filtered search"
    )
    assert len(set(ids)) == len(ids), f"Two campus cards share a campus id: {ids}"


# ======================================================================
# TC-02 — Unconfirmed locations are labelled
# ======================================================================


@pytest.mark.ui
@pytest.mark.integration
def test_unconfirmed_campuses_carry_the_location_badge(db, home):
    """
    TC-02 · FR1, BR-03, NFR5

    Three campus locations are confirmed from Richfield's own site; the
    rest came from a third-party directory. Those must say so rather
    than be presented with the same certainty.

    The badge must match the stored flag exactly - not approximately,
    and not a hardcoded list of campus names.
    """
    home.open()
    badged = set(home.unconfirmed_campus_ids())

    rows = db.query("SELECT id FROM campuses WHERE confirmed = 0")
    expected = {r["id"] for r in rows}

    assert badged == expected, (
        f"Badge shown on {sorted(badged)}, but the database says the "
        f"unconfirmed campuses are {sorted(expected)}"
    )


@pytest.mark.ui
@pytest.mark.integration
def test_the_badge_follows_the_stored_flag(db, home):
    """
    TC-02 · BR-03

    Prove the badge is drawn from the data rather than written into the
    page: clear a campus's confirmed flag, reload, and the badge must
    appear. The original value is restored afterwards.
    """
    row = db.one("SELECT id, confirmed FROM campuses WHERE confirmed = 1 LIMIT 1")
    if not row:
        pytest.skip("No confirmed campus in the data to toggle")

    campus_id, original = row["id"], row["confirmed"]

    try:
        db.execute("UPDATE campuses SET confirmed = 0 WHERE id = %s", (campus_id,))
        home.open()
        assert campus_id in home.unconfirmed_campus_ids(), (
            f"{campus_id} was set to unconfirmed but shows no badge"
        )
    finally:
        db.execute(
            "UPDATE campuses SET confirmed = %s WHERE id = %s", (original, campus_id)
        )

    home.open()
    assert campus_id not in home.unconfirmed_campus_ids(), (
        f"{campus_id} still shows the badge after being restored to confirmed"
    )


# ======================================================================
# TC-03 — The headline figures are computed, not hardcoded
# ======================================================================


@pytest.mark.integration
def test_headline_figures_match_the_database(db, home):
    """
    TC-03 · FR1, NFR5

    Three of the four figures have an exact equivalent in SQL. If the
    page claims 822 beds, the campuses table must sum to 822.
    """
    home.open()

    assert home.stat_value("Beds listed") == int(
        db.scalar("SELECT COALESCE(SUM(beds),0) FROM campuses") or 0
    )
    assert home.stat_value("Campuses covered") == int(
        db.scalar("SELECT COUNT(*) FROM campuses") or 0
    )
    assert home.stat_value("Inspected listings") == int(
        db.scalar(
            "SELECT COUNT(*) FROM listings WHERE verified = 1 AND is_active = 1"
        )
        or 0
    )


@pytest.mark.integration
def test_a_figure_changes_when_the_data_changes(db, home):
    """
    TC-03 · NFR5 — the point of the case.

    Matching once could be coincidence, or a number that happened to be
    correct when it was typed in. Change a bed count, reload, and the
    total must move by the same amount. A hardcoded figure would not.
    """
    row = db.one("SELECT id, beds FROM campuses ORDER BY id LIMIT 1")
    campus_id, original = row["id"], int(row["beds"])

    home.open()
    before = home.stat_value("Beds listed")

    try:
        db.execute(
            "UPDATE campuses SET beds = %s WHERE id = %s", (original + 100, campus_id)
        )
        home.open()
        after = home.stat_value("Beds listed")
    finally:
        db.execute(
            "UPDATE campuses SET beds = %s WHERE id = %s", (original, campus_id)
        )

    assert after == before + 100, (
        f"The bed total read {before} then {after} after adding 100 beds to "
        f"{campus_id}. It should have moved by exactly 100 - a figure that "
        "does not follow the data is hardcoded."
    )


@pytest.mark.ui
def test_the_median_walking_time_is_a_plausible_number(home):
    """
    TC-03 · FR1

    The median walk has no clean SQL equivalent, because walking time is
    computed per listing/campus pair. It can still be sanity-checked:
    somewhere between one minute and two hours.
    """
    home.open()
    minutes = home.stat_value("Median walk to campus")

    assert 1 <= minutes <= 120, (
        f"A median walk of {minutes} minutes is not plausible"
    )


# ======================================================================
# TC-04 — Honesty notices
# ======================================================================


@pytest.mark.ui
@pytest.mark.regression
@pytest.mark.parametrize(
    "path",
    [config.HOME, config.SEARCH, config.APPLY],
    ids=["home", "search", "apply"],
)
def test_sample_data_banner_appears_on_every_page(page, path):
    """
    TC-04 · NFR5

    Every listing is invented. Presenting sample data as real available
    stock would mislead a student into enquiring about a room that does
    not exist, so the banner is a requirement rather than a courtesy.
    """
    from support.pages import BasePage

    page.goto(config.url(path), wait_until=config.WAIT_UNTIL)
    expect(BasePage(page).sample_data_banner).to_be_visible()


@pytest.mark.ui
@pytest.mark.regression
@pytest.mark.parametrize(
    "path",
    [config.HOME, config.SEARCH, config.APPLY],
    ids=["home", "search", "apply"],
)
def test_non_affiliation_notice_appears_on_every_page(page, path):
    """
    TC-04 · NFR5

    The site carries Richfield's name and brand without their approval.
    The footer notice is what keeps that honest, so it must be present
    everywhere - not only on the page someone happens to land on.
    """
    from support.pages import BasePage

    page.goto(config.url(path), wait_until=config.WAIT_UNTIL)
    expect(BasePage(page).not_affiliated_notice).to_be_visible()


@pytest.mark.ui
def test_the_listing_detail_page_carries_both_notices(listing):
    """
    TC-04 · NFR5

    Checked separately because the listing page needs a slug and cannot
    be parameterised alongside the others.
    """
    listing.open_slug(config.KNOWN_LISTING)
    expect(listing.sample_data_banner).to_be_visible()
    expect(listing.not_affiliated_notice).to_be_visible()


# ======================================================================
# TC-05, TC-06 — The page agrees with the database
# ======================================================================


@pytest.mark.integration
def test_rendered_campus_list_matches_the_table(db, home):
    """
    TC-05 · FR1

    Every campus in the database appears on the page, and no campus
    appears that is not in the database.
    """
    home.open()
    shown = home.campus_ids_shown()

    stored = [r["id"] for r in db.query("SELECT id FROM campuses ORDER BY sort_order, name")]

    assert sorted(shown) == sorted(stored), (
        f"Page shows {sorted(shown)}, database holds {sorted(stored)}"
    )


@pytest.mark.integration
def test_campus_display_order_follows_the_sort_column(db, home):
    """
    TC-05 · FR1

    The campuses table carries a sort_order column so the display order
    is deliberate rather than alphabetical by accident. If the order on
    screen does not follow it, the column is not being used.
    """
    home.open()
    shown = home.campus_ids_shown()

    stored = [
        r["id"]
        for r in db.query("SELECT id FROM campuses ORDER BY sort_order, name")
    ]

    assert shown == stored, (
        f"Display order {shown} does not match the sort_order column {stored}"
    )


@pytest.mark.integration
def test_search_filter_offers_exactly_the_stored_campuses(db, search):
    """
    TC-06 · FR1, FR2

    A campus missing from the filter is unreachable by search. A filter
    option with no campus behind it produces an empty result the student
    cannot explain.
    """
    search.open()
    options = search.campus_select.locator("option").evaluate_all(
        "els => els.map(e => e.value)"
    )

    stored = [r["id"] for r in db.query("SELECT id FROM campuses")]

    assert sorted(options) == sorted(stored), (
        f"Filter offers {sorted(options)}, database holds {sorted(stored)}"
    )


# ======================================================================
# TC-07 — Campus links lead to a filtered search
# ======================================================================


# The test that clicks a campus card and checks the search opens
# filtered lives in 06-e2e/test_journeys.py - it crosses two pages and
# is the first step of every journey. The case below stays here: it
# checks what the filtered search SAYS, which is a presentation concern.


@pytest.mark.functional
def test_the_filtered_search_names_the_campus_it_filtered_to(home, search):
    """
    TC-07 · FR2

    The result count line names the campus, so a student arriving from a
    card can see which campus they are looking at without checking the
    filter panel.
    """
    home.open()
    target = home.campus_ids_shown()[0]
    expected_name = home.campus_card(target).locator(".campus-card__name").inner_text()

    search.open_filtered(campus=target)

    assert expected_name.strip().lower() in search.results_count_text.inner_text().lower(), (
        f"The results line does not name {expected_name!r}"
    )
