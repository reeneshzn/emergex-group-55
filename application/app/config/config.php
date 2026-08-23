<?php
declare(strict_types=1);

// XAMPP defaults are used for the academic demo. Change these values when needed.
return [
    'app_name' => 'EMERGEX',
    'base_url' => '/emergex/public',
    'environment' => 'development',
    'db' => [
        'host' => '127.0.0.1',
        'port' => (int) ($_SERVER['EMERGEX_DB_PORT'] ?? (getenv('EMERGEX_DB_PORT') ?: 3306)),
        'name' => 'emergex',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'directory' => dirname(__DIR__, 2) . '/public/uploads',
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],
];
