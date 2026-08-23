<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';

$user = require_auth('admin');

function csv_value(mixed $value): string
{
    $text = (string) $value;
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        return "'" . $text;
    }
    return $text;
}

function send_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'wb');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, array_map('csv_value', array_values($row)));
    }
    fclose($output);
    exit;
}

$format = (string) ($_GET['format'] ?? '');
if ($format === 'bookings_csv') {
    $rows = db()->query(
        "SELECT b.id,p.title property,CONCAT(s.first_name,' ',s.last_name) student,
                b.move_in_date,b.monthly_rent,b.deposit_amount,b.status,
                COALESCE(e.status,'not_paid') escrow_status,b.created_at
         FROM bookings b
         JOIN properties p ON p.id=b.property_id
         JOIN users s ON s.id=b.student_id
         LEFT JOIN escrow_transactions e ON e.booking_id=b.id
         ORDER BY b.created_at DESC"
    )->fetchAll();
    send_csv('emergex-bookings-' . date('Ymd-His') . '.csv',
        ['Booking ID','Property','Student','Move-in date','Monthly rent','Deposit','Booking status','Escrow status','Created'], $rows);
}
if ($format === 'properties_csv') {
    $rows = db()->query(
        "SELECT p.id,p.title,p.city,p.suburb,p.near_institution,p.monthly_rent,
                p.deposit_amount,p.available_rooms,p.nsfas_accredited,p.instant_book,
                p.status,CONCAT(u.first_name,' ',u.last_name) landlord,p.verified_at
         FROM properties p JOIN users u ON u.id=p.landlord_id ORDER BY p.title"
    )->fetchAll();
    send_csv('emergex-properties-' . date('Ymd-His') . '.csv',
        ['Property ID','Title','City','Suburb','Near institution','Monthly rent','Deposit','Rooms','NSFAS','Instant Book','Status','Landlord','Verified'], $rows);
}
if ($format === 'audit_csv') {
    $rows = db()->query(
        "SELECT a.id,COALESCE(u.email,'system') actor,a.action,a.entity_type,a.entity_id,
                COALESCE(a.ip_address,'') ip_address,a.created_at
         FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC"
    )->fetchAll();
    send_csv('emergex-audit-' . date('Ymd-His') . '.csv',
        ['Audit ID','Actor','Action','Entity type','Entity ID','IP address','Created'], $rows);
}

$metrics = db()->query(
    "SELECT COUNT(*) total,
            SUM(status='pending') pending,
            SUM(status='approved') approved,
            SUM(status='deposit_held') held,
            SUM(status IN ('checked_in','completed')) completed
     FROM bookings"
)->fetch();
$escrow = db()->query(
    "SELECT COALESCE(SUM(amount),0) total,
            COALESCE(SUM(CASE WHEN status='held' THEN amount ELSE 0 END),0) held,
            COALESCE(SUM(CASE WHEN status='released' THEN amount ELSE 0 END),0) released
     FROM escrow_transactions"
)->fetch();
$bookings = db()->query(
    "SELECT b.id,p.title,CONCAT(s.first_name,' ',s.last_name) student,b.move_in_date,
            b.monthly_rent,b.status,COALESCE(e.status,'not_paid') escrow_status,b.created_at
     FROM bookings b
     JOIN properties p ON p.id=b.property_id
     JOIN users s ON s.id=b.student_id
     LEFT JOIN escrow_transactions e ON e.booking_id=b.id
     ORDER BY b.created_at DESC LIMIT 20"
)->fetchAll();
$properties = db()->query(
    "SELECT p.title,p.city,p.available_rooms,p.nsfas_accredited,p.instant_book,p.status,
            CONCAT(u.first_name,' ',u.last_name) landlord
     FROM properties p JOIN users u ON u.id=p.landlord_id ORDER BY p.title"
)->fetchAll();
$activity = db()->query(
    "SELECT a.action,a.entity_type,a.entity_id,a.created_at,COALESCE(u.email,'system') actor
     FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 15"
)->fetchAll();

