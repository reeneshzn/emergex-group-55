"""
Every environment-specific value the suite needs, in one place.

Nothing else in the suite hardcodes a URL, a credential or a seeded value.
Each setting can be overridden with an environment variable, so the suite
runs on another machine without editing code:

    set RF_BASE_URL=http://localhost:8000
    pytest
"""

import os


def _env(name: str, default: str) -> str:
    return os.environ.get(name, default).strip()


# --- The application under test ---------------------------------------

#: Root of the deployed site. No trailing slash.
BASE_URL = _env(
    "RF_BASE_URL",
    "http://localhost/Richfield%20accommodation%20interface",
).rstrip("/")


def url(path: str = "") -> str:
    """Absolute URL for a page: url('search.php') -> http://.../search.php"""
    return f"{BASE_URL}/{path.lstrip('/')}"


# --- Pages -------------------------------------------------------------

HOME = ""
SEARCH = "search.php"
LISTING = "listing.php"
APPLY = "apply.php"


# --- Database ----------------------------------------------------------
# Used by the integration layer to prove that what the browser did
# actually reached MySQL. Matches includes/config.sample.php defaults.

DB_HOST = _env("RF_DB_HOST", "127.0.0.1")
DB_PORT = int(_env("RF_DB_PORT", "3306"))
DB_NAME = _env("RF_DB_NAME", "richfield_accommodation")
DB_USER = _env("RF_DB_USER", "root")
DB_PASSWORD = _env("RF_DB_PASSWORD", "")


# --- Seeded data the tests rely on -------------------------------------
# These come from includes/data.php via database/seed.php. If the seed
# changes, change them here and nowhere else.

#: A campus whose location Richfield has confirmed (no "to confirm" badge).
CONFIRMED_CAMPUS = _env("RF_CONFIRMED_CAMPUS", "bryanston")

#: A campus whose location came from a third-party directory.
UNCONFIRMED_CAMPUS = _env("RF_UNCONFIRMED_CAMPUS", "polokwane")

#: A listing slug that exists, used for detail-page and booking tests.
#: Confirmed present in the running application.
KNOWN_LISTING = _env("RF_KNOWN_LISTING", "glen-ashley-house")

#: A slug that must never exist, used to assert a 404.
MISSING_LISTING = "no-such-room-anywhere-xyz"

#: Total listings loaded by the seed. Asserted by the integration layer.
EXPECTED_LISTING_COUNT = int(_env("RF_LISTING_COUNT", "18"))


# --- Business rule constants -------------------------------------------
# Mirrored from includes/functions.php. The logic tests assert the
# application's arithmetic against these independently calculated values,
# so a change to either side is caught.

ADMIN_FEE_ZAR = 950
UPFRONT_DISCOUNT = 0.05
TERMLY_DISCOUNT = 0.02
MONTHS_PER_TERM = 3

#: BR-05 - a student may shortlist at most this many listings.
SHORTLIST_LIMIT = 4

#: search.php clamps the max-rent filter to this range. A value outside
#: it is silently pulled back to the nearest bound, which is itself
#: testable behaviour.
RENT_FILTER_MIN = 2000
RENT_FILTER_MAX = 9000

#: BR-10 - RF-YYMMDD-XXXX
REFERENCE_PATTERN = r"^RF-\d{6}-[0-9A-F]{4}$"

#: Longest permitted free-text note on the viewing request form.
NOTES_MAX = 500


# --- Test behaviour ----------------------------------------------------

#: How long a web-first assertion retries before failing, milliseconds.
TIMEOUT_MS = int(_env("RF_TIMEOUT_MS", "7000"))

#: Viewport widths the responsive tests check. Taken from the project
#: README, which claims zero horizontal overflow at each.
RESPONSIVE_WIDTHS = [360, 390, 414, 768, 1024, 1280, 1920]
