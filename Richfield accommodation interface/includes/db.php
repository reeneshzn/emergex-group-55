<?php
/**
 * ##########################################################################
 * ###                                                                    ###
 * ###   [DB-CONNECTOR]  THIS IS THE DATABASE CONNECTOR.                  ###
 * ###   Every database connection in the project is created here and     ###
 * ###   nowhere else. No other file calls `new PDO`.                     ###
 * ###                                                                    ###
 * ##########################################################################
 *
 * WHAT THIS FILE DOES
 *   - reads credentials from includes/config.php (git-ignored)
 *   - opens ONE shared PDO connection, lazily, on first use
 *   - supports MySQL/MariaDB (XAMPP) and SQLite (no server needed)
 *   - degrades gracefully: if there is no config or the server is down,
 *     db() returns null and the site runs on the sample arrays instead
 *
 * WHY IT DEGRADES INSTEAD OF CRASHING
 *   This repo is shared. Not everyone will have MySQL running. A missing
 *   database should not stop a teammate from opening the site. Set
 *   'strict' => true in config.php once the database is the real source of
 *   truth, and a failed connection becomes a loud error instead.
 *
 * HOW TO USE IT
 *
 *     require_once __DIR__ . '/db.php';
 *
 *     $pdo = db();                 // PDO instance, or null if unavailable
 *     if ($pdo) { ... }
 *
 *     $rows = db_all('SELECT * FROM campuses WHERE province = ?', [$p]);
 *     $row  = db_one('SELECT * FROM campuses WHERE id = ?', [$id]);
 *     $id   = db_insert('INSERT INTO ... VALUES (?, ?)', [$a, $b]);
 *
 * ALWAYS pass values as the second argument. Never build SQL by
 * concatenating user input — the helpers below use prepared statements so
 * that injection is not possible if you use them as intended.
 */

/** Loaded config, or null when there is no config.php. */
function db_config(): ?array
{
    static $config = null;
    static $loaded = false;

    if ($loaded) {
        return $config;
    }
    $loaded = true;

    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        return $config = null;
    }

    $loadedConfig = require $path;
    if (!is_array($loadedConfig) || empty($loadedConfig['driver'])) {
        return $config = null;
    }

    return $config = $loadedConfig;
}

/**
 * The shared PDO connection, or null if the database is unavailable.
 *
 * Connects on first call and reuses the same handle afterwards, so calling
 * this in a loop costs nothing.
 */
function db(): ?PDO
{
    static $pdo = null;
    static $tried = false;

    if ($tried) {
        return $pdo;
    }
    $tried = true;

    $cfg = db_config();
    if ($cfg === null) {
        return null;
    }

    try {
        if ($cfg['driver'] === 'sqlite') {
            $path = $cfg['sqlite_path'] ?? __DIR__ . '/../database/richfield.sqlite';
            $dir  = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, db_pdo_options());
            // Off by default in SQLite; without it ON DELETE CASCADE is ignored.
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int) ($cfg['port'] ?? 3306),
                $cfg['database'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO(
                $dsn,
                $cfg['username'] ?? '',
                $cfg['password'] ?? '',
                db_pdo_options()
            );
        }
    } catch (PDOException $e) {
        $pdo = null;
        error_log('[richfield] database connection failed: ' . $e->getMessage());
        if (!empty($cfg['strict'])) {
            throw $e;
        }
    }

    return $pdo;
}

function db_pdo_options(): array
{
    return [
        // Turn SQL errors into exceptions instead of silent false returns.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Plain associative arrays, which is what the rest of the site expects.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Real prepared statements, not driver-side emulation.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

/** True when a usable connection exists. Cheap to call repeatedly. */
function db_available(): bool
{
    return db() instanceof PDO;
}

/** Which driver is active: 'mysql', 'sqlite', or null. */
function db_driver(): ?string
{
    return db_available() ? (db_config()['driver'] ?? null) : null;
}

/* ------------------------------------------------------------------------
   Query helpers. All use prepared statements.
   Each returns a safe empty value when there is no connection, so callers
   can fall back to sample data without wrapping every call in a check.
   ------------------------------------------------------------------------ */

/** Every matching row. Returns [] when unavailable. */
function db_all(string $sql, array $params = []): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** The first matching row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** A single scalar from the first column, or null. */
function db_value(string $sql, array $params = [])
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

/** Run a write. Returns rows affected, or 0 when unavailable. */
function db_run(string $sql, array $params = []): int
{
    $pdo = db();
    if (!$pdo) {
        return 0;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Run an INSERT and return the new row id, or null when unavailable. */
function db_insert(string $sql, array $params = []): ?string
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Run several writes as one unit. Rolls back entirely if the callback
 * throws, so a half-written record can never be committed.
 *
 *     db_transaction(function (PDO $pdo) { ... });
 *
 * Returns whatever the callback returns, or null when unavailable.
 */
function db_transaction(callable $work)
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** True when the named table exists. Used to detect an un-migrated database. */
function db_has_table(string $table): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    try {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * True when the database is connected AND the schema has been imported.
 *
 * This is the flag the rest of the site checks. A connection to an empty
 * database is not enough — without tables the queries would just error, so
 * the site falls back to sample data until the schema is in place.
 */
function db_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_available() && db_has_table('listings');
    }
    return $ready;
}