$pageTitle = 'Operational reports';
require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="dashboard-head section report-heading">
    <div>
        <p class="eyebrow">Administrator reporting</p>
        <h1>Operational evidence.</h1>
        <p>Generate booking, property and audit reports for management review and assessment evidence.</p>
    </div>
    <div class="report-actions no-print">
        <button class="button" type="button" onclick="window.print()">Print / Save as PDF</button>
        <a class="button button-ghost" href="<?= e(url('admin.php')) ?>">Back to dashboard</a>
    </div>
</section>

<section class="section report-meta">
    <strong>EMERGEX operational report</strong>
    <span>Generated <?= e(date('d F Y, H:i')) ?> by <?= e($user['email']) ?></span>
</section>

<section class="section metric-row four report-metrics">
    <article><span>Total bookings</span><strong><?= (int) $metrics['total'] ?></strong></article>
    <article><span>Pending decisions</span><strong><?= (int) $metrics['pending'] ?></strong></article>
    <article><span>Completed journeys</span><strong><?= (int) $metrics['completed'] ?></strong></article>
    <article><span>Simulated funds held</span><strong><?= money((float) $escrow['held']) ?></strong></article>
</section>

<section class="section report-section">
    <div class="report-section-head">
        <div><p class="eyebrow">Booking pipeline</p><h2>Latest booking activity</h2></div>
        <a class="button button-small no-print" href="?format=bookings_csv">Download CSV</a>
    </div>
    <div class="report-table-wrap"><table class="report-table">
        <thead><tr><th>ID</th><th>Property</th><th>Student</th><th>Move-in</th><th>Rent</th><th>Booking</th><th>Escrow</th></tr></thead>
        <tbody><?php foreach ($bookings as $booking): ?><tr>
            <td><?= (int) $booking['id'] ?></td><td><?= e($booking['title']) ?></td><td><?= e($booking['student']) ?></td>
            <td><?= e(date('d M Y', strtotime($booking['move_in_date']))) ?></td><td><?= money((float) $booking['monthly_rent']) ?></td>
            <td><?= e(ucwords(str_replace('_',' ',$booking['status']))) ?></td><td><?= e(ucwords(str_replace('_',' ',$booking['escrow_status']))) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</section>

<section class="section report-section">
    <div class="report-section-head">
        <div><p class="eyebrow">Property assurance</p><h2>Verified inventory</h2></div>
        <a class="button button-small no-print" href="?format=properties_csv">Download CSV</a>
    </div>
    <div class="report-table-wrap"><table class="report-table">
        <thead><tr><th>Property</th><th>City</th><th>Landlord</th><th>Rooms</th><th>NSFAS</th><th>Instant Book</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($properties as $property): ?><tr>
            <td><?= e($property['title']) ?></td><td><?= e($property['city']) ?></td><td><?= e($property['landlord']) ?></td>
            <td><?= (int) $property['available_rooms'] ?></td><td><?= $property['nsfas_accredited'] ? 'Yes' : 'No' ?></td>
            <td><?= $property['instant_book'] ? 'Yes' : 'No' ?></td><td><?= e(ucwords(str_replace('_',' ',$property['status']))) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</section>

<section class="section report-section">
    <div class="report-section-head">
        <div><p class="eyebrow">Audit evidence</p><h2>Recent critical actions</h2></div>
        <a class="button button-small no-print" href="?format=audit_csv">Download full audit CSV</a>
    </div>
    <div class="report-table-wrap"><table class="report-table">
        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th></tr></thead>
        <tbody><?php foreach ($activity as $event): ?><tr>
            <td><?= e(date('d M Y H:i', strtotime($event['created_at']))) ?></td><td><?= e($event['actor']) ?></td>
            <td><code><?= e($event['action']) ?></code></td><td><?= e(($event['entity_type'] ?? 'system') . ($event['entity_id'] ? ' #' . $event['entity_id'] : '')) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
