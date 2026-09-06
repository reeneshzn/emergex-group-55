<?php
/**
 * ############################################################
 * ###  DATABASE CONNECTOR — CREDENTIALS                    ###
 * ############################################################
 *
 * COPY THIS FILE TO `config.php` AND EDIT IT.
 *
 *     copy includes\config.sample.php includes\config.php
 *
 * `config.php` is git-ignored so real credentials never reach the shared
 * repository. This sample file IS committed, so never put a real password
 * in it.
 *
 * If `config.php` does not exist, the site still runs — it falls back to
 * the sample arrays in data.php and simply does not save anything. That
 * means anyone can clone the repo and open the site without setting up
 * MySQL first.
 */

return [

    /* ------------------------------------------------------------------
       Which driver.
         'mysql'  — MySQL / MariaDB. This is what XAMPP gives you.
         'sqlite' — a single file, no server needed. Handy for testing.
         null     — no database. The site runs on sample data.
       ------------------------------------------------------------------ */
    'driver' => 'mysql',

    /* ---- MySQL / MariaDB settings (ignored when driver is sqlite) ---- */
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'richfield_accommodation',
    'username' => 'root',
    'password' => '',          // XAMPP's default root password is empty
    'charset'  => 'utf8mb4',   // do not use utf8 — it cannot store emoji

    /* ---- SQLite settings (ignored when driver is mysql) ----
       Relative paths resolve against the project root. Keep the file
       outside the web root on a real server. */
    'sqlite_path' => __DIR__ . '/../database/richfield.sqlite',

    /* ------------------------------------------------------------------
       When true, a failed connection throws instead of silently falling
       back to sample data. Turn this on once the database is the real
       source of truth — otherwise a broken connection looks like the site
       working fine on stale demo rows, which is worse than an error.
       ------------------------------------------------------------------ */
    'strict' => false,
];
