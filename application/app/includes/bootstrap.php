<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('emergex_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

function config(?string $key = null, mixed $default = null): mixed
{
    global $config;
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('The form expired. Please return to the previous page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $cached = null;
    }
    $stmt = db()->prepare('SELECT id, role, first_name, last_name, email, phone, verified_at FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([(int) $id]);
    return $cached = ($stmt->fetch() ?: null);
}

function require_auth(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $user;
}

function is_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function audit(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId, json_encode($details, JSON_UNESCAPED_SLASHES), $_SERVER['REMOTE_ADDR'] ?? null]);
}

function money(float|int|string $amount): string
{
    return 'R ' . number_format((float) $amount, 0, '.', ' ');
}

function booking_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Awaiting landlord',
        'approved' => 'Approved - deposit due',
        'deposit_held' => 'Deposit securely held',
        'checked_in' => 'Checked in',
        'completed' => 'Completed',
        'rejected' => 'Declined',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Use at least 10 characters.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Include an uppercase letter, a lowercase letter and a number.';
    }
    return null;
}

function enforce_login_throttle(): void
{
    $attempts = $_SESSION['login_attempts'] ?? [];
    $cutoff = time() - 600;
    $attempts = array_values(array_filter($attempts, fn ($timestamp) => $timestamp > $cutoff));
    $_SESSION['login_attempts'] = $attempts;
    if (count($attempts) >= 5) {
        http_response_code(429);
        exit('Too many sign-in attempts. Please wait ten minutes and try again.');
    }
}

function register_login_failure(): void
{
    $_SESSION['login_attempts'][] = time();
}

function clear_login_failures(): void
{
    unset($_SESSION['login_attempts']);
}
