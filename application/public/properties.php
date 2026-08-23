<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$maxPrice = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT) ?: null;
$nsfas = isset($_GET['nsfas']);
$instant = isset($_GET['instant']);

$where = ["p.status = 'published'", 'p.verified_at IS NOT NULL'];
$params = [];
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.city LIKE ? OR p.suburb LIKE ? OR p.near_institution LIKE ?)';
    $term = '%' . $q . '%';
    array_push($params, $term, $term, $term, $term);
}
if ($maxPrice !== null) {
    $where[] = 'p.monthly_rent <= ?';
    $params[] = $maxPrice;
}
if ($nsfas) {
    $where[] = 'p.nsfas_accredited = 1';
}
if ($instant) {
    $where[] = 'p.instant_book = 1';
}

$properties = [];
$databaseError = false;
try {
    $sql = 'SELECT p.*, u.first_name AS landlord_name FROM properties p JOIN users u ON u.id = p.landlord_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.instant_book DESC, p.rating DESC, p.monthly_rent ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();
} catch (Throwable) {
    $databaseError = true;
}

$pageTitle = 'Find accommodation';
require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="page-hero compact"><p class="eyebrow">Verified accommodation</p><h1>Find a place that fits.</h1><p>Filter by location, price, NSFAS status and booking speed.</p></section>
<section class="section listing-layout">
    <aside class="filters">
        <form method="get" action="<?= e(url('properties.php')) ?>">
            <h2>Filter homes</h2>
            <label for="q">Location or institution</label><input id="q" type="search" name="q" value="<?= e($q) ?>">
            <label for="max-price">Maximum monthly rent</label><input id="max-price" type="number" min="1000" step="100" name="max_price" value="<?= e($maxPrice === null ? '' : (string) $maxPrice) ?>" placeholder="e.g. 6000">
            <label class="check"><input type="checkbox" name="nsfas" value="1" <?= $nsfas ? 'checked' : '' ?>> NSFAS accredited only</label>
            <label class="check"><input type="checkbox" name="instant" value="1" <?= $instant ? 'checked' : '' ?>> Instant Book only</label>
            <button class="button button-block" type="submit">Apply filters</button>
            <a class="clear-link" href="<?= e(url('properties.php')) ?>">Clear filters</a>
        </form>
    </aside>
    <div>
        <div class="results-heading"><h2><?= count($properties) ?> <?= count($properties) === 1 ? 'home' : 'homes' ?></h2><span>Sorted by booking speed and rating</span></div>
        <?php if ($databaseError): ?><div class="empty-state"><h3>Database setup required</h3><p>Run the included installer before browsing seeded properties.</p></div><?php elseif (!$properties): ?><div class="empty-state"><h3>No exact matches</h3><p>Try widening the price or location filters.</p></div><?php else: ?>
        <div class="property-grid">
            <?php foreach ($properties as $property): ?>
            <article class="property-card">
                <a href="<?= e(url('property.php?id=' . (int) $property['id'])) ?>">
                    <img src="<?= e(url('assets/img/' . $property['cover_image'])) ?>" alt="Exterior illustration of <?= e($property['title']) ?>">
                    <div class="property-card-body"><div class="badges"><span class="badge badge-verified">Verified</span><?php if ($property['nsfas_accredited']): ?><span class="badge">NSFAS</span><?php endif; ?><?php if ($property['instant_book']): ?><span class="badge badge-accent">Instant Book</span><?php endif; ?></div><h3><?= e($property['title']) ?></h3><p><?= e($property['suburb']) ?>, <?= e($property['city']) ?></p><div class="property-meta"><strong><?= money($property['monthly_rent']) ?></strong><span>/ month</span><span><?= number_format((float) $property['rating'], 1) ?> rating</span></div></div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
