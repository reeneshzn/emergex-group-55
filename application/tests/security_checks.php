<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [
    'Prepared statements are used' => fn() => substr_count(file_get_contents($root . '/app/includes/bootstrap.php'), 'prepare(') >= 2,
    'Passwords use password_hash' => fn() => str_contains(file_get_contents($root . '/public/register.php'), 'password_hash('),
    'Login uses password_verify' => fn() => str_contains(file_get_contents($root . '/public/login.php'), 'password_verify('),
    'CSRF verification is present' => fn() => str_contains(file_get_contents($root . '/app/includes/bootstrap.php'), 'hash_equals('),
    'Uploads block executable extensions' => fn() => str_contains(file_get_contents($root . '/public/uploads/.htaccess'), 'php|phtml|phar'),
    'Database tables use InnoDB' => fn() => substr_count(file_get_contents($root . '/database/schema.sql'), 'ENGINE=InnoDB') >= 7,
    'Foreign keys are defined' => fn() => substr_count(file_get_contents($root . '/database/schema.sql'), 'FOREIGN KEY') >= 8,
];
foreach ($checks as $label => $check) {
    $passed = $check();
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failures[] = $label;
}
exit($failures ? 1 : 0);
