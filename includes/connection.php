<?php
// ======================================================
// LABTRACK CONFIGURATION
// Supports local XAMPP and hosted InfinityFree
// ======================================================

$hostName = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
$isLocal = PHP_SAPI === 'cli' || in_array($hostName, ['localhost', '127.0.0.1', '[::1]'], true);

if ($isLocal) {
    // ---------- LOCAL XAMPP DATABASE ----------
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'labtrack');   // your local database name
    define('DB_USER', 'root');
    define('DB_PASS', '');           // change if your local MySQL has a password
} else {
    // ---------- HOSTED DATABASE (InfinityFree) ----------
    // Copy these 4 values from your hosting panel > MySQL Databases.
    define('DB_HOST', 'sql111.infinityfree.com');
    define('DB_NAME', 'if0_43236344_LABTRACK_DATABASE');
    define('DB_USER', 'if0_43236344');
    define('DB_PASS', '3ShaMae143');
}

// Set to true ONLY while fixing a connection problem: the page then shows the real
// MySQL error. Set it back to false afterwards (never leave it on a public site).
define('LABTRACK_DEBUG', true);
define('IS_LOCAL', $isLocal);

define('FINE_PER_DAY', 20);

// ======================================================
// MAIN FUNCTIONS LIVE IN index.php
// (database connection, query helpers, transactions, login check, redirect)
// ======================================================
require_once dirname(__DIR__) . '/index.php';

// ======================================================
// SESSION AND TIMEZONE
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

// ======================================================
// BASE URL DETECTION
// ======================================================

$__root = str_replace(
    '\\',
    '/',
    realpath(__DIR__ . '/..') ?: ''
);

$__doc = str_replace(
    '\\',
    '/',
    realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''
);

if (
    $__doc !== '' &&
    stripos($__root, $__doc) === 0
) {
    define(
        'BASE_URL',
        substr($__root, strlen($__doc))
    );
} else {
    define('BASE_URL', '');
}

// ======================================================
// GENERAL HELPERS
// ======================================================

function e($s)
{
    return htmlspecialchars(
        (string) $s,
        ENT_QUOTES,
        'UTF-8'
    );
}

function url($p = '')
{
    return BASE_URL . '/' . ltrim($p, '/');
}

function money($n)
{
    return '₱' . number_format((float) $n, 2);
}

function dt($s, $f = 'M j, Y')
{
    return $s ? date($f, strtotime($s)) : '—';
}

function post($k, $d = '')
{
    return isset($_POST[$k])
        ? trim((string) $_POST[$k])
        : $d;
}

function nullable($v)
{
    return $v === '' ? null : $v;
}

// ======================================================
// FLASH MESSAGES
// ======================================================

function flash($msg = null, $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'][] = [$type, $msg];
        return null;
    }

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

// ======================================================
// CSRF PROTECTION
// ======================================================

function csrf()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' .
        e(csrf()) . '">';
}

// A file bigger than post_max_size arrives as an EMPTY form, which would otherwise look like a CSRF error.
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' &&
    empty($_POST) && empty($_FILES) &&
    (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    http_response_code(413);
    exit('The upload is larger than this server allows (post_max_size). Go back and choose a smaller file.');
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' &&
    !hash_equals(
        csrf(),
        (string) ($_POST['_csrf'] ?? '')
    )
) {
    http_response_code(419);

    exit(
        'Invalid or expired form token. ' .
        'Go back and reload the page.'
    );
}

// ======================================================
// AVATAR AND BADGE HELPERS
// ======================================================

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $result = '';

    foreach (array_slice($parts, 0, 2) as $word) {
        $result .= strtoupper(substr($word, 0, 1));
    }

    return $result ?: '?';
}

function user_avatar($user)
{
    if (!empty($user['profile_photo'])) {
        $photo = (string) $user['profile_photo'];
        $parts = parse_url($photo);

        $isRemote =
            filter_var($photo, FILTER_VALIDATE_URL) &&
            is_array($parts) &&
            isset($parts['scheme']) &&
            in_array(
                strtolower($parts['scheme']),
                ['http', 'https'],
                true
            );

        $src = $isRemote ? $photo : url($photo);

        return '<span class="av av-photo">' .
            '<img src="' . e($src) .
            '" alt="Profile picture" ' .
            'referrerpolicy="no-referrer">' .
            '</span>';
    }

    return '<span class="av">' .
        e(initials($user['full_name'] ?? '')) .
        '</span>';
}

function badge($text)
{
    $class = strtolower(
        preg_replace('/[^a-z]+/i', '-', (string) $text)
    );

    return '<span class="badge b-' . $class . '">' .
        e($text) .
        '</span>';
}

// ======================================================
// OVERDUE AND EQUIPMENT HELPERS
// ======================================================

function days_overdue($expected, $at = null)
{
    $date = strtotime($at ?? 'now');
    $due = strtotime($expected . ' 23:59:59');

    $days = ($date - $due) / 86400;

    return $days > 0 ? (int) ceil($days) : 0;
}

function qr_for($id)
{
    return 'EQ-' . str_pad(
        (string) $id,
        4,
        '0',
        STR_PAD_LEFT
    );
}

// ======================================================
// DAILY STATISTICS
// ======================================================

function daily_series(
    $table,
    $col,
    $days = 14,
    $where = '1=1'
) {
    $days = max(1, min(365, (int) $days));

    $rows = all(
        "SELECT DATE($col) AS d, COUNT(*) AS c
         FROM $table
         WHERE $where
         AND $col >= DATE_SUB(
             CURDATE(),
             INTERVAL " . ($days - 1) . " DAY
         )
         GROUP BY DATE($col)"
    );

    $map = [];

    foreach ($rows as $row) {
        $map[$row['d']] = (int) $row['c'];
    }

    $out = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $out[] = $map[$date] ?? 0;
    }

    return $out;
}