<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Property not found.'); }
$stmt = db()->prepare("SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS landlord_name, u.verified_at AS landlord_verified FROM properties p JOIN users u ON u.id = p.landlord_id WHERE p.id = ? AND p.status = 'published'");
$stmt->execute([$id]);
$property = $stmt->fetch();
if (!$property) { http_response_code(404); exit('Property not found.'); }
$amenities = json_decode($property['amenities_json'], true) ?: [];
$pageTitle = $property['title'];
require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="property-detail section">
    <div class="detail-gallery"><img src="<?= e(url('assets/img/' . $property['cover_image'])) ?>" alt="Exterior illustration of <?= e($property['title']) ?>"><div class="visual-proof"><span>Video walkthrough</span><span>Floor plan</span><span>360 tour ready</span></div></div>
    <div class="detail-summary">
        <div class="badges"><span class="badge badge-verified">Verified property</span><?php if ($property['nsfas_accredited']): ?><span class="badge">NSFAS accredited</span><?php endif; ?></div>
        <h1><?= e($property['title']) ?></h1><p class="location"><?= e($property['address_line']) ?>, <?= e($property['suburb']) ?>, <?= e($property['city']) ?></p>
        <div class="price"><strong><?= money($property['monthly_rent']) ?></strong><span>per month</span></div>
        <p><?= e($property['description']) ?></p>
        <dl class="facts"><div><dt>Deposit</dt><dd><?= money($property['deposit_amount']) ?></dd></div><div><dt>Rooms available</dt><dd><?= (int) $property['available_rooms'] ?></dd></div><div><dt>Near</dt><dd><?= e($property['near_institution']) ?></dd></div><div><dt>Rating</dt><dd><?= number_format((float) $property['rating'], 1) ?>/5</dd></div></dl>
        <a class="button button-block" href="<?= e(url('book.php?property_id=' . (int) $property['id'])) ?>"><?= $property['instant_book'] ? 'Instant Book' : 'Request booking' ?></a>
        <small class="safe-note">Academic prototype: the deposit step is simulated and no real money is collected.</small>
    </div>
</section>
<section class="section detail-columns"><div><p class="eyebrow">What is included</p><h2>A home you can inspect before committing.</h2><ul class="amenity-list"><?php foreach ($amenities as $amenity): ?><li><?= e((string) $amenity) ?></li><?php endforeach; ?></ul></div><aside class="landlord-card"><span class="avatar"><?= e(substr($property['landlord_name'], 0, 1)) ?></span><div><small>Managed by</small><strong><?= e($property['landlord_name']) ?></strong><span><?= $property['landlord_verified'] ? 'Identity verified' : 'Verification pending' ?></span></div></aside></section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
