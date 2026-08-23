<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
$user = require_auth('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'upload_nsfas') {
        $file = $_FILES['nsfas_document'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) { flash('error', 'Select a valid document to upload.'); }
        elseif ($file['size'] > (int) config('uploads.max_bytes')) { flash('error', 'The file exceeds the 5 MB limit.'); }
        else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!in_array($mime, config('uploads.allowed_mime'), true)) { flash('error', 'Only PDF, JPG and PNG files are accepted.'); }
            else {
                $extension = match ($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' };
                $stored = bin2hex(random_bytes(20)) . '.' . $extension;
                $target = rtrim(config('uploads.directory'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stored;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    db()->prepare('INSERT INTO user_documents (user_id, document_type, original_name, stored_name, mime_type, file_size, status, uploaded_at) VALUES (?, "nsfas_proof", ?, ?, ?, ?, "pending", NOW())')->execute([$user['id'], basename($file['name']), $stored, $mime, $file['size']]);
                    audit('document.uploaded', 'user', (int) $user['id'], ['type' => 'nsfas_proof']); flash('success', 'Document uploaded for administrator review.');
                } else flash('error', 'The file could not be saved.');
            }
        }
    } elseif (in_array($action, ['simulate_deposit', 'confirm_checkin'], true)) {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $stmt = db()->prepare('SELECT b.*, e.id AS escrow_id, e.status AS escrow_status FROM bookings b JOIN escrow_transactions e ON e.booking_id = b.id WHERE b.id = ? AND b.student_id = ?');
        $stmt->execute([$bookingId, $user['id']]); $booking = $stmt->fetch();
        if (!$booking) { http_response_code(404); exit('Booking not found.'); }
        if ($action === 'simulate_deposit' && $booking['status'] === 'approved' && $booking['escrow_status'] === 'not_paid') {
            $pdo = db(); $pdo->beginTransaction();
            $pdo->prepare("UPDATE escrow_transactions SET status = 'held', paid_at = NOW(), reference_code = ? WHERE id = ?")->execute(['SIM-' . strtoupper(bin2hex(random_bytes(4))), $booking['escrow_id']]);
            $pdo->prepare("UPDATE bookings SET status = 'deposit_held', updated_at = NOW() WHERE id = ?")->execute([$bookingId]);
            $pdo->commit(); audit('escrow.simulated_hold', 'booking', $bookingId); flash('success', 'Deposit hold simulated successfully. No real funds were processed.');
        } elseif ($action === 'confirm_checkin' && $booking['status'] === 'deposit_held' && $booking['escrow_status'] === 'held') {
            $pdo = db(); $pdo->beginTransaction();
            $pdo->prepare("UPDATE escrow_transactions SET status = 'released', released_at = NOW() WHERE id = ?")->execute([$booking['escrow_id']]);
            $pdo->prepare("UPDATE bookings SET status = 'completed', checked_in_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$bookingId]);
            $pdo->commit(); audit('escrow.simulated_release', 'booking', $bookingId); flash('success', 'Check-in confirmed and deposit release recorded.');
        }
    } else {
        http_response_code(400);
        exit('Invalid dashboard action.');
    }
    redirect('dashboard.php');
}

$stmt = db()->prepare('SELECT b.*, p.title, p.suburb, p.city, p.cover_image, e.status AS escrow_status, e.reference_code FROM bookings b JOIN properties p ON p.id = b.property_id JOIN escrow_transactions e ON e.booking_id = b.id WHERE b.student_id = ? ORDER BY b.created_at DESC');
$stmt->execute([$user['id']]); $bookings = $stmt->fetchAll();
$docStmt = db()->prepare('SELECT document_type, original_name, status, uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC'); $docStmt->execute([$user['id']]); $documents = $docStmt->fetchAll();
$pageTitle = 'Student dashboard'; require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="dashboard-head section"><div><p class="eyebrow">Student dashboard</p><h1>Hi, <?= e($user['first_name']) ?>.</h1><p>Follow every booking from request to check-in.</p></div><a class="button" href="<?= e(url('properties.php')) ?>">Find another home</a></section>
<section class="section dashboard-grid"><div class="dashboard-main"><h2>Your bookings</h2><?php if (!$bookings): ?><div class="empty-state"><h3>No bookings yet</h3><p>Start with a verified property search.</p></div><?php endif; ?><?php foreach ($bookings as $booking): ?><article class="booking-card"><img src="<?= e(url('assets/img/' . $booking['cover_image'])) ?>" alt=""><div class="booking-content"><div class="booking-top"><div><h3><?= e($booking['title']) ?></h3><p><?= e($booking['suburb']) ?>, <?= e($booking['city']) ?></p></div><span class="status status-<?= e($booking['status']) ?>"><?= e(booking_status_label($booking['status'])) ?></span></div><div class="booking-facts"><span>Move-in <strong><?= e(date('d M Y', strtotime($booking['move_in_date']))) ?></strong></span><span>Rent <strong><?= money($booking['monthly_rent']) ?></strong></span><span>Deposit <strong><?= money($booking['deposit_amount']) ?></strong></span></div><?php if ($booking['response_deadline'] && $booking['status'] === 'pending'): ?><p class="deadline">Response requested by <?= e(date('d M Y, H:i', strtotime($booking['response_deadline']))) ?></p><?php endif; ?><?php if ($booking['status'] === 'approved'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="action" value="simulate_deposit"><button class="button" type="submit">Simulate deposit hold</button></form><?php elseif ($booking['status'] === 'deposit_held'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="action" value="confirm_checkin"><button class="button" type="submit">Confirm check-in & release</button></form><?php elseif ($booking['status'] === 'completed'): ?><p class="success-line">Booking completed · reference <?= e($booking['reference_code']) ?></p><?php endif; ?></div></article><?php endforeach; ?></div><aside class="dashboard-side"><div class="panel"><h2>NSFAS evidence</h2><p>Upload proof for administrator review. Use demo files only.</p><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload_nsfas"><label for="nsfas-document">PDF, JPG or PNG up to 5 MB</label><input id="nsfas-document" type="file" name="nsfas_document" accept=".pdf,.jpg,.jpeg,.png" required><button class="button button-block" type="submit">Upload securely</button></form><?php foreach ($documents as $document): ?><p class="document-row"><span><?= e($document['original_name']) ?></span><strong><?= e(ucfirst($document['status'])) ?></strong></p><?php endforeach; ?></div><div class="panel dark"><h2>Safety reminder</h2><p>Never pay a landlord outside the demonstrated process. Inspect the property and lease details first.</p></div></aside></section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
