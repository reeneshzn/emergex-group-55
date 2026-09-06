<?php
/**
 * Richfield Student Accommodation — helper functions.
 * Distance maths, ZAR cost calculation, formatting and lookups.
 */

require_once __DIR__ . '/data.php';

/* ======================================================================
   [DB-2] DATABASE CONNECTOR — WIRED UP
   ----------------------------------------------------------------------
   db.php opens the one shared PDO connection; repository.php holds every
   SQL statement. Neither is optional to include — both degrade safely
   when there is no database configured.

   Credentials live in includes/config.php, which is git-ignored. Copy
   includes/config.sample.php to create it.
   ====================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repository.php';

/**
 * Is the site running on the database, or on the sample arrays?
 *
 * True only when a connection succeeded AND the schema has been imported.
 * Everything below branches on this, so the site works either way:
 *
 *   - no config.php            -> sample data
 *   - config but MySQL is down -> sample data (unless 'strict' is set)
 *   - connected but no tables  -> sample data
 *   - connected with tables    -> the database
 *
 * That is deliberate. This repo is shared, and a teammate without MySQL
 * running should still be able to open the site.
 */
function using_database(): bool
{
    return db_ready();
}

/* ------------------------------------------------------------------ */
/* Output safety                                                       */
/* ------------------------------------------------------------------ */

/** Escape for HTML output. Every dynamic value on a page goes through this. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Character count that works with or without the mbstring extension.
 *
 * mbstring is optional and is NOT enabled in a stock PHP build, so calling
 * mb_strlen() directly makes the form fatal on any server without it. Falling
 * back to strlen() counts bytes, which is stricter for multi-byte input but
 * correct enough for a minimum-name and maximum-notes check.
 */
function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/* ------------------------------------------------------------------ */
/* Distance                                                            */
/* ------------------------------------------------------------------ */

const EARTH_RADIUS_KM = 6371;
const WALK_KMH        = 5;
const DRIVE_KMH       = 30;

/**
 * Great-circle distance in kilometres.
 * Straight-line, not street routing — the UI labels these as estimates.
 */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $h = sin($dLat / 2) ** 2
       + sin($dLng / 2) ** 2 * cos(deg2rad($lat1)) * cos(deg2rad($lat2));

    return 2 * EARTH_RADIUS_KM * asin(sqrt($h));
}

function pace_minutes(float $km, float $kmh): int
{
    if ($km <= 0) {
        return 0;
    }
    return max(1, (int) round(($km / $kmh) * 60));
}

function walk_minutes(float $km): int
{
    return pace_minutes($km, WALK_KMH);
}

function drive_minutes(float $km): int
{
    return pace_minutes($km, DRIVE_KMH);
}

/** Minutes on foot from a listing to its campus. */
function listing_walk(array $listing): int
{
    $campus = get_campus($listing['campus']);
    if (!$campus) {
        return 0;
    }
    return walk_minutes(haversine_km(
        $listing['lat'], $listing['lng'], $campus['lat'], $campus['lng']
    ));
}

/** Minutes by car from a listing to its campus. */
function listing_drive(array $listing): int
{
    $campus = get_campus($listing['campus']);
    if (!$campus) {
        return 0;
    }
    return drive_minutes(haversine_km(
        $listing['lat'], $listing['lng'], $campus['lat'], $campus['lng']
    ));
}

/* ------------------------------------------------------------------ */
/* Money                                                               */
/* ------------------------------------------------------------------ */

const UPFRONT_DISCOUNT = 0.05;
const TERMLY_DISCOUNT  = 0.02;
const MONTHS_PER_TERM  = 3;
const ADMIN_FEE_ZAR    = 950;

/** South African rand, no decimals: "R 4 500". */
function format_zar(float $amount): string
{
    return 'R ' . number_format(round($amount), 0, '.', ' ');
}

/**
 * Full cost breakdown for a lease.
 *
 * Discounts apply to rent only, never to the deposit or admin fee — a
 * deposit is refundable and discounting it would misrepresent what is held.
 *
 * @return array{rent_total:int,discount:int,deposit:int,admin:int,total:int,due_now:int,instalment:int,instalments:int}
 */
