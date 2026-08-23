<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';

$featured = [];
try {
    $featured = db()->query("SELECT p.*, u.first_name AS landlord_name FROM properties p JOIN users u ON u.id = p.landlord_id WHERE p.status = 'published' AND p.verified_at IS NOT NULL ORDER BY p.instant_book DESC, p.rating DESC LIMIT 3")->fetchAll();
} catch (Throwable) {
    // The landing page remains useful before the database installer has run.
}

$pageTitle = 'Secure student accommodation';
require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Search smart. Book safely. Move confidently.</p>
        <h1>A safer route to your student home.</h1>
        <p class="lead">Compare verified accommodation, inspect rich visual listings and reserve with a transparent deposit-hold process.</p>
        <form class="hero-search" action="<?= e(url('properties.php')) ?>" method="get">
            <label class="sr-only" for="home-search">City, suburb or university</label>
            <input id="home-search" name="q" type="search" placeholder="City, suburb or university" autocomplete="off">
            <button class="button" type="submit">Search homes</button>
        </form>
        <div class="trust-row" aria-label="Platform highlights">
            <span>Verified landlords</span><span>NSFAS filters</span><span>Escrow simulator</span>
        </div>
    </div>
    <div class="hero-art" aria-label="EMERGEX booking journey illustration">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="hero-card">
            <span class="status-dot"></span>
            <small>Booking protected</small>
            <strong>Deposit held securely</strong>
            <p>Release only after check-in confirmation.</p>
        </div>
    </div>
</section>

<section class="section feature-strip">
    <article><span class="feature-number">01</span><h2>Verified first</h2><p>Landlords, properties and accreditation evidence are reviewed before trust badges appear.</p></article>
    <article><span class="feature-number">02</span><h2>See the full picture</h2><p>Floor plans, walkthrough videos and property details reduce unpleasant surprises.</p></article>
    <article><span class="feature-number">03</span><h2>Book with clarity</h2><p>Instant Book or a timed request flow keeps every next step and status visible.</p></article>
</section>

<?php if ($featured): ?>
<section class="section">
    <div class="section-heading"><div><p class="eyebrow">Verified picks</p><h2>Ready when you are</h2></div><a href="<?= e(url('properties.php')) ?>">View all properties</a></div>
    <div class="property-grid">
    <?php foreach ($featured as $property): ?>
        <article class="property-card">
            <a href="<?= e(url('property.php?id=' . (int) $property['id'])) ?>">
                <img src="<?= e(url('assets/img/' . $property['cover_image'])) ?>" alt="Exterior illustration of <?= e($property['title']) ?>">
                <div class="property-card-body">
                    <div class="badges"><span class="badge badge-verified">Verified</span><?php if ($property['instant_book']): ?><span class="badge">Instant Book</span><?php endif; ?></div>
                    <h3><?= e($property['title']) ?></h3>
                    <p><?= e($property['suburb']) ?>, <?= e($property['city']) ?></p>
                    <div class="property-meta"><strong><?= money($property['monthly_rent']) ?></strong><span>/ month</span><span><?= number_format((float) $property['rating'], 1) ?> rating</span></div>
                </div>
            </a>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section split-callout">
    <div><p class="eyebrow">For accommodation providers</p><h2>Fill verified beds without losing control.</h2></div>
    <div><p>Publish rich listings, review applications, respond within the platform and monitor simulated deposit milestones from one dashboard.</p><a class="button button-light" href="<?= e(url('register.php?role=landlord')) ?>">List a property</a></div>
</section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
