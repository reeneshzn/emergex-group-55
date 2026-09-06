<?php
/**
 * ==========================================================================
 * Database setup — creates the tables and loads the starting data.
 * ==========================================================================
 *
 * Run it from the project root:
 *
 *     php database/seed.php
 *
 * It reads includes/config.php to decide which database to talk to, imports
 * the matching schema file, then copies everything out of includes/data.php
 * into the tables.
 *
 * SAFE TO RE-RUN. Existing rows are updated rather than duplicated, and the
 * viewing_requests tables are never touched — re-seeding will not delete
 * anybody's submitted request.
 *
 * Pass --fresh to drop the reference tables first and start clean:
 *
 *     php database/seed.php --fresh
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/data.php';

$fresh = in_array('--fresh', $argv ?? [], true);

function out(string $line): void
{
    echo $line, PHP_EOL;
}

/* ---------------------------------------------------------------- checks */

$cfg = db_config();
if ($cfg === null) {
    out('No includes/config.php found.');
    out('');
    out('  copy includes\config.sample.php includes\config.php');
    out('');
    out('Then edit it and run this again.');
    exit(1);
}

$pdo = db();
if (!$pdo) {
    out('Could not connect using includes/config.php.');
    out('Driver: ' . ($cfg['driver'] ?? '?'));
    if (($cfg['driver'] ?? '') === 'mysql') {
        out('Is MySQL running in the XAMPP control panel?');
        out('Does the database "' . ($cfg['database'] ?? '?') . '" exist?');
        out('Import database/schema.mysql.sql first — it creates it.');
    }
    exit(1);
}

$driver = $cfg['driver'];
out('Connected using ' . $driver . '.');

/* ---------------------------------------------------------------- schema */

$schema = __DIR__ . '/schema.' . ($driver === 'sqlite' ? 'sqlite' : 'mysql') . '.sql';
if (!is_file($schema)) {
    out('Schema file missing: ' . $schema);
    exit(1);
}

if ($fresh) {
    out('Dropping reference tables (--fresh)…');
    foreach (['listing_amenities', 'listings', 'faqs', 'amenities', 'room_types'] as $t) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS ' . $t);
        } catch (PDOException $e) {
            // A foreign key may block the drop; the CREATEs below still work.
        }
    }
}

out('Applying ' . basename($schema) . '…');
$sql = file_get_contents($schema);

if ($driver === 'sqlite') {
    // CREATE DATABASE / USE are MySQL-only; SQLite has neither.
    $pdo->exec($sql);
} else {
    // Split on semicolons at end of line — enough for this file, which has
    // no stored procedures or semicolons inside string literals.
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql))) as $stmt) {
        if ($stmt !== '' && !str_starts_with($stmt, '--')) {
            $pdo->exec($stmt);
        }
    }
}
out('Schema applied.');

/* ------------------------------------------------------------------ data */

/** Insert or update, without needing driver-specific UPSERT syntax. */
function upsert(PDO $pdo, string $table, string $key, array $row): void
{
    $exists = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $key = ?");
    $exists->execute([$row[$key]]);

    if ((int) $exists->fetchColumn() > 0) {
        $sets = [];
        $vals = [];
        foreach ($row as $col => $val) {
            if ($col === $key) {
                continue;
            }
            $sets[] = "$col = ?";
            $vals[] = $val;
        }
        $vals[] = $row[$key];
        $pdo->prepare("UPDATE $table SET " . implode(', ', $sets) . " WHERE $key = ?")
            ->execute($vals);
    } else {
        $cols = implode(', ', array_keys($row));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        $pdo->prepare("INSERT INTO $table ($cols) VALUES ($marks)")
            ->execute(array_values($row));
    }
}

$pdo->beginTransaction();

try {
    $order = 0;
    foreach (CAMPUSES as $id => $c) {
        upsert($pdo, 'campuses', 'id', [
            'id'         => $id,
            'name'       => $c['name'],
            'city'       => $c['city'],
            'province'   => $c['province'],
            'lat'        => $c['lat'],
            'lng'        => $c['lng'],
            'confirmed'  => $c['confirmed'] ? 1 : 0,
            'beds'       => $c['beds'],
            'sort_order' => $order++,
        ]);
    }
    out('Campuses: ' . count(CAMPUSES));

    $order = 0;
    foreach (ROOM_TYPES as $id => $label) {
        upsert($pdo, 'room_types', 'id', [
            'id' => $id, 'label' => $label, 'sort_order' => $order++,
        ]);
    }
    out('Room types: ' . count(ROOM_TYPES));

    $order = 0;
    foreach (AMENITIES as $id => $a) {
        upsert($pdo, 'amenities', 'id', [
            'id'         => $id,
            'short'      => $a['short'],
            'label'      => $a['label'],
            'sort_order' => $order++,
        ]);
    }
    out('Amenities: ' . count(AMENITIES));

    $links = 0;
    foreach (LISTINGS as $slug => $l) {
        upsert($pdo, 'listings', 'slug', [
            'slug'      => $slug,
            'name'      => $l['name'],
            'area'      => $l['area'],
            'campus_id' => $l['campus'],
            'room_type' => $l['room'],
            'lat'       => $l['lat'],
            'lng'       => $l['lng'],
            'rent'      => $l['rent'],
            'beds'      => $l['beds'],
            'verified'  => $l['verified'] ? 1 : 0,
            'seed'      => $l['seed'],
            'blurb'     => $l['blurb'],
            'is_active' => 1,
        ]);

        $pdo->prepare('DELETE FROM listing_amenities WHERE listing_slug = ?')->execute([$slug]);
        $ins = $pdo->prepare('INSERT INTO listing_amenities (listing_slug, amenity_id) VALUES (?, ?)');
        foreach ($l['amenities'] as $a) {
            $ins->execute([$slug, $a]);
            $links++;
        }
    }
    out('Listings: ' . count(LISTINGS) . ' (' . $links . ' amenity links)');

    // FAQs have no natural key, so replace them wholesale.
    $pdo->exec('DELETE FROM faqs');
    $ins = $pdo->prepare('INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, 1)');
    foreach (FAQS as $i => $f) {
        $ins->execute([$f['q'], $f['a'], $i]);
    }
    out('FAQs: ' . count(FAQS));

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    out('FAILED, nothing was written: ' . $e->getMessage());
    exit(1);
}

out('');
out('Done. The site will now read from the database.');
out('Open index.php — the footer says which source is live.');
