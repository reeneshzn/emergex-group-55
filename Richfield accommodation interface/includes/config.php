<?php
/**
 * Local database credentials.
 *
 * Created from config.sample.php. This file is git-ignored so real
 * credentials never reach the shared repository - which is why a fresh
 * clone does not have it, and why the site falls back to sample data
 * until someone creates it.
 *
 * These are the stock XAMPP values: root with no password, on the
 * default port.
 */

return [

    'driver' => 'mysql',

    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'richfield_accommodation',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',

    'sqlite_path' => __DIR__ . '/../database/richfield.sqlite',

    'strict' => false,
];
