<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this acceptance suite from the command line.\n");
}

date_default_timezone_set('Africa/Johannesburg');
$baseUrl = rtrim((string) (getenv('EMERGEX_BASE_URL') ?: 'http://127.0.0.1:8080/emergex/public'), '/');
$resultsFile = (string) (getenv('EMERGEX_RESULTS') ?: (__DIR__ . '/acceptance-results.json'));
$results = [];

final class TestFailure extends RuntimeException {}

final class HttpClient
{
    private array $cookies = [];

    public function __construct(private readonly string $baseUrl) {}

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function request(string $method, string $path, array $data = []): array
    {
        $headers = [];
        $ch = curl_init($this->baseUrl . '/' . ltrim($path, '/'));
        if ($ch === false) {
            throw new RuntimeException('Unable to initialise cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }
                [$name, $value] = array_map('trim', explode(':', $line, 2));
                $headers[strtolower($name)][] = $value;
                if (strtolower($name) === 'set-cookie') {
                    $pair = explode(';', $value, 2)[0];
                    if (str_contains($pair, '=')) {
                        [$cookieName, $cookieValue] = explode('=', $pair, 2);
                        if ($cookieValue === '') {
                            unset($this->cookies[$cookieName]);
                        } else {
                            $this->cookies[$cookieName] = $cookieValue;
                        }
                    }
                }
                return $length;
            },
        ]);
        if ($this->cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(
                static fn (string $name, string $value): string => $name . '=' . $value,
                array_keys($this->cookies),
                array_values($this->cookies)
            )));
        }
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $hasFile = (bool) array_filter($data, static fn ($value): bool => $value instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
            if (!$hasFile) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: text/html,application/xhtml+xml',
                    'Content-Type: application/x-www-form-urlencoded',
                ]);
            }
        }
        $body = curl_exec($ch);
        if ($body === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $message);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
    }
}

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function csrf_from(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match)) {
        throw new TestFailure('CSRF field was not found in the response.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function location_of(array $response): string
{
    return (string) ($response['headers']['location'][0] ?? '');
}

function run_case(string $id, string $area, callable $test): void
{
    global $results;
    $started = microtime(true);
    try {
        $evidence = (string) $test();
        $result = ['id' => $id, 'area' => $area, 'status' => 'PASS', 'evidence' => $evidence];
        echo "[PASS] {$id} {$area}: {$evidence}\n";
    } catch (Throwable $exception) {
        $result = ['id' => $id, 'area' => $area, 'status' => 'FAIL', 'evidence' => $exception->getMessage()];
        echo "[FAIL] {$id} {$area}: {$exception->getMessage()}\n";
    }
    $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
    $results[] = $result;
}

function login(HttpClient $client, string $email, string $password): array
{
    $page = $client->request('GET', 'login.php');
    expect_true($page['status'] === 200, 'Login page did not return HTTP 200.');
    return $client->request('POST', 'login.php', [
        'csrf_token' => csrf_from($page['body']),
        'email' => $email,
        'password' => $password,
    ]);
}

ob_start();
require dirname(__DIR__) . '/database/install.php';
$installerOutput = trim((string) ob_get_clean());
$config = require dirname(__DIR__) . '/app/config/config.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']),
    $db['user'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

$studentClient = new HttpClient($baseUrl);
$studentEmail = 'acceptance.student@example.test';
$studentPassword = 'Acceptance55';
$studentId = 0;
$instantPropertyId = (int) $pdo->query("SELECT id FROM properties WHERE instant_book=1 ORDER BY id LIMIT 1")->fetchColumn();
$standardPropertyId = (int) $pdo->query("SELECT id FROM properties WHERE instant_book=0 ORDER BY id LIMIT 1")->fetchColumn();
$instantBookingId = 0;
$standardBookingId = 0;
$pendingStateBookingId = 0;
$validDocumentId = 0;

run_case('ST-01', 'Install', function () use ($pdo, $installerOutput): string {
    $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='emergex'")->fetchColumn();
    $accounts = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    expect_true(str_contains($installerOutput, 'installed successfully'), 'Installer did not report success.');
    expect_true($tables === 7 && $accounts === 3, "Expected 7 tables and 3 accounts; found {$tables} and {$accounts}.");
    return 'Clean installer created 7 tables and 3 seeded accounts.';
});

run_case('ST-02', 'Register', function () use ($studentClient, $pdo, $studentEmail, $studentPassword, &$studentId): string {
    $page = $studentClient->request('GET', 'register.php');
    $response = $studentClient->request('POST', 'register.php', [
        'csrf_token' => csrf_from($page['body']), 'role' => 'student', 'first_name' => 'Acceptance',
        'last_name' => 'Student', 'email' => $studentEmail, 'phone' => '0825550155',
        'student_number' => 'ACC-2026-55', 'password' => $studentPassword, 'terms' => '1',
    ]);
    expect_true($response['status'] === 302 && str_contains(location_of($response), 'dashboard.php'), 'Valid registration did not redirect to the student dashboard.');
    $stmt = $pdo->prepare('SELECT id,password_hash FROM users WHERE email=?');
    $stmt->execute([$studentEmail]);
    $row = $stmt->fetch();
    expect_true((bool) $row && password_verify($studentPassword, $row['password_hash']), 'Created account or password hash was not found.');
    $studentId = (int) $row['id'];
    return "Student {$studentId} was created with a verifiable password hash.";
});

run_case('ST-03', 'Register', function () use ($baseUrl, $pdo, $studentEmail, $studentPassword): string {
    $client = new HttpClient($baseUrl);
    $page = $client->request('GET', 'register.php');
    $response = $client->request('POST', 'register.php', [
        'csrf_token' => csrf_from($page['body']), 'role' => 'student', 'first_name' => 'Duplicate',
        'last_name' => 'Student', 'email' => $studentEmail, 'phone' => '0825550156',
        'student_number' => 'ACC-2026-56', 'password' => $studentPassword, 'terms' => '1',
    ]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
    $stmt->execute([$studentEmail]);
    expect_true($response['status'] === 200 && str_contains($response['body'], 'already uses that email'), 'Duplicate registration did not produce the safe duplicate message.');
    expect_true((int) $stmt->fetchColumn() === 1, 'Duplicate registration created another account.');
    return 'Duplicate email was rejected and the unique account count remained 1.';
});

run_case('ST-04', 'Login', function () use ($baseUrl): string {
    $client = new HttpClient($baseUrl);
    $page = $client->request('GET', 'login.php');
    $token = csrf_from($page['body']);
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $client->request('POST', 'login.php', ['csrf_token' => $token, 'email' => 'student@emergex.test', 'password' => 'WrongPass55']);
        expect_true($response['status'] === 200 && str_contains($response['body'], 'email or password is incorrect'), "Attempt {$attempt} did not return the generic error.");
    }
    $blocked = $client->request('POST', 'login.php', ['csrf_token' => $token, 'email' => 'student@emergex.test', 'password' => 'WrongPass55']);
    expect_true($blocked['status'] === 429, 'The sixth request was not throttled with HTTP 429.');
    return 'Five generic failures were followed by HTTP 429.';
});

run_case('ST-05', 'Search', function () use ($baseUrl): string {
    $response = (new HttpClient($baseUrl))->request('GET', 'properties.php');
    $cards = substr_count($response['body'], 'class="property-card"');
    expect_true($response['status'] === 200 && $cards === 4, "Expected 4 property cards; found {$cards}.");
    return 'All four verified seeded properties were rendered.';
});

run_case('ST-06', 'Search', function () use ($baseUrl): string {
    $response = (new HttpClient($baseUrl))->request('GET', 'properties.php?max_price=5000&nsfas=1');
    expect_true($response['status'] === 200 && str_contains($response['body'], '1 home'), 'Filtered result count was not 1.');
    expect_true(str_contains($response['body'], 'Mowbray Campus Rooms') && !str_contains($response['body'], 'Emerald House Hatfield'), 'Filtered result set did not match NSFAS and price criteria.');
    return 'NSFAS plus R5,000 filter returned only Mowbray Campus Rooms.';
});

run_case('ST-07', 'Search', function () use ($baseUrl): string {
    $payload = rawurlencode("' OR 1=1 --");
    $response = (new HttpClient($baseUrl))->request('GET', 'properties.php?q=' . $payload);
    expect_true($response['status'] === 200 && !preg_match('/SQLSTATE|syntax error|PDOException/i', $response['body']), 'Quote payload exposed a database error.');
    expect_true(str_contains($response['body'], '0 homes'), 'Quote payload unexpectedly returned all records.');
    return 'SQL-style quote payload returned a safe empty result without an error.';
});

run_case('ST-08', 'Property', function () use ($baseUrl): string {
    $response = (new HttpClient($baseUrl))->request('GET', 'property.php?id=99999999');
    expect_true($response['status'] === 404 && trim($response['body']) === 'Property not found.', 'Missing property did not return the safe HTTP 404 response.');
    return 'Unknown property returned HTTP 404 with no detail leak.';
});

run_case('ST-09', 'AuthZ', function () use ($baseUrl): string {
    $response = (new HttpClient($baseUrl))->request('GET', 'dashboard.php');
    expect_true($response['status'] === 302 && str_contains(location_of($response), 'login.php'), 'Visitor was not redirected to login.');
    return 'Unauthenticated dashboard request redirected to login.';
});

run_case('ST-10', 'AuthZ', function () use ($studentClient): string {
    $response = $studentClient->request('GET', 'admin.php');
    expect_true($response['status'] === 403, 'Student received access to the administrator dashboard.');
    return 'Student role received HTTP 403 for the administrator dashboard.';
});

run_case('ST-11', 'CSRF', function () use ($studentClient, $pdo, $studentId, $instantPropertyId): string {
    $before = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE student_id={$studentId}")->fetchColumn();
    $response = $studentClient->request('POST', 'book.php?property_id=' . $instantPropertyId, [
        'property_id' => (string) $instantPropertyId, 'move_in_date' => date('Y-m-d', strtotime('+40 days')), 'message' => 'No token',
    ]);
    $after = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE student_id={$studentId}")->fetchColumn();
    expect_true($response['status'] === 419 && $before === $after, 'Missing CSRF token changed booking data or returned the wrong status.');
    return 'Missing token returned HTTP 419 and booking count was unchanged.';
});

run_case('ST-12', 'Booking', function () use ($studentClient, $pdo, $studentId, $instantPropertyId): string {
    $page = $studentClient->request('GET', 'book.php?property_id=' . $instantPropertyId);
    $before = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE student_id={$studentId}")->fetchColumn();
    $response = $studentClient->request('POST', 'book.php?property_id=' . $instantPropertyId, [
        'csrf_token' => csrf_from($page['body']), 'property_id' => (string) $instantPropertyId,
        'move_in_date' => date('Y-m-d', strtotime('-1 day')), 'message' => 'Past date check',
    ]);
    $after = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE student_id={$studentId}")->fetchColumn();
    expect_true($response['status'] === 200 && str_contains($response['body'], 'valid future move-in date') && $before === $after, 'Past date was not rejected without a write.');
    return 'Past move-in date produced validation feedback and no booking.';
});

run_case('ST-13', 'Booking', function () use ($studentClient, $pdo, $studentId, $instantPropertyId, &$instantBookingId): string {
    $page = $studentClient->request('GET', 'book.php?property_id=' . $instantPropertyId);
    $response = $studentClient->request('POST', 'book.php?property_id=' . $instantPropertyId, [
        'csrf_token' => csrf_from($page['body']), 'property_id' => (string) $instantPropertyId,
        'move_in_date' => date('Y-m-d', strtotime('+45 days')), 'message' => 'Instant acceptance test',
    ]);
    $stmt = $pdo->prepare('SELECT b.id,b.status,e.status escrow_status FROM bookings b JOIN escrow_transactions e ON e.booking_id=b.id WHERE b.student_id=? AND b.property_id=? ORDER BY b.id DESC LIMIT 1');
    $stmt->execute([$studentId, $instantPropertyId]);
    $row = $stmt->fetch();
    expect_true($response['status'] === 302 && $row && $row['status'] === 'approved' && $row['escrow_status'] === 'not_paid', 'Instant booking state or escrow state was incorrect.');
    $instantBookingId = (int) $row['id'];
    return "Instant booking {$instantBookingId} was approved with not_paid escrow.";
});

run_case('ST-14', 'Booking', function () use ($studentClient, $pdo, $studentId, $standardPropertyId, &$standardBookingId): string {
    $page = $studentClient->request('GET', 'book.php?property_id=' . $standardPropertyId);
    $response = $studentClient->request('POST', 'book.php?property_id=' . $standardPropertyId, [
        'csrf_token' => csrf_from($page['body']), 'property_id' => (string) $standardPropertyId,
        'move_in_date' => date('Y-m-d', strtotime('+50 days')), 'message' => 'Standard acceptance test',
    ]);
    $stmt = $pdo->prepare('SELECT id,status,TIMESTAMPDIFF(MINUTE,created_at,response_deadline) deadline_minutes FROM bookings WHERE student_id=? AND property_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$studentId, $standardPropertyId]);
    $row = $stmt->fetch();
    expect_true($response['status'] === 302 && $row && $row['status'] === 'pending' && (int) $row['deadline_minutes'] === 720, 'Standard booking or 12-hour deadline was incorrect.');
    $standardBookingId = (int) $row['id'];
    return "Standard booking {$standardBookingId} is pending with a 720-minute deadline.";
});

run_case('ST-15', 'Landlord', function () use ($baseUrl, $pdo, &$standardBookingId): string {
    $client = new HttpClient($baseUrl);
    $login = login($client, 'landlord@emergex.test', 'Landlord@55');
    expect_true($login['status'] === 302, 'Seeded landlord could not sign in.');
    $page = $client->request('GET', 'landlord.php');
    $response = $client->request('POST', 'landlord.php', ['csrf_token' => csrf_from($page['body']), 'booking_id' => (string) $standardBookingId, 'action' => 'approve']);
    $stmt = $pdo->prepare("SELECT b.status,b.responded_at,(SELECT COUNT(*) FROM audit_logs WHERE action='booking.approved' AND entity_id=b.id) audits FROM bookings b WHERE b.id=?");
    $stmt->execute([$standardBookingId]);
    $row = $stmt->fetch();
    expect_true($response['status'] === 302 && $row['status'] === 'approved' && $row['responded_at'] && (int) $row['audits'] >= 1, 'Owned booking approval did not update state and audit evidence.');
    return 'Owned pending request was approved with response time and audit event.';
});

run_case('ST-16', 'Landlord', function () use ($baseUrl, $pdo, $instantBookingId): string {
    $hash = password_hash('OtherLandlord55', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users(role,first_name,last_name,email,phone,password_hash,status,verified_at) VALUES('landlord','Other','Landlord','other.landlord@example.test','0825550166',?,'active',NOW())");
    $stmt->execute([$hash]);
    $otherLandlordId = (int) $pdo->lastInsertId();
    $seededStudentId = (int) $pdo->query("SELECT id FROM users WHERE email='student@emergex.test'")->fetchColumn();
    $property = $pdo->prepare("INSERT INTO properties(landlord_id,title,description,address_line,suburb,city,province,near_institution,monthly_rent,deposit_amount,available_rooms,instant_book,nsfas_accredited,rating,cover_image,amenities_json,status,verified_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $property->execute([$otherLandlordId,'Other Landlord Fixture','Ownership test fixture','16 Fixture Road','Hatfield','Pretoria','Gauteng','Test University',4000,4000,1,0,0,4.0,'property-1.svg','["Wi-Fi"]','published']);
    $ownedPropertyId = (int) $pdo->lastInsertId();
    $booking = $pdo->prepare("INSERT INTO bookings(property_id,student_id,move_in_date,message,monthly_rent,deposit_amount,status,response_deadline) VALUES(?,?,DATE_ADD(CURDATE(),INTERVAL 80 DAY),'CSRF fixture',4000,4000,'pending',DATE_ADD(NOW(),INTERVAL 12 HOUR))");
    $booking->execute([$ownedPropertyId, $seededStudentId]);
    $ownedBookingId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO escrow_transactions(booking_id,amount,status) VALUES({$ownedBookingId},4000,'not_paid')");
    $client = new HttpClient($baseUrl);
    login($client, 'other.landlord@example.test', 'OtherLandlord55');
    $page = $client->request('GET', 'landlord.php');
    $before = $pdo->query("SELECT status FROM bookings WHERE id={$instantBookingId}")->fetchColumn();
    $response = $client->request('POST', 'landlord.php', ['csrf_token' => csrf_from($page['body']), 'booking_id' => (string) $instantBookingId, 'action' => 'reject']);
    $after = $pdo->query("SELECT status FROM bookings WHERE id={$instantBookingId}")->fetchColumn();
    expect_true($response['status'] === 400 && $before === $after, 'Another landlord changed an unowned booking.');
    return 'Cross-landlord booking action returned HTTP 400 and state was unchanged.';
});

run_case('ST-17', 'State', function () use ($studentClient, $pdo, $studentId, $standardPropertyId, &$pendingStateBookingId): string {
    $bookPage = $studentClient->request('GET', 'book.php?property_id=' . $standardPropertyId);
    $studentClient->request('POST', 'book.php?property_id=' . $standardPropertyId, [
        'csrf_token' => csrf_from($bookPage['body']), 'property_id' => (string) $standardPropertyId,
        'move_in_date' => date('Y-m-d', strtotime('+60 days')), 'message' => 'Pending state check',
    ]);
    $pendingStateBookingId = (int) $pdo->query("SELECT id FROM bookings WHERE student_id={$studentId} AND property_id={$standardPropertyId} ORDER BY id DESC LIMIT 1")->fetchColumn();
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', ['csrf_token' => csrf_from($dashboard['body']), 'booking_id' => (string) $pendingStateBookingId, 'action' => 'simulate_deposit']);
    $status = $pdo->query("SELECT status FROM bookings WHERE id={$pendingStateBookingId}")->fetchColumn();
    expect_true($response['status'] === 302 && $status === 'pending', 'Pending booking accepted an invalid escrow hold.');
    return 'Deposit hold attempt on a pending booking left it pending.';
});

run_case('ST-18', 'State', function () use ($studentClient, $pdo, $instantBookingId): string {
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', ['csrf_token' => csrf_from($dashboard['body']), 'booking_id' => (string) $instantBookingId, 'action' => 'simulate_deposit']);
    $row = $pdo->query("SELECT b.status,e.status escrow_status,e.reference_code FROM bookings b JOIN escrow_transactions e ON e.booking_id=b.id WHERE b.id={$instantBookingId}")->fetch();
    expect_true($response['status'] === 302 && $row['status'] === 'deposit_held' && $row['escrow_status'] === 'held' && str_starts_with((string) $row['reference_code'], 'SIM-'), 'Approved booking did not transition to held states with a SIM reference.');
    return 'Approved booking moved to deposit_held with held escrow and SIM reference.';
});

run_case('ST-19', 'State', function () use ($studentClient, $pdo, $instantBookingId): string {
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', ['csrf_token' => csrf_from($dashboard['body']), 'booking_id' => (string) $instantBookingId, 'action' => 'confirm_checkin']);
    $row = $pdo->query("SELECT b.status,b.checked_in_at,e.status escrow_status,e.released_at FROM bookings b JOIN escrow_transactions e ON e.booking_id=b.id WHERE b.id={$instantBookingId}")->fetch();
    expect_true($response['status'] === 302 && $row['status'] === 'completed' && $row['checked_in_at'] && $row['escrow_status'] === 'released' && $row['released_at'], 'Held booking did not complete and release escrow.');
    return 'Check-in completed the booking and released simulated escrow.';
});

run_case('ST-20', 'Atomicity', function () use ($pdo, $standardBookingId): string {
    $before = $pdo->query("SELECT b.status,e.status escrow_status FROM bookings b JOIN escrow_transactions e ON e.booking_id=b.id WHERE b.id={$standardBookingId}")->fetch();
    try {
        $pdo->beginTransaction();
        $pdo->exec("UPDATE escrow_transactions SET status='held' WHERE booking_id={$standardBookingId}");
        $pdo->exec("UPDATE bookings SET missing_test_column=1 WHERE id={$standardBookingId}");
        $pdo->commit();
        throw new TestFailure('The forced second update unexpectedly succeeded.');
    } catch (PDOException) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $after = $pdo->query("SELECT b.status,e.status escrow_status FROM bookings b JOIN escrow_transactions e ON e.booking_id=b.id WHERE b.id={$standardBookingId}")->fetch();
    expect_true($before === $after, 'Rollback did not restore both booking and escrow state.');
    return 'Forced second-update failure rolled back both transactional changes.';
});

run_case('ST-21', 'Upload', function () use ($studentClient, $pdo, $studentId, &$validDocumentId): string {
    $path = tempnam(sys_get_temp_dir(), 'emergex_pdf_');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
    $before = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn();
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', [
        'csrf_token' => csrf_from($dashboard['body']), 'action' => 'upload_nsfas',
        'nsfas_document' => new CURLFile($path, 'application/pdf', 'valid-small.pdf'),
    ]);
    @unlink($path);
    $row = $pdo->query("SELECT id,original_name,stored_name,mime_type,status FROM user_documents WHERE user_id={$studentId} ORDER BY id DESC LIMIT 1")->fetch();
    expect_true($response['status'] === 302 && $row && (int) $row['id'] > 0 && $row['status'] === 'pending' && $row['mime_type'] === 'application/pdf' && $row['stored_name'] !== $row['original_name'], 'Valid PDF was not stored with random pending metadata.');
    expect_true((int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn() === $before + 1, 'Valid upload did not add exactly one metadata row.');
    $validDocumentId = (int) $row['id'];
    return "Valid PDF created pending document {$validDocumentId} with a random stored name.";
});

run_case('ST-22', 'Upload', function () use ($studentClient, $pdo, $studentId): string {
    $path = tempnam(sys_get_temp_dir(), 'emergex_big_');
    $handle = fopen($path, 'wb');
    fseek($handle, (5 * 1024 * 1024) + 1024 - 1);
    fwrite($handle, "A");
    fclose($handle);
    $before = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn();
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', [
        'csrf_token' => csrf_from($dashboard['body']), 'action' => 'upload_nsfas',
        'nsfas_document' => new CURLFile($path, 'application/pdf', 'too-large.pdf'),
    ]);
    @unlink($path);
    $after = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn();
    expect_true($response['status'] === 302 && $before === $after, 'Oversized file created document metadata.');
    return 'File larger than 5 MB was rejected with no metadata row.';
});

run_case('ST-23', 'Upload', function () use ($studentClient, $pdo, $studentId): string {
    $path = tempnam(sys_get_temp_dir(), 'emergex_php_');
    file_put_contents($path, "<?php echo 'not an image'; ?>");
    $before = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn();
    $dashboard = $studentClient->request('GET', 'dashboard.php');
    $response = $studentClient->request('POST', 'dashboard.php', [
        'csrf_token' => csrf_from($dashboard['body']), 'action' => 'upload_nsfas',
        'nsfas_document' => new CURLFile($path, 'image/jpeg', 'renamed-payload.jpg'),
    ]);
    @unlink($path);
    $after = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE user_id={$studentId}")->fetchColumn();
    expect_true($response['status'] === 302 && $before === $after, 'PHP content renamed to JPG created metadata.');
    return 'Fileinfo rejected PHP content renamed with a JPG extension.';
});

run_case('ST-24', 'XSS', function () use ($studentClient, $baseUrl, $pdo, $studentId, $standardPropertyId): string {
    $payload = '<script>alert(1)</script>';
    $page = $studentClient->request('GET', 'book.php?property_id=' . $standardPropertyId);
    $studentClient->request('POST', 'book.php?property_id=' . $standardPropertyId, [
        'csrf_token' => csrf_from($page['body']), 'property_id' => (string) $standardPropertyId,
        'move_in_date' => date('Y-m-d', strtotime('+70 days')), 'message' => $payload,
    ]);
    $client = new HttpClient($baseUrl);
    login($client, 'landlord@emergex.test', 'Landlord@55');
    $landlordPage = $client->request('GET', 'landlord.php');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE student_id=? AND message=?');
    $stmt->execute([$studentId, $payload]);
    expect_true((int) $stmt->fetchColumn() === 1, 'XSS fixture message was not stored for output testing.');
    expect_true(!str_contains($landlordPage['body'], $payload) && str_contains($landlordPage['body'], '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Booking message was not HTML-escaped on output.');
    return 'Stored script payload rendered as escaped text on the landlord dashboard.';
});

run_case('ST-25', 'Session', function () use ($baseUrl): string {
    $client = new HttpClient($baseUrl);
    $page = $client->request('GET', 'login.php');
    $before = $client->cookie('emergex_session');
    $response = $client->request('POST', 'login.php', ['csrf_token' => csrf_from($page['body']), 'email' => 'student@emergex.test', 'password' => 'Student@55']);
    $after = $client->cookie('emergex_session');
    expect_true($response['status'] === 302 && $before && $after && !hash_equals($before, $after), 'Session identifier did not change after successful login.');
    return 'Session identifier changed after authentication.';
});

run_case('ST-26', 'Session', function () use ($baseUrl): string {
    $client = new HttpClient($baseUrl);
    login($client, 'student@emergex.test', 'Student@55');
    $logout = $client->request('GET', 'logout.php');
    $dashboard = $client->request('GET', 'dashboard.php');
    expect_true($logout['status'] === 302 && $dashboard['status'] === 302 && str_contains(location_of($dashboard), 'login.php'), 'Dashboard remained available after logout.');
    return 'Logout invalidated the session and dashboard access required login.';
});

run_case('ST-27', 'Admin', function () use ($baseUrl, $pdo): string {
    $hash = password_hash('PendingLandlord55', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users(role,first_name,last_name,email,phone,password_hash,status) VALUES('landlord','Pending','Landlord','pending.landlord@example.test','0825550177',?,'active')");
    $stmt->execute([$hash]);
    $id = (int) $pdo->lastInsertId();
    $client = new HttpClient($baseUrl);
    login($client, 'admin@emergex.test', 'Admin@55Secure');
    $page = $client->request('GET', 'admin.php');
    $response = $client->request('POST', 'admin.php', ['csrf_token' => csrf_from($page['body']), 'entity_id' => (string) $id, 'action' => 'verify_user']);
    $row = $pdo->query("SELECT verified_at,(SELECT COUNT(*) FROM audit_logs WHERE action='admin.user_verified' AND entity_id={$id}) audits FROM users WHERE id={$id}")->fetch();
    expect_true($response['status'] === 302 && $row['verified_at'] && (int) $row['audits'] >= 1, 'Administrator verification did not set time and audit evidence.');
    return "Pending landlord {$id} was verified with an audit event.";
});

run_case('ST-28', 'Admin', function () use ($baseUrl, $pdo): string {
    $landlordId = (int) $pdo->query("SELECT id FROM users WHERE email='pending.landlord@example.test'")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO properties(landlord_id,title,description,address_line,suburb,city,province,near_institution,monthly_rent,deposit_amount,available_rooms,instant_book,nsfas_accredited,rating,cover_image,amenities_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$landlordId,'Acceptance Pending Residence','Acceptance verification fixture','55 Test Street','Hatfield','Pretoria','Gauteng','Test University',4300,4300,2,0,1,4.0,'property-1.svg','["Wi-Fi"]','pending_review']);
    $propertyId = (int) $pdo->lastInsertId();
    $client = new HttpClient($baseUrl);
    login($client, 'admin@emergex.test', 'Admin@55Secure');
    $page = $client->request('GET', 'admin.php');
    $response = $client->request('POST', 'admin.php', ['csrf_token' => csrf_from($page['body']), 'entity_id' => (string) $propertyId, 'action' => 'verify_property']);
    $row = $pdo->query("SELECT status,verified_at,(SELECT COUNT(*) FROM audit_logs WHERE action='admin.property_verified' AND entity_id={$propertyId}) audits FROM properties WHERE id={$propertyId}")->fetch();
    $search = (new HttpClient($baseUrl))->request('GET', 'properties.php?q=' . rawurlencode('Acceptance Pending Residence'));
    expect_true($response['status'] === 302 && $row['status'] === 'published' && $row['verified_at'] && (int) $row['audits'] >= 1 && str_contains($search['body'], 'Acceptance Pending Residence'), 'Verified property was not published, audited and searchable.');
    return "Pending property {$propertyId} was published, audited and became searchable.";
});

run_case('ST-29', 'Admin', function () use ($baseUrl, $pdo, $validDocumentId): string {
    expect_true($validDocumentId > 0, 'No valid document fixture was available.');
    $client = new HttpClient($baseUrl);
    login($client, 'admin@emergex.test', 'Admin@55Secure');
    $page = $client->request('GET', 'admin.php');
    $response = $client->request('POST', 'admin.php', ['csrf_token' => csrf_from($page['body']), 'entity_id' => (string) $validDocumentId, 'action' => 'approve_document']);
    $row = $pdo->query("SELECT status,reviewed_by,reviewed_at,(SELECT COUNT(*) FROM audit_logs WHERE action='admin.document_approved' AND entity_id={$validDocumentId}) audits FROM user_documents WHERE id={$validDocumentId}")->fetch();
    expect_true($response['status'] === 302 && $row['status'] === 'approved' && $row['reviewed_by'] && $row['reviewed_at'] && (int) $row['audits'] >= 1, 'Document approval did not store reviewer, time and audit evidence.');
    return "Document {$validDocumentId} was approved with reviewer, time and audit event.";
});

$passed = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'PASS'));
$failed = count($results) - $passed;
$report = [
    'suite' => 'EMERGEX runtime acceptance tests ST-01 to ST-29',
    'executed_at' => date(DATE_ATOM),
    'base_url' => $baseUrl,
    'php_version' => PHP_VERSION,
    'database_version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
    'summary' => ['passed' => $passed, 'failed' => $failed, 'total' => count($results)],
    'results' => $results,
];
file_put_contents($resultsFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo "\nRuntime suite: {$passed}/" . count($results) . " passed; {$failed} failed.\n";
echo "Results: {$resultsFile}\n";
exit($failed === 0 ? 0 : 1);
