"""
Shared pytest fixtures for the Richfield accommodation interface suite.

Anything more than one test file needs lives here. Tests import nothing
from each other, so any test can be run on its own.
"""

from __future__ import annotations

import re
import sys
from datetime import date, timedelta
from pathlib import Path

import pytest
import requests
from playwright.sync_api import Page

sys.path.insert(0, str(Path(__file__).parent))

from support import config, database  # noqa: E402
from support.pages import ApplyPage, HomePage, ListingPage, SearchPage  # noqa: E402


# ======================================================================
# Session checks - fail fast and clearly
# ======================================================================


@pytest.fixture(scope="session", autouse=True)
def application_is_running():
    """
    Stop the whole run immediately if the site is not reachable.

    Without this, every one of the browser tests fails with a confusing
    navigation error and the real cause - Apache is not started - is
    buried a hundred lines down.
    """
    try:
        response = requests.get(config.BASE_URL + "/", timeout=10)
    except Exception as exc:
        pytest.exit(
            f"\n\nThe application is not reachable at {config.BASE_URL}\n"
            f"  {type(exc).__name__}: {exc}\n\n"
            "Start Apache in the XAMPP control panel, then run again.\n"
            "If the site lives somewhere else, set RF_BASE_URL.\n",
            returncode=1,
        )
    if response.status_code != 200:
        pytest.exit(
            f"\n\n{config.BASE_URL}/ returned HTTP {response.status_code}, "
            "expected 200.\n",
            returncode=1,
        )


@pytest.fixture(scope="session")
def db_available() -> bool:
    """Whether MySQL is reachable with the schema imported."""
    return database.available()


@pytest.fixture
def db(db_available):
    """
    Gate for the integration layer.

    The application deliberately runs on sample data when no database is
    connected, so the absence of MySQL is a valid state - not a failure.
    Integration tests skip with a clear reason instead of failing.
    """
    if not db_available:
        pytest.skip(
            "MySQL not reachable or schema not imported - "
            "import database/schema.mysql.sql and run database/seed.php"
        )
    return database


# ======================================================================
# Browser configuration
# ======================================================================


@pytest.fixture(scope="session")
def browser_context_args(browser_context_args):
    """A clean, predictable context for every test."""
    return {
        **browser_context_args,
        "viewport": {"width": 1280, "height": 900},
        "locale": "en-ZA",
        "timezone_id": "Africa/Johannesburg",
        "ignore_https_errors": True,
    }


@pytest.fixture(autouse=True)
def page_defaults(page: Page):
    """Apply the configured timeout to every page in every test."""
    page.set_default_timeout(config.TIMEOUT_MS)
    page.set_default_navigation_timeout(config.TIMEOUT_MS * 2)
    yield page


@pytest.fixture
def console_errors(page: Page) -> list[str]:
    """
    Collects console errors raised while a test runs.

    Several tests assert this list is empty. A page that works but throws
    in the console is still a defect.
    """
    errors: list[str] = []
    page.on(
        "console",
        lambda msg: errors.append(msg.text) if msg.type == "error" else None,
    )
    page.on("pageerror", lambda exc: errors.append(str(exc)))
    return errors


# ======================================================================
# Page objects
# ======================================================================


@pytest.fixture
def home(page: Page) -> HomePage:
    return HomePage(page)


@pytest.fixture
def search(page: Page) -> SearchPage:
    return SearchPage(page)


@pytest.fixture
def listing(page: Page) -> ListingPage:
    return ListingPage(page)


@pytest.fixture
def apply_page(page: Page) -> ApplyPage:
    return ApplyPage(page)


# ======================================================================
# Test data
# ======================================================================


@pytest.fixture
def future_date() -> str:
    """A valid preferred viewing date, one week out."""
    return (date.today() + timedelta(days=7)).isoformat()


@pytest.fixture
def past_date() -> str:
    """An invalid preferred viewing date - BR-09 must reject it."""
    return (date.today() - timedelta(days=1)).isoformat()


@pytest.fixture
def submitted_references() -> list[str]:
    """
    References created during a test, removed afterwards.

    Tests append to this list; the teardown deletes each row. Without it
    the viewing_requests table fills with test data and any assertion on
    row counts drifts between runs.
    """
    created: list[str] = []
    yield created

    if not database.available():
        return
    for reference in created:
        try:
            database.delete_request(reference)
        except Exception:  # cleanup must never fail a passing test
            pass


# ======================================================================
# Reporting
# ======================================================================


def pytest_html_report_title(report):
    report.title = "Richfield Student Accommodation - automated test report"


@pytest.hookimpl(hookwrapper=True)
def pytest_runtest_makereport(item, call):
    """Attach the test's level markers to each row of the HTML report."""
    outcome = yield
    report = outcome.get_result()
    levels = [m.name for m in item.iter_markers()]
    report.levels = ", ".join(sorted(levels)) if levels else ""


def pytest_configure(config_):
    Path(config_.rootpath / "reports").mkdir(exist_ok=True)
