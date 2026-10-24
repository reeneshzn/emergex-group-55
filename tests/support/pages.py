"""
Page objects.

EVERY CSS SELECTOR IN THE SUITE LIVES IN THIS FILE.

Tests say what they want to do - open the search page, filter by rent,
submit the viewing form. These classes know which element that means. When
the markup changes, the fix is one line here instead of a search across
every test.
"""

from __future__ import annotations

import re

from playwright.sync_api import Page, expect

from . import config


class BasePage:
    """Shared behaviour: navigation, the header, the sample-data banner."""

    path = ""

    def __init__(self, page: Page):
        self.page = page

    # --- navigation ---

    def open(self, query: str = "") -> "BasePage":
        self.page.goto(config.url(self.path) + query)
        return self

    # --- chrome present on every page ---

    @property
    def sample_data_banner(self):
        return self.page.get_by_text(
            "Demonstration prototype", exact=False
        ).first

    @property
    def nav(self):
        return self.page.locator("header nav").first

    @property
    def shortlist_button(self):
        return self.page.get_by_role("button", name=re.compile("shortlist", re.I)).first

    @property
    def shortlist_count(self):
        """The badge beside the shortlist control."""
        return self.page.locator("[data-shortlist-count], .shortlist-count").first

    @property
    def not_affiliated_notice(self):
        return self.page.get_by_text("not affiliated", exact=False).first

    def skip_link(self):
        return self.page.get_by_role("link", name=re.compile("skip to", re.I)).first

    # --- helpers ---

    def has_horizontal_overflow(self) -> bool:
        """True when the document is wider than the viewport."""
        return self.page.evaluate(
            "() => document.documentElement.scrollWidth >"
            " document.documentElement.clientWidth"
        )

    def meta_robots(self) -> str:
        """The robots directive. The README requires noindex everywhere."""
        tag = self.page.locator('meta[name="robots"]')
        return tag.get_attribute("content") or "" if tag.count() else ""


class HomePage(BasePage):
    path = config.HOME

    @property
    def hero_heading(self):
        return self.page.get_by_role("heading", level=1).first

    @property
    def faq_items(self):
        return self.page.locator("details, .faq__item")

    # The trust strip figures, computed from the data rather than hardcoded.
    @property
    def trust_strip(self):
        return self.page.locator(".trust, .trust-strip").first


class SearchPage(BasePage):
    path = config.SEARCH

    # --- filters ---

    # Filter state lives in the URL, so a filtered view is a shareable link.
    # That also means filters can be applied by navigation instead of by
    # clicking, which is faster and less brittle — see open_filtered().

    @property
    def campus_select(self):
        return self.page.locator("#f-campus")

    @property
    def max_rent_input(self):
        """A range slider, clamped server-side to 2000-9000."""
        return self.page.locator("#f-max")

    def room_type_checkbox(self, room_type: str):
        return self.page.locator(f"input[name='type[]'][value='{room_type}']")

    def amenity_checkbox(self, amenity_id: str):
        return self.page.locator(f"input[name='amenity[]'][value='{amenity_id}']")

    @property
    def apply_filters_button(self):
        return self.page.get_by_role("button", name=re.compile("apply filters", re.I))

    @property
    def clear_all_link(self):
        return self.page.get_by_role("link", name=re.compile("clear all", re.I))

    # --- results ---

    @property
    def result_cards(self):
        return self.page.locator("ul.listing-grid > li")

    @property
    def results_count_text(self):
        """The live region reading '<n> rooms near Richfield <campus>'."""
        return self.page.locator(".results-count")

    @property
    def empty_state(self):
        return self.page.locator(".empty-state")

    def result_count(self) -> int:
        return self.result_cards.count()

    def card_titles(self) -> list[str]:
        return [t.strip() for t in self.result_cards.locator("h2, h3").all_inner_texts()]

    def rents_shown(self) -> list[int]:
        """Every rent on screen, as integers, so ordering and caps can be asserted."""
        out = []
        for text in self.result_cards.locator(".price, .listing-card__price").all_inner_texts():
            digits = re.sub(r"[^0-9]", "", text)
            if digits:
                out.append(int(digits))
        return out

    @property
    def map_container(self):
        return self.page.locator("#map, .map").first

    def open_filtered(
        self,
        campus: str | None = None,
        max_rent: int | None = None,
        room_types: list[str] | None = None,
        amenities: list[str] | None = None,
    ) -> "SearchPage":
        """
        Load a filtered view directly by URL.

        search.php reads every filter from the query string, so this is
        equivalent to using the controls but avoids dragging a range
        slider, which Playwright can only do by simulated mouse movement.
        """
        from urllib.parse import urlencode

        params: list[tuple[str, str]] = []
        if campus is not None:
            params.append(("campus", campus))
        if max_rent is not None:
            params.append(("max", str(max_rent)))
        for t in room_types or []:
            params.append(("type[]", t))
        for a in amenities or []:
            params.append(("amenity[]", a))

        query = ("?" + urlencode(params)) if params else ""
        self.page.goto(config.url(self.path) + query)
        return self

    def search_via_controls(self, campus: str | None = None) -> "SearchPage":
        """
        Apply filters by operating the form, rather than by URL.

        Slower, but it is what a student actually does — so at least one
        test must exercise this path rather than the URL shortcut.
        """
        if campus is not None:
            self.campus_select.select_option(campus)
            # The campus select submits its own form on change.
            self.page.wait_for_load_state("networkidle")
        else:
            self.apply_filters_button.click()
            self.page.wait_for_load_state("networkidle")
        return self


