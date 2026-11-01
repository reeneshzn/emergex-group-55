<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Richfield Student Accommodation | Start Here. Stay Close.';
$page_desc  = 'Inspected, campus-linked student accommodation within walking distance of every Richfield campus. Compare rooms and book a viewing online.';
$is_home    = true;
$needs_map  = true;

$months   = upcoming_months();
$featured = array_slice(array_filter(all_listings(), fn($l) => $l['verified']), 0, 8, true);

require __DIR__ . '/includes/header.php';
?>

<!-- ====================================================================
     HERO
     ==================================================================== -->
<section class="hero">
  <span class="hero__rule" aria-hidden="true"></span>
  <span class="hero__lines" aria-hidden="true"></span>

  <div class="wrap hero__inner">
    <p class="rf-eyebrow hero__eyebrow">Campus accommodation portal</p>

    <h1 class="rf-display">Start here.<br><span>Stay close.</span></h1>

    <p class="hero__lede">
      Inspected rooms within reach of every Richfield campus. Compare what&rsquo;s
      actually available, see how far it really is, and book a viewing before you
      commit to anything.
    </p>

    <dl class="hero__stats">
      <div>
        <dt class="rf-eyebrow">Beds listed</dt>
        <dd class="rf-display tnum"><?= number_format(total_beds()) ?></dd>
      </div>
      <div>
        <dt class="rf-eyebrow">Campuses</dt>
        <dd class="rf-display tnum"><?= count(all_campuses()) ?></dd>
      </div>
      <div>
        <dt class="rf-eyebrow">Booking fee</dt>
        <dd class="rf-display">None</dd>
      </div>
    </dl>
  </div>

  <div class="wrap">
    <form class="search-widget" action="search.php" method="get">
      <div class="search-widget__grid">
        <div class="field">
          <label class="rf-eyebrow" for="hero-campus">Your campus</label>
          <select id="hero-campus" name="campus">
            <?php foreach (all_campuses() as $id => $c): ?>
              <option value="<?= e($id) ?>"<?= $id === DEFAULT_CAMPUS ? ' selected' : '' ?>>
                <?= e($c['name']) ?> · <?= e($c['city']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="rf-eyebrow" for="hero-from">Moving in</label>
          <select id="hero-from" name="from">
            <?php foreach ($months as $value => $label): ?>
              <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <div class="field__row">
            <label class="rf-eyebrow" for="hero-max">Monthly budget</label>
            <span class="field__value">up to <span id="heroMaxOut">R 6 000</span></span>
          </div>
          <input type="range" id="hero-max" name="max" min="2000" max="9000" step="250"
                 value="6000" data-money-out="heroMaxOut"
                 aria-label="Maximum monthly rent in rand">
        </div>

        <button type="submit" class="btn btn--primary">
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          Find my room
        </button>
      </div>
    </form>
  </div>
  <div class="hero__spacer" aria-hidden="true"></div>
</section>

<!-- ====================================================================
     TRUST STRIP — every figure derived from the data, never hardcoded
     ==================================================================== -->
<section class="trust" aria-label="At a glance">
  <div class="wrap">
    <dl class="trust__grid">
      <div class="trust__item">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3M2 17h20M4 17v-6h16v6M6 11V9h5v2"/></svg>
        <div><dt class="rf-eyebrow">Beds listed</dt><dd class="rf-display tnum"><?= number_format(total_beds()) ?></dd></div>
      </div>
      <div class="trust__item">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <div><dt class="rf-eyebrow">Campuses covered</dt><dd class="rf-display tnum"><?= count(all_campuses()) ?></dd></div>
      </div>
      <div class="trust__item">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <div><dt class="rf-eyebrow">Inspected listings</dt><dd class="rf-display tnum"><?= verified_count() ?></dd></div>
      </div>
      <div class="trust__item">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M4 20l4-9 3 4 3-7 6 12"/></svg>
        <div><dt class="rf-eyebrow">Median walk to campus</dt><dd class="rf-display tnum"><?= median_walk_minutes() ?> min</dd></div>
      </div>
    </dl>
  </div>
</section>

<!-- ====================================================================
     CAMPUS GRID
     ==================================================================== -->
<section class="section" id="campuses">
  <div class="wrap">
    <div class="section__head">
      <div>
        <p class="rf-eyebrow section__eyebrow">Where you study</p>
        <h2 class="rf-display section__title">Eight campuses.<br>Rooms near all of them.</h2>
      </div>
      <p class="section__lede">
        Pick your campus and we&rsquo;ll show only what&rsquo;s actually within reach
        of it — with real walking and driving times, not a vague &ldquo;close to
        campus&rdquo;.
      </p>
    </div>

    <ul class="campus-grid">
      <?php foreach (all_campuses() as $id => $c): $n = count(listings_for_campus($id)); ?>
        <li class="reveal">
          <a class="campus-card" href="search.php?campus=<?= e($id) ?>">
            <span class="campus-card__top">
              <span class="rf-display campus-card__name"><?= e($c['name']) ?></span>
              <svg viewBox="0 0 24 24" class="icon campus-card__arrow" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>
            </span>
            <span class="campus-card__place"><?= e($c['city']) ?>, <?= e($c['province']) ?></span>
            <dl class="campus-card__stats">
              <div><dt>Beds</dt><dd><?= (int) $c['beds'] ?></dd></div>
              <div><dt>Listings</dt><dd><?= $n ?></dd></div>
            </dl>
            <?php if (!$c['confirmed']): ?>
              <span class="badge-unconfirmed">Location to confirm</span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ====================================================================
     FEATURED LISTINGS
     ==================================================================== -->
<section class="section section--sunken" id="rooms">
  <div class="wrap">
    <div class="section__head">
      <div>
        <p class="rf-eyebrow section__eyebrow">Inspected listings</p>
        <h2 class="rf-display section__title">Rooms we&rsquo;ve walked through</h2>
        <p class="section__lede">
          Someone has physically visited every listing marked &ldquo;Inspected&rdquo;
          — checked the security, the water pressure, the Wi-Fi, and how long the
          walk really takes.
        </p>
      </div>
      <a class="btn btn--outline" href="search.php">
        Browse all rooms
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>

    <div class="listing-rail">
      <?php foreach ($featured as $slug => $l): require __DIR__ . '/includes/listing-card.php'; endforeach; ?>
    </div>
  </div>
</section>

<!-- ====================================================================
     MAP
     ==================================================================== -->
<section class="section" id="map-section">
  <div class="wrap">
    <div class="section__head">
      <div>
        <p class="rf-eyebrow section__eyebrow">How far, really</p>
        <h2 class="rf-display section__title">See the distance<br>before you sign</h2>
        <p class="section__lede">
          &ldquo;Close to campus&rdquo; means nothing. Every room is plotted against
          your campus with an honest walking and driving estimate.
        </p>
      </div>
      <div class="field" style="max-width:20rem;width:100%">
        <label class="rf-eyebrow" for="mapCampus">Campus</label>
        <select id="mapCampus">
          <?php foreach (all_campuses() as $id => $c): ?>
            <option value="<?= e($id) ?>"<?= $id === DEFAULT_CAMPUS ? ' selected' : '' ?>>
              <?= e($c['name']) ?> · <?= e($c['city']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="map-layout">
      <div>
        <?php foreach (all_campuses() as $id => $c): ?>
          <ul class="map-list" data-campus-list="<?= e($id) ?>"<?= $id === DEFAULT_CAMPUS ? '' : ' hidden' ?>>
            <?php foreach (listings_for_campus($id) as $slug => $l): ?>
              <li>
                <a href="listing.php?slug=<?= e($slug) ?>">
                  <span>
                    <span class="map-list__name"><?= e($l['name']) ?></span>
                    <span class="map-list__meta"><?= e($l['area']) ?> · <?= e(room_label($l['room'])) ?></span>
                    <span class="map-list__times">
                      <span><svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M13 4v6l3 2M8 20l2-5"/><circle cx="13" cy="3" r="1"/></svg><?= listing_walk($l) ?> min walk</span>
                      <span><svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M5 17h14M6 17V9l2-4h8l2 4v8"/><circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/></svg><?= listing_drive($l) ?> min drive</span>
                    </span>
                  </span>
                  <span class="map-list__price"><?= e(format_zar($l['rent'])) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
        <p class="note">Times are straight-line estimates, not street routing.</p>
      </div>

      <div class="map-canvas" id="map" data-campus="<?= e(DEFAULT_CAMPUS) ?>"></div>
    </div>
  </div>
</section>

<!-- ====================================================================
     PAYMENT CALCULATOR
     ==================================================================== -->
<section class="section section--sunken" id="costs">
  <div class="wrap">
    <p class="rf-eyebrow section__eyebrow">What it actually costs</p>
    <h2 class="rf-display section__title">No surprises<br>on move-in day</h2>
    <p class="section__lede">
      Work out the full cost before you commit — deposit, admin fee and rent, in
      the payment rhythm that suits you.
    </p>

    <div class="calc" id="calc">
      <div class="calc__controls">
        <div class="tabs" role="tablist" aria-label="Payment plan">
          <button type="button" class="tab" role="tab" data-plan="monthly" aria-selected="true">Monthly</button>
          <button type="button" class="tab" role="tab" data-plan="termly"  aria-selected="false">Per term</button>
          <button type="button" class="tab" role="tab" data-plan="upfront" aria-selected="false">Upfront</button>
        </div>

        <div class="calc__fields">
          <div class="field">
            <div class="field__row">
              <label class="calc__label" for="calcRent">Monthly rent</label>
              <span class="calc__big" id="calcRentOut">R 4 500</span>
            </div>
            <input type="range" id="calcRent" min="2000" max="9000" step="100" value="4500"
                   data-money-out="calcRentOut" aria-label="Monthly rent in rand">
          </div>

          <div class="calc__pair">
            <div class="field">
              <label class="calc__label" for="calcLease">Lease length</label>
              <select id="calcLease">
                <option value="6">6 months</option>
                <option value="10">10 months (academic)</option>
                <option value="12" selected>12 months</option>
              </select>
            </div>
            <div class="field">
              <label class="calc__label" for="calcDeposit">Deposit</label>
              <select id="calcDeposit">
                <option value="0">No deposit</option>
                <option value="1" selected>1 month</option>
                <option value="2">2 months</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="calc__panel">
        <p class="rf-eyebrow calc__panel-eyebrow">Your estimate</p>
        <p class="calc__duenow-label">Due before you move in</p>
        <p class="rf-display calc__duenow" id="calcDueNow">R 9 950</p>

        <hr class="calc__rule">

        <dl class="calc__lines">
          <div>
            <dt id="calcInstalmentLabel">12 × monthly instalment</dt>
            <dd><b id="calcInstalment">R 4 500</b></dd>
          </div>
          <div>
            <dt>Deposit (refundable)</dt>
            <dd id="calcDeposit_out">R 4 500</dd>
          </div>
          <div>
            <dt>Once-off admin fee</dt>
            <dd id="calcAdmin">R 950</dd>
          </div>
          <div class="calc__discount" id="calcDiscountRow" hidden>
            <dt id="calcDiscountLabel">Discount on rent</dt>
            <dd><b id="calcDiscount">R 0</b></dd>
          </div>
        </dl>

        <hr class="calc__rule">

        <div class="calc__total">
          <b>Total over the lease</b>
          <span class="rf-display" id="calcTotal">R 59 450</span>
        </div>

        <p class="calc__disclaimer">
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          Estimate only. Final amounts are confirmed by the accommodation provider
          before you sign anything.
        </p>
      </div>
    </div>

    <!-- Funding language must match Richfield's actual position. They are a
         PRIVATE institution and therefore NOT NSFAS-eligible. Never imply
         otherwise anywhere on this site. -->
    <div class="funding-note">
      <h3 class="rf-display">Paying for it</h3>
      <p>
        Richfield students are supported through flexible payment plans, internal
        bursary opportunities, and partnerships with private financial institutions
        for loan financing. Speak to your campus finance office about which of these
        applies to you.
      </p>
    </div>
  </div>
</section>

<!-- ====================================================================
     HOW IT WORKS
     ==================================================================== -->
<section class="section" id="how">
  <div class="wrap">
    <p class="rf-eyebrow section__eyebrow">Three steps</p>
    <h2 class="rf-display section__title" style="max-width:40rem">
      Finding a room shouldn&rsquo;t be the hard part of your degree
    </h2>

    <ol class="steps">
      <li class="reveal">
        <div class="step">
          <span class="rf-display step__n">01</span>
          <h3 class="rf-display step__t">Search your campus</h3>
          <p class="step__b">Pick your campus and budget. You only ever see rooms that are genuinely within reach of where you study — with honest distances, not marketing language.</p>
        </div>
      </li>
      <li class="reveal">
        <div class="step" style="padding-left:4%">
          <span class="rf-display step__n">02</span>
          <h3 class="rf-display step__t">Book a viewing</h3>
          <p class="step__b">Shortlist up to four and request viewings for all of them at once. It is free, it commits you to nothing, and the provider contacts you directly to confirm a time.</p>
        </div>
      </li>
      <li class="reveal">
        <div class="step" style="padding-left:8%">
          <span class="rf-display step__n">03</span>
          <h3 class="rf-display step__t">Move in</h3>
          <p class="step__b">Sign directly with the accommodation provider once you have seen the place. You know the full cost before you commit, because you worked it out here first.</p>
        </div>
      </li>
    </ol>
  </div>
</section>

<!-- ====================================================================
     PARENTS
     ==================================================================== -->
<section class="section section--navy" id="parents">
  <div class="wrap">
    <div class="split">
      <div class="split__left">
        <p class="rf-eyebrow section__eyebrow">For parents and guardians</p>
        <h2 class="rf-display section__title">You are paying for it.<br>You should see it.</h2>
        <p class="section__lede" style="max-width:28rem">
          Most student accommodation is chosen in a hurry, from photos, by someone
          who has never signed a lease before. This is built so that does not have
          to happen.
        </p>
        <a class="btn btn--light" href="apply.php" style="margin-top:32px">Arrange a viewing together</a>
      </div>

      <ul class="parent-points split__right">
        <li>
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          <h3>Someone has been there</h3>
          <p>Inspected listings have been physically visited. We check secure entry, locks, hot water, electrics, backup power and measured internet speed — and we record what we found rather than summarising it into a score.</p>
        </li>
        <li>
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
          <h3>Plain-language leases</h3>
          <p>Every listing states the lease terms offered before you enquire: length, notice period, what happens over the December break, and whether the room can be sublet.</p>
        </li>
        <li>
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M20 12V8H6a2 2 0 0 1 0-4h12v4M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>
          <h3>Deposits held by the provider</h3>
          <p>The deposit goes to the accommodation provider, not to Richfield and not to this platform. The listing states the amount and the refund conditions up front.</p>
        </li>
        <li>
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <h3>Your details are on the request</h3>
          <?php /* DEF-07 — this previously promised that a guardian
                   "receives the same confirmation and lease summary".
                   No email is sent by this release, so the claim was
                   untrue. Restore it only when email delivery is built. */ ?>
          <p>Add your email to your student&rsquo;s viewing request and the accommodation provider can include you when they make contact. Nothing is agreed without you being part of the conversation.</p>
        </li>
      </ul>
    </div>
  </div>
</section>

<!-- ====================================================================
     FAQ
     ==================================================================== -->
<section class="section" id="faq">
  <div class="wrap">
    <div class="split">
      <div class="split__left">
        <p class="rf-eyebrow section__eyebrow">Questions</p>
        <h2 class="rf-display section__title">Straight answers</h2>
        <p class="section__lede">Including the ones other platforms avoid.</p>
      </div>

      <div class="faq split__right">
        <?php foreach (all_faqs() as $i => $f): ?>
          <details<?= $i === 0 ? ' open' : '' ?>>
            <summary>
              <?= e($f['q']) ?>
              <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </summary>
            <p><?= e($f['a']) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ====================================================================
     CLOSING CTA
     ==================================================================== -->
<section class="cta-band">
  <span class="cta-band__rule" aria-hidden="true"></span>
  <div class="wrap cta-band__inner">
    <div>
      <h2 class="rf-display">Ready to see it in person?</h2>
      <p>Book a viewing at up to four places at once. It&rsquo;s free, and it commits you to nothing.</p>
    </div>
    <a class="btn btn--primary" href="apply.php" style="height:48px;padding-inline:28px">
      Book a viewing
      <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
