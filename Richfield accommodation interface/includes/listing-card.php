<?php
/**
 * Reusable listing card. Expects $slug and $l in scope.
 * Shared by index.php (rail) and search.php (grid).
 */
$card_walk   = listing_walk($l);
$card_campus = get_campus($l['campus']);
$card_chips  = array_slice($l['amenities'], 0, 3);
$card_rest   = count($l['amenities']) - count($card_chips);
?>
<article class="listing-card">
  <div class="listing-card__media">
    <?= listing_image($l) ?>

    <button type="button" class="btn heart"
            data-heart="<?= e($slug) ?>"
            data-name="<?= e($l['name']) ?>"
            aria-pressed="false"
            aria-label="Save <?= e($l['name']) ?> to shortlist">
      <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.7 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>

    <?php if ($l['verified']): ?>
      <span class="badge-inspected">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
        Inspected
      </span>
    <?php endif; ?>
  </div>

  <div class="listing-card__body">
    <div class="listing-card__head">
      <h3 class="rf-display listing-card__name">
        <a href="listing.php?slug=<?= e($slug) ?>"><?= e($l['name']) ?></a>
      </h3>
      <p class="listing-card__price">
        <b><?= e(format_zar($l['rent'])) ?></b>
        <span>per month</span>
      </p>
    </div>

    <p class="listing-card__meta"><?= e($l['area']) ?> · <?= e(room_label($l['room'])) ?></p>

    <?php if ($card_campus): ?>
      <p class="listing-card__walk">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M13 4v6l3 2M8 20l2-5"/><circle cx="13" cy="3" r="1"/></svg>
        <?= $card_walk ?> min walk to <?= e($card_campus['name']) ?>
      </p>
    <?php endif; ?>

    <ul class="chips">
      <?php foreach ($card_chips as $a): ?>
        <li class="chip"><?= e(amenity($a)['short'] ?? $a) ?></li>
      <?php endforeach; ?>
      <?php if ($card_rest > 0): ?>
        <li class="chip chip--more"
            title="<?= e(implode(', ', array_map(fn($a) => amenity($a)['label'] ?? $a, array_slice($l['amenities'], 3)))) ?>">
          +<?= $card_rest ?> more
        </li>
      <?php endif; ?>
    </ul>

    <p class="listing-card__foot"><?= (int) $l['beds'] ?> beds available</p>
  </div>
</article>
