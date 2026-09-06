<?php
/**
 * ##########################################################################
 * ###  [DB-QUERIES]  EVERY SQL STATEMENT IN THE PROJECT LIVES HERE.      ###
 * ##########################################################################
 *
 * No page writes SQL. Pages call the lookup functions in functions.php,
 * which call these when a database is connected and fall back to the sample
 * arrays in data.php when it is not.
 *
 * ADDING A NEW QUERY? Put it in this file, use a prepared statement, and
 * return the same array shape the rest of the site already expects (see the
 * shape notes at the top of data.php).
 *
 * Every function here assumes db_ready() is true. functions.php checks that
 * before calling, so these do not re-check.
 */

require_once __DIR__ . '/db.php';

/* ======================================================================
   Reading reference data
   ====================================================================== */

/** All campuses, keyed by id — same shape as the CAMPUSES array. */
function repo_campuses(): array
{
    $rows = db_all(
        'SELECT id, name, city, province, lat, lng, confirmed, beds
           FROM campuses
       ORDER BY sort_order, name'
    );

    $out = [];
    foreach ($rows as $r) {
        $out[$r['id']] = [
            'name'      => $r['name'],
            'city'      => $r['city'],
            'province'  => $r['province'],
            'lat'       => (float) $r['lat'],
            'lng'       => (float) $r['lng'],
            'confirmed' => (bool) $r['confirmed'],
            'beds'      => (int) $r['beds'],
        ];
    }
    return $out;
}

/** One campus, or null. */
function repo_campus(string $id): ?array
{
    $r = db_one(
        'SELECT id, name, city, province, lat, lng, confirmed, beds
           FROM campuses WHERE id = ?',
        [$id]
    );
    if (!$r) {
        return null;
    }
    return [
        'name'      => $r['name'],
        'city'      => $r['city'],
        'province'  => $r['province'],
        'lat'       => (float) $r['lat'],
        'lng'       => (float) $r['lng'],
        'confirmed' => (bool) $r['confirmed'],
        'beds'      => (int) $r['beds'],
    ];
}

/** All amenities, keyed by id. */
function repo_amenities(): array
{
    $out = [];
    foreach (db_all('SELECT id, short, label FROM amenities ORDER BY sort_order, short') as $r) {
        $out[$r['id']] = ['short' => $r['short'], 'label' => $r['label']];
    }
    return $out;
}

/** All room types as id => label. */
function repo_room_types(): array
{
    $out = [];
    foreach (db_all('SELECT id, label FROM room_types ORDER BY sort_order, label') as $r) {
        $out[$r['id']] = $r['label'];
    }
    return $out;
}

/** FAQs in display order, as [['q' => ..., 'a' => ...], ...]. */
function repo_faqs(): array
{
    $out = [];
    foreach (db_all('SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order, id') as $r) {
        $out[] = ['q' => $r['question'], 'a' => $r['answer']];
    }
    return $out;
}

/* ======================================================================
   Reading listings
   ====================================================================== */

/** Shared SELECT so every listing read produces an identical shape. */
function repo_listing_select(): string
{
    return 'SELECT slug, name, area, campus_id, room_type, lat, lng, rent,
                   beds, verified, seed, image_url, blurb
              FROM listings';
}

/** Turn a listings row into the array shape the site expects. */
function repo_hydrate_listing(array $r, array $amenities): array
{
    return [
        'name'      => $r['name'],
        'area'      => $r['area'],
        'campus'    => $r['campus_id'],
        'lat'       => (float) $r['lat'],
        'lng'       => (float) $r['lng'],
        'rent'      => (int) $r['rent'],
        'room'      => $r['room_type'],
        'beds'      => (int) $r['beds'],
        'amenities' => $amenities,
        'verified'  => (bool) $r['verified'],
        'seed'      => $r['seed'],
        'image_url' => $r['image_url'] ?? null,
        'blurb'     => $r['blurb'],
    ];
}

/**
 * Amenity ids for a set of listing slugs, keyed by slug.
 *
 * Fetched in one query rather than one per listing — with 18 listings that
 * is the difference between 1 query and 19.
 */
function repo_amenities_for(array $slugs): array
{
    if (!$slugs) {
        return [];
    }
    $in   = implode(',', array_fill(0, count($slugs), '?'));
    $rows = db_all(
        "SELECT listing_slug, amenity_id
           FROM listing_amenities
          WHERE listing_slug IN ($in)",
        array_values($slugs)
    );

    $out = array_fill_keys($slugs, []);
    foreach ($rows as $r) {
        $out[$r['listing_slug']][] = $r['amenity_id'];
    }
    return $out;
}

/** Every active listing, keyed by slug. */
function repo_listings(): array
{
    $rows = db_all(repo_listing_select() . ' WHERE is_active = 1 ORDER BY name');
    if (!$rows) {
        return [];
    }
    $amen = repo_amenities_for(array_column($rows, 'slug'));

    $out = [];
    foreach ($rows as $r) {
        $out[$r['slug']] = repo_hydrate_listing($r, $amen[$r['slug']] ?? []);
    }
    return $out;
}

/** One listing by slug, or null when it does not exist. */
function repo_listing(string $slug): ?array
{
    $r = db_one(repo_listing_select() . ' WHERE slug = ? AND is_active = 1', [$slug]);
    if (!$r) {
        return null;
    }
    $amen = repo_amenities_for([$slug]);
    return repo_hydrate_listing($r, $amen[$slug] ?? []);
}

