<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
$user = require_auth('student');
$propertyId = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
if (!$propertyId) { http_response_code(404); exit('Property not found.'); }
$stmt = db()->prepare("SELECT * FROM properties WHERE id = ? AND status = 'published' AND verified_at IS NOT NULL"); $stmt->execute([$propertyId]); $property = $stmt->fetch();
if (!$property) { http_response_code(404); exit('Property not found.'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $moveIn = (string) ($_POST['move_in_date'] ?? '');
    $message = trim((string) ($_POST['message'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $moveIn);
    if (!$date || $date < new DateTimeImmutable('today')) $errors[] = 'Choose a valid future move-in date.';
    if (mb_strlen($message) > 1000) $errors[] = 'Keep the message under 1,000 characters.';
    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $status = $property['instant_book'] ? 'approved' : 'pending';
            $deadline = $property['instant_book'] ? null : (new DateTimeImmutable('+12 hours'))->format('Y-m-d H:i:s');
            $insert = $pdo->prepare('INSERT INTO bookings (property_id, student_id, move_in_date, message, monthly_rent, deposit_amount, status, response_deadline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $insert->execute([$propertyId, $user['id'], $moveIn, $message, $property['monthly_rent'], $property['deposit_amount'], $status, $deadline]);
            $bookingId = (int) $pdo->lastInsertId();
            $escrow = $pdo->prepare('INSERT INTO escrow_transactions (booking_id, amount, status, created_at) VALUES (?, ?, "not_paid", NOW())'); $escrow->execute([$bookingId, $property['deposit_amount']]);
            $pdo->commit(); audit('booking.created', 'booking', $bookingId, ['instant_book' => (bool) $property['instant_book']]);
            flash('success', $property['instant_book'] ? 'Booking approved. The simulated deposit step is ready.' : 'Booking request sent. The landlord response timer has started.');
            redirect('dashboard.php');
        } catch (Throwable $exception) { $pdo->rollBack(); $errors[] = 'The booking could not be created. Please try again.'; }
    }
}
$pageTitle = 'Book ' . $property['title']; require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="auth-layout booking-layout"><div class="auth-copy"><p class="eyebrow"><?= $property['instant_book'] ? 'Instant Book' : 'Booking request' ?></p><h1><?= e($property['title']) ?></h1><p><?= e($property['suburb']) ?>, <?= e($property['city']) ?></p><div class="price"><strong><?= money($property['monthly_rent']) ?></strong><span>per month</span></div><p>Deposit simulator amount: <strong><?= money($property['deposit_amount']) ?></strong></p></div><div class="auth-card"><h2>Confirm your details</h2><?php foreach ($errors as $error): ?><div class="form-error"><?= e($error) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="property_id" value="<?= (int) $propertyId ?>"><label for="move-in">Move-in date</label><input id="move-in" type="date" name="move_in_date" min="<?= date('Y-m-d') ?>" required><label for="message">Message to the landlord</label><textarea id="message" name="message" rows="5" maxlength="1000" placeholder="Introduce yourself and ask any final questions."></textarea><div class="notice-box"><strong>No real payment is taken.</strong><span>The project demonstrates escrow statuses only.</span></div><button class="button button-block" type="submit"><?= $property['instant_book'] ? 'Confirm Instant Book' : 'Send booking request' ?></button></form></div></section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