function calculate_payment(
    float $monthly_rent,
    int $lease_months,
    float $deposit_months,
    string $plan = 'monthly',
    float $admin_fee = ADMIN_FEE_ZAR
): array {
    $rent           = max(0, $monthly_rent);
    $months         = max(0, $lease_months);
    $deposit_months = max(0, $deposit_months);
    $admin          = (int) round(max(0, $admin_fee));

    $gross = $rent * $months;
    $rate  = match ($plan) {
        'upfront' => UPFRONT_DISCOUNT,
        'termly'  => TERMLY_DISCOUNT,
        default   => 0.0,
    };

    $discount   = (int) round($gross * $rate);
    $rent_total = (int) round($gross - $discount);
    $deposit    = (int) round($rent * $deposit_months);
    $total      = $rent_total + $deposit + $admin;

    if ($months === 0) {
        $instalments = 0;
    } elseif ($plan === 'upfront') {
        $instalments = 1;
    } elseif ($plan === 'termly') {
        $instalments = (int) ceil($months / MONTHS_PER_TERM);
    } else {
        $instalments = $months;
    }

    $instalment = $instalments === 0 ? 0 : (int) round($rent_total / $instalments);
    $due_now    = $plan === 'upfront' ? $total : $deposit + $admin + $instalment;

    return [
        'rent_total'  => $rent_total,
        'discount'    => $discount,
        'deposit'     => $deposit,
        'admin'       => $admin,
        'total'       => $total,
        'due_now'     => $due_now,
        'instalment'  => $instalment,
        'instalments' => $instalments,
    ];
}

/* ======================================================================
   [DB-3] DATABASE INTEGRATION POINT — LOOKUPS
   ----------------------------------------------------------------------
   THIS IS THE SEAM. Every page in the site reads data through these four
   functions and nothing else. Rewrite their bodies to query the database
   and the entire front end keeps working untouched.

   Contracts to preserve, or pages will break:

     get_campus($id)            -> one campus array, or null if not found
     get_listing($slug)         -> one listing array, or null if not found
                                   (listing.php relies on null to send a
                                   404 — do not return an empty array)
     listings_for_campus($id)   -> array KEYED BY SLUG, sorted by walking
                                   distance ascending. The slug keys are
                                   used as array keys by index.php and
                                   search.php, so a plain numeric list
                                   will break the links.

   The `amenities` key on a listing must come back as a PHP array of
   amenity ids, not a comma-separated string — listing-card.php and
   search.php both use in_array() on it.

   PERFORMANCE NOTE: listings_for_campus() sorts in PHP by calling
   listing_walk() on every row, which is fine for 18 rows and wasteful
   for 18 000. Distance depends on the campus, so it cannot simply be a
   stored column — if the catalogue grows, either compute it in SQL or
   cache it per campus.
   ====================================================================== */

/** All campuses, keyed by id. Database when available, sample data otherwise. */
function all_campuses(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = using_database() ? repo_campuses() : CAMPUSES;
        if (!$cache) {
            $cache = CAMPUSES;   // empty table, still show something
        }
    }
    return $cache;
}

/** All listings, keyed by slug. */
function all_listings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = using_database() ? repo_listings() : LISTINGS;
        if (!$cache) {
            $cache = LISTINGS;
        }
    }
    return $cache;
}

/** All amenities, keyed by id. */
function all_amenities(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = using_database() ? repo_amenities() : AMENITIES;
        if (!$cache) {
            $cache = AMENITIES;
        }
    }
    return $cache;
}

/** Room types as id => label. */
function all_room_types(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = using_database() ? repo_room_types() : ROOM_TYPES;
        if (!$cache) {
            $cache = ROOM_TYPES;
        }
    }
    return $cache;
}

/** FAQs in display order. */
function all_faqs(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = using_database() ? repo_faqs() : FAQS;
        if (!$cache) {
            $cache = FAQS;
        }
    }
    return $cache;
}

function get_campus(string $id): ?array
{
    return all_campuses()[$id] ?? null;
}

function get_listing(string $slug): ?array
{
    return all_listings()[$slug] ?? null;
}

/** All listings for a campus, closest on foot first. */
function listings_for_campus(string $campus_id): array
{
    $out = using_database()
        ? repo_listings_for_campus($campus_id)
        : array_filter(LISTINGS, fn($l) => $l['campus'] === $campus_id);

    uasort($out, fn($a, $b) => listing_walk($a) <=> listing_walk($b));
    return $out;
}

/**
 * Filtered search. Runs in SQL when the database is connected, and in PHP
 * over the sample arrays when it is not. Both paths return the same shape,
 * keyed by slug and sorted by walking distance.
 */
