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
#:
#: This points at the copy INSIDE THE REPOSITORY, which is the copy that
#: gets submitted. An identical copy sat directly under htdocs and was
#: what the suite originally tested - so the application being verified
#: and the application being delivered were two different files, with
#: nothing keeping them in step. Test what you ship.
BASE_URL = _env(
    "RF_BASE_URL",
    "http://localhost/emergex-group-55/Richfield%20accommodation%20interface",
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
#:
#: It MUST be a room at CONFIRMED_CAMPUS, which is also the campus the
#: viewing form defaults to. Once BR-07 is enforced - the rooms on a
#: request must be at the chosen campus - a room from anywhere else
#: makes every otherwise-valid submission invalid.
#:
#: This previously named a room at Umhlanga while the form defaulted to
#: Bryanston. The mismatch went unnoticed because the build accepted it;
#: that acceptance was defect DEF-03.
KNOWN_LISTING = _env("RF_KNOWN_LISTING", "acacia-court-bryanston")

#: A slug that must never exist, used to assert a 404.
MISSING_LISTING = "no-such-room-anywhere-xyz"

#: Total listings loaded by the seed. Asserted by the integration layer.
EXPECTED_LISTING_COUNT = int(_env("RF_LISTING_COUNT", "18"))

#: Number of Richfield campuses in the data.
EXPECTED_CAMPUS_COUNT = int(_env("RF_CAMPUS_COUNT", "8"))

#: Room type ids, from includes/data.php.
ROOM_TYPES = ["single", "sharing-2", "sharing-3", "studio", "en-suite"]

#: Amenity ids, from includes/data.php.
AMENITIES = [
    "uncapped-wifi",
    "backup-power",
    "biometric-access",
    "24h-security",
    "laundry",
    "study-room",
    "shuttle",
    "prepaid-electricity",
    "furnished",
    "cleaning",
]

#: A combination that returns nothing, used for the empty-state case.
#: Polokwane has few listings; requiring three amenities at the lowest
#: rent excludes all of them.
IMPOSSIBLE_FILTER = {
    "campus": "polokwane",
    "max_rent": 2000,
    "amenities": ["biometric-access", "laundry", "prepaid-electricity"],
}


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

#: The application's own folder, used by the fallback tests, which move
#: the configuration aside to prove the site works without a database.
#: Set RF_APP_DIR if the application lives somewhere else.
APP_DIR = _env(
    "RF_APP_DIR",
    r"C:\xampp\htdocs\emergex-group-55\Richfield accommodation interface",
)
# Must be the same copy BASE_URL points at, or the fallback tests would
# move a configuration file the served site is not reading.

#: Set RF_RUN_FALLBACK=1 to allow the tests that temporarily rename the
#: configuration file. They restore it afterwards, but an interrupted
#: run would leave the site on sample data, so they are opt-in.
RUN_FALLBACK_TESTS = _env("RF_RUN_FALLBACK", "") not in ("", "0", "false")

#: Viewport widths the responsive tests check. Taken from the project
#: README, which claims zero horizontal overflow at each.
RESPONSIVE_WIDTHS = [360, 390, 414, 768, 1024, 1280, 1920]
