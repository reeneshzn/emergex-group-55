<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Find a room | Richfield Student Accommodation';
$page_desc  = "Search inspected student accommodation near your Richfield campus by budget, room type and what's included.";
$needs_map  = true;

/* Filter state lives in the URL, so any filtered view is a shareable link. */
$campus_id = isset($_GET['campus']) && get_campus($_GET['campus']) ? $_GET['campus'] : DEFAULT_CAMPUS;
$max_rent  = isset($_GET['max']) ? max(2000, min(9000, (int) $_GET['max'])) : 9000;
$types     = isset($_GET['type']) ? array_intersect((array) $_GET['type'], array_keys(all_room_types())) : [];
$wanted    = isset($_GET['amenity']) ? array_intersect((array) $_GET['amenity'], array_keys(all_amenities())) : [];

/* ======================================================================
   [DB-5] SEARCH FILTERING — runs as SQL when a database is connected
   ----------------------------------------------------------------------
   search_listings() in functions.php picks the path:

     database connected -> repo_search() builds a WHERE clause
     no database        -> the same filters applied in PHP over sample data

   Both return the same shape, keyed by slug, sorted by walking distance.

   SECURITY: all four inputs come from $_GET. They are validated on the
   lines above — the campus must exist, rent is clamped to 2000-9000, and
   room types and amenities are intersected against known keys — and every
   value is then bound as a parameter in repo_search(). The IN() lists are
   built from placeholders, never from the values themselves.
   ====================================================================== */
$campus  = get_campus($campus_id);
$results = search_listings($campus_id, $max_rent, $types, $wanted);

require __DIR__ . '/includes/header.php';
?>

<div style="background:var(--rf-surface-sunken)">
  <div class="wrap" style="padding-block:48px 64px">

    <p class="rf-eyebrow section__eyebrow">Find a room</p>
    <h1 class="rf-display section__title">Rooms near your campus</h1>

    <div class="search-layout">

      <!-- Filters -->
      <aside>
        <form class="filters" method="get" action="search.php">
          <div class="filters__head">
            <h2 class="rf-display filters__title">Filters</h2>
            <a class="btn btn--ghost btn--sm" href="search.php?campus=<?= e($campus_id) ?>">Clear all</a>
          </div>

          <hr>

          <div class="field">
            <label class="rf-eyebrow" for="f-campus">Campus</label>
            <select id="f-campus" name="campus" onchange="this.form.submit()">
              <?php foreach (all_campuses() as $id => $c): ?>
                <option value="<?= e($id) ?>"<?= $id === $campus_id ? ' selected' : '' ?>>
                  <?= e($c['name']) ?> · <?= e($c['city']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <hr>

          <div class="field">
            <div class="field__row">
              <label class="rf-eyebrow" for="f-max">Max rent</label>
              <span class="field__value" id="fMaxOut"><?= e(format_zar($max_rent)) ?></span>
            </div>
            <input type="range" id="f-max" name="max" min="2000" max="9000" step="250"
                   value="<?= $max_rent ?>" data-money-out="fMaxOut"
                   aria-label="Maximum monthly rent in rand">
          </div>

          <hr>

          <fieldset>
            <legend class="rf-eyebrow">Room type</legend>
            <?php foreach (all_room_types() as $key => $label): ?>
              <label class="check">
                <input type="checkbox" name="type[]" value="<?= e($key) ?>"
                       <?= in_array($key, $types, true) ? 'checked' : '' ?>>
                <?= e($label) ?>
              </label>
            <?php endforeach; ?>
          </fieldset>

          <hr>

          <fieldset>
            <legend class="rf-eyebrow">Must include</legend>
            <?php foreach (all_amenities() as $key => $a): ?>
              <label class="check">
                <input type="checkbox" name="amenity[]" value="<?= e($key) ?>"
                       <?= in_array($key, $wanted, true) ? 'checked' : '' ?>>
                <?= e($a['short']) ?>
              </label>
            <?php endforeach; ?>
          </fieldset>

          <button type="submit" class="btn btn--navy btn--block" style="margin-top:20px">Apply filters</button>
        </form>
      </aside>

      <!-- Results -->
      <div class="results-layout">
        <div>
          <p class="results-count" aria-live="polite">
            <b><?= count($results) ?></b>
            <?= count($results) === 1 ? 'room' : 'rooms' ?>
            near Richfield <?= e($campus['name']) ?><?= count($results) > 0 ? ', closest first' : '' ?>
          </p>

          <?php if (count($results) === 0): ?>
            <div class="empty-state">
              <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M9 9l4 4M13 9l-4 4"/></svg>
              <h2 class="rf-display">Nothing matches yet</h2>
              <p>Try raising your budget or removing a filter.</p>
              <a class="btn btn--outline" href="search.php?campus=<?= e($campus_id) ?>" style="margin-top:20px">Clear filters</a>
            </div>
          <?php else: ?>
            <ul class="listing-grid" style="margin-top:24px">
              <?php foreach ($results as $slug => $l): ?>
                <li><?php require __DIR__ . '/includes/listing-card.php'; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="results-map">
          <div class="map-canvas" id="map" data-campus="<?= e($campus_id) ?>"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