function search_listings(string $campus_id, int $max_rent, array $types, array $wanted): array
{
    if (using_database()) {
        $out = repo_search($campus_id, $max_rent, $types, $wanted);
    } else {
        $out = array_filter(
            listings_for_campus($campus_id),
            function ($l) use ($max_rent, $types, $wanted) {
                if ($l['rent'] > $max_rent) {
                    return false;
                }
                if ($types && !in_array($l['room'], $types, true)) {
                    return false;
                }
                foreach ($wanted as $a) {
                    if (!in_array($a, $l['amenities'], true)) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

    uasort($out, fn($a, $b) => listing_walk($a) <=> listing_walk($b));
    return $out;
}

function room_label(string $key): string
{
    return all_room_types()[$key] ?? $key;
}

/** One amenity's labels, from the database or the sample data. */
function amenity(string $key): ?array
{
    return all_amenities()[$key] ?? null;
}

/* ------------------------------------------------------------------ */
/* Derived statistics                                                  */
/* ------------------------------------------------------------------ */
/* Computed from the data, never hardcoded. A buyer who spots "500+ beds"
   next to eight campuses totalling 822 stops believing the rest of the page.

   [DB-4] DATABASE INTEGRATION POINT — AGGREGATES
   These three feed the trust strip on the home page. Once the data lives
   in tables these become one aggregate query each:

     total_beds()          SUM of campus beds
     verified_count()      COUNT of listings WHERE verified
     median_walk_minutes() has no clean SQL equivalent, because walking
                           time is computed per listing/campus pair in
                           PHP. Either keep it in PHP over a lighter
                           query, or cache the value — do NOT hardcode a
                           number, the whole point is that it stays true.

   These run on every home page load. If they get slow, cache them rather
   than replacing them with literals. */

function total_beds(): int
{
    return using_database()
        ? repo_total_beds()
        : array_sum(array_column(CAMPUSES, 'beds'));
}

function verified_count(): int
{
    return using_database()
        ? repo_verified_count()
        : count(array_filter(LISTINGS, fn($l) => $l['verified']));
}

/**
 * Median walking time across every listing.
 *
 * Stays in PHP even with a database connected: walking time depends on the
 * listing/campus pair and is computed by listing_walk(), so there is no
 * clean SQL equivalent. Cache it if it ever gets slow — never replace it
 * with a hardcoded number, the whole point is that it stays true.
 */
function median_walk_minutes(): int
{
    $mins = array_map('listing_walk', array_values(all_listings()));
    sort($mins);
    $n = count($mins);
    if ($n === 0) {
        return 0;
    }
    $mid = intdiv($n, 2);
    return $n % 2 === 0
        ? (int) round(($mins[$mid - 1] + $mins[$mid]) / 2)
        : $mins[$mid];
}

/* ------------------------------------------------------------------ */
/* Presentation helpers                                                */
/* ------------------------------------------------------------------ */

/**
 * Deterministic placeholder panel for a listing.
 *
 * No real photography of these buildings exists, so rather than dress the
 * demo in mismatched stock photos this renders a branded panel derived from
 * the listing seed. Swap for a real <img> when photography is supplied.
 */
function listing_image(array $listing, string $extra_class = ''): string
{
    $seed = crc32($listing['seed']);
    $angles     = [135, 155, 200, 115, 170, 225];
    $treatments = [
        ['#043171', '#002d6d'],
        ['#002d6d', '#001a42'],
        ['#0056af', '#043171'],
        ['#0a3f8f', '#002d6d'],
    ];
    $angle  = $angles[$seed % count($angles)];
    [$from, $to] = $treatments[$seed % count($treatments)];
    $offset = $seed % 40;

    $name = e($listing['name']);

    return '<div class="listing-img ' . e($extra_class) . '" role="img"'
         . ' aria-label="' . $name . ' — photography to be supplied"'
         . ' style="background:linear-gradient(' . $angle . 'deg,' . $from . ',' . $to . ')">'
         . '<span class="listing-img__grid" aria-hidden="true"'
         . ' style="background-position:' . $offset . 'px ' . ($offset / 2) . 'px"></span>'
         . '<span class="listing-img__glow" aria-hidden="true"></span>'
         . '<span class="listing-img__caption">'
         . '<span class="rf-display listing-img__name">' . $name . '</span>'
         . '<span class="rf-eyebrow listing-img__note">Photography to be supplied</span>'
         . '</span></div>';
}

/** The next N months as ['2026-09' => 'September 2026', ...]. */
function upcoming_months(int $count = 12): array
{
    $out = [];
    $d = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < $count; $i++) {
        $m = $d->modify("+$i months");
        $out[$m->format('Y-m')] = $m->format('F Y');
    }
    return $out;
}
