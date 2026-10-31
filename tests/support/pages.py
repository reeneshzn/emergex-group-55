"""
Page objects.

EVERY CSS SELECTOR IN THE SUITE LIVES IN THIS FILE.

Tests say what they want to do - open the search page, filter by rent,
submit the viewing form. These classes know which element that means. When
the markup changes, the fix is one line here instead of a search across
every test.

Every selector below was read from the application source rather than
guessed:
    includes/header.php        shortlist trigger and count
    includes/footer.php        shortlist drawer
    includes/listing-card.php  the result card
    listing.php                the detail page buybox
    search.php                 the filter form
    apply.php                  the viewing request form
"""

from __future__ import annotations

import re
from urllib.parse import urlencode

from playwright.sync_api import Page, expect

from . import config


class BasePage:
    """Shared behaviour: navigation, the header, the shortlist drawer."""

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
        return self.page.get_by_text("Demonstration prototype", exact=False).first

    @property
    def not_affiliated_notice(self):
        return self.page.get_by_text("not affiliated", exact=False).first

    @property
    def skip_link(self):
        return self.page.get_by_role("link", name=re.compile("skip to", re.I)).first

    # --- shortlist, which lives in the header and footer of every page ---

    @property
    def shortlist_trigger(self):
        return self.page.locator("#shortlistOpen")

    @property
    def shortlist_count(self):
        return self.page.locator("#shortlistCount")

    @property
    def drawer(self):
        return self.page.locator("#shortlistDrawer")

    @property
    def drawer_count(self):
        return self.page.locator("#drawerCount")

    @property
    def drawer_body(self):
        return self.page.locator("#drawerBody")

    @property
    def drawer_book_all(self):
        return self.page.locator("#drawerBookAll")

    @property
    def drawer_clear(self):
        return self.page.locator("#drawerClear")

    @property
    def drawer_close(self):
        return self.page.locator("#shortlistClose")

    def saved_count(self) -> int:
        """How many rooms the header badge reports."""
        text = self.shortlist_count.inner_text().strip()
        return int(text) if text.isdigit() else 0

    def open_drawer(self) -> "BasePage":
        self.shortlist_trigger.click()
        expect(self.drawer).to_be_visible(timeout=config.TIMEOUT_MS)
        return self

    def clear_shortlist(self) -> "BasePage":
        """Empty the shortlist so a test starts from a known state."""
        self.page.evaluate("() => { try { localStorage.clear(); } catch (e) {} }")
        return self

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
        return (tag.get_attribute("content") or "") if tag.count() else ""


