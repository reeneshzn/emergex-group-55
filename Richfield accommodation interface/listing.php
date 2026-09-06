<?php
require_once __DIR__ . '/includes/functions.php';

$slug    = $_GET['slug'] ?? '';
$listing = get_listing($slug);

if (!$listing) {
    http_response_code(404);
    $page_title = 'Room not found | Richfield Student Accommodation';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="wrap" style="padding-block:112px;text-align:center;max-width:640px">
      <p class="rf-eyebrow section__eyebrow">404</p>
      <h1 class="rf-display section__title">That room isn&rsquo;t listed</h1>
      <p class="section__lede" style="margin-inline:auto">
        It may have been taken, or the link may be out of date. Have a look at what
        else is available near your campus.
      </p>
      <a class="btn btn--navy" href="search.php" style="margin-top:32px">Browse rooms</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = $listing['name'] . ', ' . $listing['area'] . ' | Richfield Student Accommodation';
$page_desc  = $listing['blurb'];
$needs_map  = true;

$campus = get_campus($listing['campus']);
$walk   = listing_walk($listing);
$drive  = listing_drive($listing);
$costs  = calculate_payment($listing['rent'], 12, 1, 'monthly');

require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div style="padding-top:32px">
    <a class="btn btn--ghost" href="search.php" style="padding-left:8px">
      <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Back to search
    </a>
  </div>

  <div class="detail">
    <div>
      <div class="detail__hero"><?= listing_image($listing) ?></div>

      <div class="detail__head">
        <div>
          <h1 class="rf-display"><?= e($listing['name']) ?></h1>
          <p class="detail__place"><?= e($listing['area']) ?> · <?= e(room_label($listing['room'])) ?></p>
        </div>
        <?php if ($listing['verified']): ?>
          <span class="badge-inspected badge-inspected--static">
            <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
            Inspected
          </span>
        <?php endif; ?>
      </div>

      <p class="detail__blurb"><?= e($listing['blurb']) ?></p>

      <hr>

      <h2 class="rf-display">What&rsquo;s included</h2>
      <ul class="chips" style="margin-top:16px">
        <?php foreach ($listing['amenities'] as $a): ?>
          <li class="chip chip--md"><?= e(amenity($a)['label'] ?? $a) ?></li>
        <?php endforeach; ?>
      </ul>

      <hr>

      <h2 class="rf-display">Getting to campus</h2>
      <?php if ($campus): ?>
        <dl class="detail__times">
          <div>
            <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M13 4v6l3 2M8 20l2-5"/><circle cx="13" cy="3" r="1"/></svg>
            <div><dt>Walk</dt><dd><?= $walk ?> min</dd></div>
          </div>
          <div>
            <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M5 17h14M6 17V9l2-4h8l2 4v8"/><circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/></svg>
            <div><dt>Drive</dt><dd><?= $drive ?> min</dd></div>
          </div>
          <div>
            <dt>Campus</dt>
            <dd>Richfield <?= e($campus['name']) ?></dd>
          </div>
        </dl>
      <?php endif; ?>

      <div class="map-canvas" id="map" data-campus="<?= e($listing['campus']) ?>"
           style="height:380px;margin-top:24px"></div>
      <p class="note">Times are straight-line estimates, not street routing.</p>
    </div>

    <aside class="buybox-wrap">
      <div class="buybox">
        <p class="rf-display buybox__price">
          <?= e(format_zar($listing['rent'])) ?><small> per month</small>
        </p>
        <p class="buybox__beds"><?= (int) $listing['beds'] ?> beds available</p>

        <hr>

        <dl>
          <div><dt>Deposit (1 month)</dt><dd><?= e(format_zar($costs['deposit'])) ?></dd></div>
          <div><dt>Once-off admin fee</dt><dd><?= e(format_zar($costs['admin'])) ?></dd></div>
          <div class="row--total"><dt>Due before move-in</dt><dd><?= e(format_zar($costs['due_now'])) ?></dd></div>
        </dl>

        <p class="note">Based on a 12-month lease. Estimate only.</p>

        <a class="btn btn--primary btn--block" style="height:48px;margin-top:24px"
           href="apply.php?listings=<?= e($slug) ?>">Book a viewing</a>

        <p class="note" style="text-align:center">Free, and commits you to nothing.</p>
      </div>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