/** Listings for one campus, keyed by slug. Sorting happens in PHP. */
function repo_listings_for_campus(string $campus_id): array
{
    $rows = db_all(
        repo_listing_select() . ' WHERE campus_id = ? AND is_active = 1',
        [$campus_id]
    );
    if (!$rows) {
        return [];
    }
    $amen = repo_amenities_for(array_column($rows, 'slug'));

    $out = [];
    foreach ($rows as $r) {
        $out[$r['slug']] = repo_hydrate_listing($r, $amen[$r['slug']] ?? []);
    }
    return $out;
}

/**
 * Filtered search, done in SQL.
 *
 * $types and $wanted are validated against known keys by search.php before
 * they get here, and every value is still bound as a parameter — the IN()
 * lists are built from placeholders, never from the values themselves.
 *
 * Amenities are AND, not OR: the UI says "Must include", so a listing has to
 * have all of them. That is the HAVING COUNT(DISTINCT ...) clause.
 */
function repo_search(string $campus_id, int $max_rent, array $types, array $wanted): array
{
    $sql    = repo_listing_select() . ' WHERE campus_id = ? AND is_active = 1 AND rent <= ?';
    $params = [$campus_id, $max_rent];

    if ($types) {
        $sql .= ' AND room_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        $params = array_merge($params, array_values($types));
    }

    if ($wanted) {
        $in = implode(',', array_fill(0, count($wanted), '?'));

        /* The HAVING count is interpolated, not bound, and that is deliberate.
           With PDO::ATTR_EMULATE_PREPARES = false a bound value arrives as a
           STRING. SQLite then compares COUNT(...) — an integer — against '2'
           and never matches, so this filter silently returned nothing. MySQL
           coerces and works, which would have made it an SQLite-only bug.

           Interpolating is safe here because the value is count() of a PHP
           array cast to int — it can never contain user input. Every actual
           value below is still bound. */
        $need = (int) count($wanted);

        $sql .= " AND slug IN (
                    SELECT listing_slug FROM listing_amenities
                     WHERE amenity_id IN ($in)
                     GROUP BY listing_slug
                    HAVING COUNT(DISTINCT amenity_id) = $need
                  )";
        $params = array_merge($params, array_values($wanted));
    }

    $rows = db_all($sql, $params);
    if (!$rows) {
        return [];
    }
    $amen = repo_amenities_for(array_column($rows, 'slug'));

    $out = [];
    foreach ($rows as $r) {
        $out[$r['slug']] = repo_hydrate_listing($r, $amen[$r['slug']] ?? []);
    }
    return $out;
}

/* ======================================================================
   Aggregates for the trust strip
   ====================================================================== */

function repo_total_beds(): int
{
    return (int) db_value('SELECT COALESCE(SUM(beds), 0) FROM campuses');
}

function repo_verified_count(): int
{
    return (int) db_value('SELECT COUNT(*) FROM listings WHERE verified = 1 AND is_active = 1');
}

/* ======================================================================
   [DB-WRITE] SAVING A VIEWING REQUEST — the main capture point
   ======================================================================
   Called from apply.php after validation passes.

   Writes two tables inside one transaction so a request can never be saved
   without the rooms it named:

     viewing_requests       one row: who, when, which campus, notes
     viewing_request_rooms  one row per room requested

   Returns the new request id, or null when there is no database.
   ====================================================================== */
function repo_save_viewing_request(array $data, array $listing_slugs): ?int
{
    return db_transaction(function (PDO $pdo) use ($data, $listing_slugs) {

        $stmt = $pdo->prepare(
            'INSERT INTO viewing_requests
                (reference, full_name, email, phone, guardian_email,
                 campus_id, preferred_date, move_in_month, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['reference'],
            $data['fullName'],
            $data['email'],
            $data['phone'],
            $data['guardianEmail'] !== '' ? $data['guardianEmail'] : null,
            $data['campus'],
            $data['date'],
            $data['moveIn'],
            $data['notes'] !== '' ? $data['notes'] : null,
            'new',
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($listing_slugs) {
            $room = $pdo->prepare(
                'INSERT INTO viewing_request_rooms (request_id, listing_slug) VALUES (?, ?)'
            );
            foreach ($listing_slugs as $slug) {
                $room->execute([$id, $slug]);
            }
        }

        return $id;
    });
}

/**
 * True when a reference is already taken.
 *
 * The reference is a date plus 3 random bytes, so collisions are unlikely
 * but not impossible. apply.php retries on a clash rather than failing the
 * student's submission.
 */
function repo_reference_exists(string $reference): bool
{
    return (int) db_value(
        'SELECT COUNT(*) FROM viewing_requests WHERE reference = ?',
        [$reference]
    ) > 0;
}

/* ======================================================================
   [DB-SHORTLIST] Saved rooms — only meaningful once students have logins.
   Unused by the current front end, which keeps shortlists in localStorage.
   Wire these up from a small JSON endpoint if you add accounts.
   ====================================================================== */

function repo_shortlist_get(string $owner_key): array
{
    return array_column(
        db_all('SELECT listing_slug FROM shortlists WHERE owner_key = ? ORDER BY created_at', [$owner_key]),
        'listing_slug'
    );
}

function repo_shortlist_add(string $owner_key, string $slug): void
{
    // INSERT OR IGNORE / INSERT IGNORE differ by driver, so check first.
    $exists = (int) db_value(
        'SELECT COUNT(*) FROM shortlists WHERE owner_key = ? AND listing_slug = ?',
        [$owner_key, $slug]
    );
    if ($exists === 0) {
        db_run('INSERT INTO shortlists (owner_key, listing_slug) VALUES (?, ?)', [$owner_key, $slug]);
    }
}

function repo_shortlist_remove(string $owner_key, string $slug): void
{
    db_run('DELETE FROM shortlists WHERE owner_key = ? AND listing_slug = ?', [$owner_key, $slug]);
}