class HomePage(BasePage):
    path = config.HOME

    @property
    def hero_heading(self):
        return self.page.get_by_role("heading", level=1).first

    @property
    def campus_cards(self):
        """Each card is a link to that campus's filtered search."""
        return self.page.locator("a.campus-card")

    def campus_card(self, campus_id: str):
        return self.page.locator(f"a.campus-card[href*='campus={campus_id}']")

    @property
    def campus_names(self):
        return self.page.locator(".campus-card__name")

    @property
    def unconfirmed_badges(self):
        return self.page.locator(".badge-unconfirmed")

    def campus_ids_shown(self) -> list[str]:
        """The campus id from each card's href, in display order."""
        hrefs = self.campus_cards.evaluate_all(
            "els => els.map(e => e.getAttribute('href'))"
        )
        return [
            h.split("campus=")[1].split("&")[0] for h in hrefs if h and "campus=" in h
        ]

    def unconfirmed_campus_ids(self) -> list[str]:
        """Campus ids whose card carries the 'Location to confirm' badge."""
        return self.page.evaluate(
            """() => Array.from(document.querySelectorAll('a.campus-card'))
                 .filter(a => a.querySelector('.badge-unconfirmed'))
                 .map(a => {
                   const h = a.getAttribute('href') || '';
                   const m = h.match(/campus=([^&]+)/);
                   return m ? m[1] : null;
                 })
                 .filter(Boolean)"""
        )

    @property
    def listing_cards(self):
        """The featured rail on the landing page."""
        return self.page.locator("article.listing-card")

    # --- cost estimator (FR8, BR-13) ---
    #
    # The calculator is client-side: moving a control recalculates
    # immediately with no page load. Every figure below is read back as
    # an integer so the arithmetic can be asserted rather than eyeballed.

    @property
    def calc(self):
        return self.page.locator("#calc")

    @property
    def calc_rent_slider(self):
        return self.page.locator("#calcRent")

    @property
    def calc_rent_display(self):
        return self.page.locator("#calcRentOut")

    @property
    def calc_lease(self):
        return self.page.locator("#calcLease")

    @property
    def calc_deposit_months(self):
        return self.page.locator("#calcDeposit")

    def calc_plan_tab(self, plan: str):
        """plan is one of monthly, termly, upfront."""
        return self.page.locator(f".tabs [data-plan='{plan}']")

    @property
    def calc_due_now(self):
        return self.page.locator("#calcDueNow")

    @property
    def calc_instalment(self):
        return self.page.locator("#calcInstalment")

    @property
    def calc_instalment_label(self):
        return self.page.locator("#calcInstalmentLabel")

    @property
    def calc_deposit_out(self):
        return self.page.locator("#calcDeposit_out")

    @property
    def calc_admin(self):
        return self.page.locator("#calcAdmin")

    @property
    def calc_discount(self):
        return self.page.locator("#calcDiscount")

    @property
    def calc_discount_row(self):
        return self.page.locator("#calcDiscountRow")

    @property
    def calc_total(self):
        return self.page.locator("#calcTotal")

    @staticmethod
    def _rand(text: str) -> int:
        """'R 59 450' -> 59450. Returns 0 when there is no number."""
        digits = re.sub(r"[^0-9]", "", text or "")
        return int(digits) if digits else 0

    def set_calculator(
        self,
        rent: int | None = None,
        lease_months: int | None = None,
        deposit_months: int | None = None,
        plan: str | None = None,
    ) -> "HomePage":
        """
        Drive the estimator.

        The rent control is a range input. Playwright cannot type into one,
        so the value is set directly and an input event dispatched - which
        is exactly what dragging it would produce.
        """
        if rent is not None:
            self.calc_rent_slider.evaluate(
                "(el, v) => { el.value = v;"
                " el.dispatchEvent(new Event('input', {bubbles:true}));"
                " el.dispatchEvent(new Event('change', {bubbles:true})); }",
                str(rent),
            )
        if lease_months is not None:
            self.calc_lease.select_option(str(lease_months))
        if deposit_months is not None:
            self.calc_deposit_months.select_option(str(deposit_months))
        if plan is not None:
            self.calc_plan_tab(plan).click()
        return self

    def calculator_figures(self) -> dict[str, int]:
        """Every figure the estimator currently shows, as integers."""
        return {
            "due_now": self._rand(self.calc_due_now.inner_text()),
            "instalment": self._rand(self.calc_instalment.inner_text()),
            "deposit": self._rand(self.calc_deposit_out.inner_text()),
            "admin": self._rand(self.calc_admin.inner_text()),
            "total": self._rand(self.calc_total.inner_text()),
            "discount": (
                self._rand(self.calc_discount.inner_text())
                if self.calc_discount_row.is_visible()
                else 0
            ),
        }

    def instalment_count(self) -> int:
        """The '12' from '12 × monthly instalment'."""
        match = re.search(r"\d+", self.calc_instalment_label.inner_text())
        return int(match.group()) if match else 0

    @property
    def trust_items(self):
        return self.page.locator(".trust__item")

    def stat_value(self, label: str) -> int:
        """
        A figure from the trust strip, found by the label beside it.

        Each item is a <dt> label with a <dd> value. These figures are
        computed from the data on every page load, so the test that
        changes the data and reloads can prove they are derived rather
        than written into the page.

        'Beds listed' -> 822, 'Median walk to campus' -> 15
        """
        value = self.trust_items.filter(has_text=label).locator("dd").first
        digits = re.sub(r"[^0-9]", "", value.inner_text())
        return int(digits) if digits else 0


