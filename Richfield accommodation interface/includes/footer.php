</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">

      <div>
        <span class="wordmark wordmark--stacked">
          <img class="wordmark__logo" src="assets/img/accommodation.png"
               alt="" width="512" height="512" aria-hidden="true">
          <span class="wordmark__text">
            <span class="rf-display wordmark__name wordmark__name--light">EMERGEX</span>
            <span class="rf-eyebrow wordmark__sub wordmark__sub--light">Since 2026</span>
          </span>
        </span>
        <p class="footer-blurb">
          EMERGEX lists inspected student accommodation within reach of every
          Richfield campus. Compare rooms, save your favourites, and book
          viewings in one place.
        </p>
      </div>

      <div>
        <h2 class="rf-eyebrow footer-heading">Campuses</h2>
        <ul class="footer-list">
          <?php foreach (all_campuses() as $id => $c): ?>
            <li>
              <a href="search.php?campus=<?= e($id) ?>">
                <?= e($c['name']) ?><span class="dim"> · <?= e($c['city']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h2 class="rf-eyebrow footer-heading">For students</h2>
        <ul class="footer-list">
          <li><a href="search.php">Find a room</a></li>
          <li><a href="index.php#costs">What it costs</a></li>
          <li><a href="index.php#how">How it works</a></li>
          <li><a href="apply.php">Book a viewing</a></li>
        </ul>
      </div>

      <div>
        <h2 class="rf-eyebrow footer-heading">For parents</h2>
        <ul class="footer-list">
          <li><a href="index.php#parents">For parents and guardians</a></li>
          <li><a href="index.php#parents">What inspection covers</a></li>
          <li><a href="index.php#faq">Deposits and leases</a></li>
          <li><a href="index.php#faq">Frequently asked questions</a></li>
        </ul>
      </div>
    </div>

    <!-- Required disclaimer. This prototype uses a real institution's identity
         without approval; the line stays until Richfield signs off. -->
    <div class="footer-legal">
      <p>
        Prototype built for evaluation purposes. This is not an official Richfield
        service and is not affiliated with, endorsed by, or approved by Richfield
        Graduate Institute of Technology. All listings shown are sample data.
      </p>
      <p class="footer-attrib">
        Map data &copy;
        <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a>
        contributors.
      </p>
      <p class="footer-built">
        Built with <span class="footer-heart" aria-hidden="true">&#128153;</span><span class="sr-only">love</span> by EMERGEX developers
      </p>
    </div>
  </div>
</footer>

<!-- Shortlist drawer, shared across every page -->
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
<aside class="drawer" id="shortlistDrawer" aria-label="Your shortlist" aria-hidden="true" hidden>
  <div class="drawer__head">
    <div>
      <h2 class="rf-display drawer__title">Your shortlist</h2>
      <p class="drawer__sub"><span id="drawerCount">0</span> of 4 saved. Compare them, then book all your viewings in one go.</p>
    </div>
    <button type="button" class="btn btn--ghost" id="shortlistClose" aria-label="Close shortlist">
      <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="drawer__body" id="drawerBody"></div>
  <div class="drawer__foot" id="drawerFoot" hidden>
    <a class="btn btn--primary btn--block" id="drawerBookAll" href="apply.php">Book viewings for all</a>
    <button type="button" class="btn btn--ghost btn--block" id="drawerClear">Clear shortlist</button>
  </div>
</aside>

<?php
/**
 * Hand the client the data it needs. The shortlist drawer lives on every
 * page, so the listing index ships everywhere; the map data rides along
 * because it is the same records.
 *
 * [DB-A] NOT AN INTEGRATION POINT — BUT READ THIS ONCE THE DB IS IN
 * ---------------------------------------------------------------------
 * This embeds EVERY listing into EVERY page as JSON. Harmless at 18 rows
 * (about 12 KB). At a few hundred it becomes the largest thing on the
 * page, and it does not shrink just because the data moved to a table.
 *
 * When the catalogue grows, send only what the page needs:
 *   - the shortlist drawer needs the saved slugs, which it can fetch on
 *     open rather than having every listing preloaded
 *   - the map needs only the current campus's listings
 *
 * Nothing secret goes in here, and it must stay that way — this is
 * public page source. Do not let a wider SELECT * leak provider contact
 * details or anything from the requests table into this payload.
 */
$rf_listings = [];
foreach (all_listings() as $slug => $l) {
    $rf_listings[$slug] = [
        'name'      => $l['name'],
        'area'      => $l['area'],
        'room'      => room_label($l['room']),
        'rent'      => $l['rent'],
        'campus'    => $l['campus'],
        'lat'       => $l['lat'],
        'lng'       => $l['lng'],
        'walk'      => listing_walk($l),
        'drive'     => listing_drive($l),
        'amenities' => count($l['amenities']),
        'thumb'     => listing_image($l),
    ];
}
?>
<script>
window.RF = {
  listings: <?= json_encode($rf_listings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  campuses: <?= json_encode(all_campuses(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<?php if (!empty($needs_map)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<?php endif; ?>
<?php
/* Same cache-bust as the stylesheet, for the same reason. */
$js_path = __DIR__ . '/../assets/js/app.js';
$js_ver  = is_file($js_path) ? filemtime($js_path) : '1';
?>
<script src="assets/js/app.js?v=<?= e((string) $js_ver) ?>"></script>
</body>
</html>