class ListingPage(BasePage):
    path = config.LISTING

    def open_slug(self, slug: str) -> "ListingPage":
        self.page.goto(config.url(f"{self.path}?slug={slug}"))
        return self

    @property
    def title(self):
        return self.page.get_by_role("heading", level=1).first

    @property
    def rent(self):
        return self.page.locator(".price, .listing__price").first

    @property
    def inspected_badge(self):
        return self.page.get_by_text(re.compile("inspected", re.I)).first

    @property
    def walk_time(self):
        return self.page.get_by_text(re.compile(r"\d+\s*min.*walk", re.I)).first

    @property
    def drive_time(self):
        return self.page.get_by_text(re.compile(r"\d+\s*min.*driv", re.I)).first

    @property
    def book_viewing_link(self):
        return self.page.get_by_role("link", name=re.compile("book a viewing", re.I)).first

    @property
    def shortlist_toggle(self):
        return self.page.get_by_role(
            "button", name=re.compile("shortlist|save", re.I)
        ).first


class ApplyPage(BasePage):
    """
    The viewing-request form - the only page that captures typed data,
    and therefore the heaviest test surface in the application.
    """

    path = config.APPLY

    # --- fields (ids confirmed against apply.php) ---

    @property
    def full_name(self):
        return self.page.locator("#fullName")

    @property
    def email(self):
        return self.page.locator("#email")

    @property
    def phone(self):
        return self.page.locator("#phone")

    @property
    def guardian_email(self):
        return self.page.locator("#guardianEmail")

    @property
    def campus(self):
        return self.page.locator("#campus")

    @property
    def date(self):
        return self.page.locator("#date")

    @property
    def move_in(self):
        return self.page.locator("#moveIn")

    @property
    def notes(self):
        return self.page.locator("#notes")

    @property
    def csrf_token(self):
        return self.page.locator("input[name='csrf']")

    def room_checkbox(self, slug: str):
        return self.page.locator(f"input[name='listings[]'][value='{slug}']")

    @property
    def submit(self):
        return self.page.get_by_role("button", name=re.compile("request viewing", re.I))

    # --- results ---

    @property
    def success_panel(self):
        return self.page.locator(".success")

    @property
    def reference(self):
        return self.page.locator(".success .ref")

    @property
    def fineprint(self):
        return self.page.locator(".success .fineprint")

    @property
    def form_level_error(self):
        return self.page.locator("p.form-error[role='alert']")

    def error_for(self, field_id: str):
        """
        The error paragraph belonging to one field.

        apply.php renders every error as a sibling <p class="form-error">
        inside the same .form-row, so the field's own row is the anchor.
        """
        return self.page.locator(
            f".form-row:has(#{field_id}) p.form-error"
        ).first

    def visible_errors(self) -> list[str]:
        """Every non-empty validation message currently on the page."""
        texts = self.page.locator("p.form-error").all_inner_texts()
        return [t.strip() for t in texts if t.strip()]

    # --- actions ---

    def fill_valid(
        self,
        name: str = "Test Student",
        email: str = "test.student@example.com",
        phone: str = "0821234567",
        date: str | None = None,
        rooms: list[str] | None = None,
    ) -> "ApplyPage":
        """Complete the form with data that should pass every rule."""
        from datetime import date as _date, timedelta

        self.full_name.fill(name)
        self.email.fill(email)
        self.phone.fill(phone)
        self.date.fill(date or (_date.today() + timedelta(days=7)).isoformat())

        for slug in rooms or [config.KNOWN_LISTING]:
            box = self.room_checkbox(slug)
            if box.count():
                box.check()
        return self

    def submit_form(self) -> "ApplyPage":
        self.submit.click()
        self.page.wait_for_load_state("networkidle")
        return self

    def reference_text(self) -> str:
        expect(self.reference).to_be_visible(timeout=config.TIMEOUT_MS)
        return self.reference.inner_text().strip()