class SearchPage(BasePage):
    path = config.SEARCH

    # --- filters ---

    @property
    def campus_select(self):
        return self.page.locator("#f-campus")

    @property
    def max_rent_input(self):
        """A range slider, clamped server-side to 2000-9000."""
        return self.page.locator("#f-max")

    @property
    def max_rent_display(self):
        return self.page.locator("#fMaxOut")

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
        return self.page.locator("article.listing-card")

    @property
    def results_count_text(self):
        """The live region reading '<n> rooms near Richfield <campus>'."""
        return self.page.locator(".results-count")

    @property
    def empty_state(self):
        return self.page.locator(".empty-state")

    @property
    def clear_filters_button(self):
        return self.page.locator(".empty-state").get_by_role(
            "link", name=re.compile("clear filters", re.I)
        )

    @property
    def map_container(self):
        return self.page.locator("#map")

    def result_count(self) -> int:
        return self.result_cards.count()

    def reported_count(self) -> int:
        """The number the page itself claims, which should match the cards."""
        match = re.search(r"\d+", self.results_count_text.inner_text())
        return int(match.group()) if match else 0

    def card_names(self) -> list[str]:
        """
        Listing names as they are written, not as they are displayed.

        The stylesheet renders these in uppercase. inner_text() returns
        the rendered text and would give "THE RIDGE ROOMS", which no
        longer matches the "The Ridge Rooms" held in the database.
        text_content() reads the DOM text before CSS transforms it, so
        comparisons against stored data hold.
        """
        return [
            t.strip()
            for t in self.result_cards.locator(
                ".listing-card__name"
            ).all_text_contents()
        ]

    def rents_shown(self) -> list[int]:
        """Every rent on screen as an integer, so caps and order can be asserted."""
        out = []
        for text in self.result_cards.locator(".listing-card__price").all_inner_texts():
            digits = re.sub(r"[^0-9]", "", text)
            if digits:
                out.append(int(digits))
        return out

    def walk_minutes_shown(self) -> list[int]:
        """Walking times in result order, used to assert closest-first sorting."""
        out = []
        for text in self.result_cards.locator(".listing-card__walk").all_inner_texts():
            match = re.search(r"\d+", text)
            if match:
                out.append(int(match.group()))
        return out

    def heart(self, slug: str):
        """The shortlist toggle on a given result card."""
        return self.page.locator(f"button[data-heart='{slug}']")

    # --- actions ---

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
        Apply filters by operating the form rather than by URL.

        Slower, but it is what a student actually does, so at least one
        test must exercise this path rather than the URL shortcut.
        """
        if campus is not None:
            # The campus select submits its own form on change.
            self.campus_select.select_option(campus)
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
        return self.page.locator(".buybox__price")

    @property
    def beds(self):
        return self.page.locator(".buybox__beds")

    @property
    def inspected_badge(self):
        return self.page.locator(".badge-inspected")

    @property
    def amenity_chips(self):
        return self.page.locator(".chip")

    @property
    def walk_time(self):
        return self.page.get_by_text(re.compile(r"\d+\s*min.*walk", re.I)).first

    @property
    def drive_time(self):
        return self.page.get_by_text(re.compile(r"\d+\s*min.*driv", re.I)).first

    @property
    def book_viewing_link(self):
        return self.page.get_by_role(
            "link", name=re.compile("book a viewing", re.I)
        ).first

    def heart(self, slug: str):
        return self.page.locator(f"button[data-heart='{slug}']")

    def rent_value(self) -> int:
        """The rent as an integer, for comparison against the database."""
        digits = re.sub(r"[^0-9]", "", self.rent.inner_text().split("per")[0])
        return int(digits) if digits else 0


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
    def guardian_hint(self):
        """The hint under the guardian field - NFR5 honesty check."""
        return self.page.locator(".form-row:has(#guardianEmail) .form-hint")

    @property
    def privacy_hint(self):
        """The notice above the submit button, which must match behaviour."""
        return self.page.locator("form.form-card > p.form-hint").last

    @property
    def csrf_token(self):
        return self.page.locator("input[name='csrf']")

    def room_checkbox(self, slug: str):
        return self.page.locator(f"input[name='listings[]'][value='{slug}']")

    @property
    def room_checkboxes(self):
        return self.page.locator("input[name='listings[]']")

    def checked_rooms(self) -> list[str]:
        """Slugs currently ticked, used by the shortlist pre-selection test."""
        return self.page.eval_on_selector_all(
            "input[name='listings[]']",
            "els => els.filter(e => e.checked).map(e => e.value)",
        )

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
        return self.page.locator(f".form-row:has(#{field_id}) p.form-error").first

    def visible_errors(self) -> list[str]:
        """Every non-empty validation message currently on the page."""
        texts = self.page.locator("p.form-error").all_inner_texts()
        return [t.strip() for t in texts if t.strip()]

    def error_count(self) -> int:
        return len(self.visible_errors())

    # --- actions ---

    def fill_valid(
        self,
        name: str = "Test Student",
        email: str = "test.student@example.com",
        phone: str = "0821234567",
        guardian: str = "",
        date: str | None = None,
        notes: str = "",
        rooms: list[str] | None = None,
    ) -> "ApplyPage":
        """Complete the form with data that should pass every rule."""
        from datetime import date as _date, timedelta

        self.full_name.fill(name)
        self.email.fill(email)
        self.phone.fill(phone)
        if guardian:
            self.guardian_email.fill(guardian)
        self.date.fill(date or (_date.today() + timedelta(days=7)).isoformat())
        if notes:
            self.notes.fill(notes)

        for slug in rooms if rooms is not None else [config.KNOWN_LISTING]:
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

    def submitted_successfully(self) -> bool:
        return self.success_panel.count() > 0
