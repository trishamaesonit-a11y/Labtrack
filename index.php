<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/includes/connection.php';
}

// ======================================================
// PDO DATABASE CONNECTION
// ======================================================

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST .
            ';dbname=' . DB_NAME .
            ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;

    } catch (PDOException $ex) {
        error_log(
            'LabTrack PDO connection error: ' .
            $ex->getMessage()
        );

        http_response_code(500);

        // Detect the literal YOUR_... placeholders (NOT the real credentials).
        $hasPlaceholder =
            strpos(DB_NAME, 'YOUR_') === 0 ||
            strpos(DB_USER, 'YOUR_') === 0 ||
            strpos(DB_PASS, 'YOUR_') === 0;

        $hint = 'Check the database settings in includes/connection.php and the PHP error log.';

        if ($hasPlaceholder) {
            $hint = 'The hosted database settings in includes/connection.php still contain the '
                  . 'YOUR_... placeholders. Replace them with the real values from your hosting '
                  . 'panel (MySQL Databases).';
        }

        // When debugging is on, ALWAYS append the real MySQL error and the
        // values actually used, so the cause is obvious.
        if (LABTRACK_DEBUG) {
            $hint .= '<br><br>'
                   . '<strong>MySQL error:</strong> '
                   . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8')
                   . '<br><strong>Host:</strong> '
                   . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8')
                   . '<br><strong>Database:</strong> '
                   . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8')
                   . '<br><strong>User:</strong> '
                   . htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8')
                   . '<br><strong>Detected as local:</strong> '
                   . (IS_LOCAL ? 'yes' : 'no');
        }

        exit('<h3>Database connection failed.</h3><p>' . $hint . '</p>');
    }
}

// ======================================================
// DATABASE HELPERS
// ======================================================

function q($sql, $p = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($p);

    return $stmt;
}

function all($sql, $p = [])
{
    return q($sql, $p)->fetchAll();
}

function one($sql, $p = [])
{
    $result = q($sql, $p)->fetch();

    return $result ?: null;
}

function val($sql, $p = [])
{
    return q($sql, $p)->fetchColumn();
}

// ======================================================
// TRANSACTIONS (START TRANSACTION / COMMIT / ROLLBACK)
// Use these when several queries must succeed or fail together.
// ======================================================

function db_begin(): bool
{
    // START TRANSACTION: changes after this are not permanent yet.
    return db()->inTransaction() ? true : db()->beginTransaction();
}

function db_commit(): bool
{
    // COMMIT: saves every change made in the transaction.
    return db()->inTransaction() ? db()->commit() : false;
}

function db_rollback(): bool
{
    // ROLLBACK: undoes every change made since db_begin().
    return db()->inTransaction() ? db()->rollBack() : false;
}

/**
 * Run $work inside one transaction.
 * Returns whatever $work returns. If $work throws, everything is rolled back
 * and the exception is thrown again.
 */
function transaction(callable $work)
{
    $own = !db()->inTransaction();   // allows safe nesting
    if ($own) db_begin();

    try {
        $result = $work();
        if ($own) db_commit();
        return $result;
    } catch (Throwable $ex) {
        if ($own) db_rollback();
        throw $ex;
    }
}

function redirect($p)
{
    header('Location: ' . url($p));
    exit;
}

// ======================================================
// CURRENT USER
// ======================================================

function me()
{
    static $u = false;

    if ($u === false) {
        $u = isset($_SESSION['uid'])
            ? one(
                'SELECT user_id, username, full_name, email,
                        role, department, phone, bio,
                        profile_photo, status, created_at
                 FROM users
                 WHERE user_id = ?',
                [$_SESSION['uid']]
            )
            : null;

        if ($u && $u['status'] !== 'active') {
            $u = null;
        }
    }

    return $u;
}

// ======================================================
// ROLE HELPERS
// ======================================================

function home_for($role)
{
    return $role === 'admin'
        ? 'admin/dashboard.php'
        : 'user/dashboard.php';
}

function require_role(array $roles)
{
    $u = me();

    if (!$u) {
        redirect('login.php');
    }

    if (!in_array($u['role'], $roles, true)) {
        redirect(home_for($u['role']));
    }

    return $u;
}

// ======================================================
// ENTRY POINT (runs only when index.php itself is opened)
// ======================================================

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $u = me();
    redirect($u ? home_for($u['role']) : 'login.php');
}
