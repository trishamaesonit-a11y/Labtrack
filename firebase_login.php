<?php
require __DIR__ . '/includes/connection.php';

function firebase_json_error($message, $status) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function firebase_base64url_decode($value) {
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    return $decoded === false ? null : $decoded;
}

function firebase_fetch_signing_certificates() {
    $curl = curl_init('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $status < 200 || $status >= 300) {
        error_log('Firebase signing certificate request failed: ' . ($error ?: 'HTTP ' . $status));
        return null;
    }
    $certificates = json_decode($body, true);
    return is_array($certificates) ? $certificates : null;
}

function firebase_verify_id_token($token, $projectId, &$failureReason = null) {
    $reject = function ($reason) use (&$failureReason) {
        $failureReason = $reason;
        return null;
    };
    $parts = explode('.', $token);
    if (count($parts) !== 3) return $reject('Malformed token.');

    $headerJson = firebase_base64url_decode($parts[0]);
    $claimsJson = firebase_base64url_decode($parts[1]);
    $signature = firebase_base64url_decode($parts[2]);
    if ($headerJson === null || $claimsJson === null || $signature === null) return $reject('Token encoding is invalid.');

    $header = json_decode($headerJson, true);
    $claims = json_decode($claimsJson, true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        return $reject('Token header or claims are invalid.');
    }

    $certificates = firebase_fetch_signing_certificates();
    if (!$certificates) return $reject('Could not retrieve Firebase signing certificates.');
    if (empty($certificates[$header['kid']])) return $reject('No Firebase signing certificate matched the token key ID.');
    $publicKey = openssl_pkey_get_public($certificates[$header['kid']]);
    if ($publicKey === false) return $reject('Firebase signing certificate is invalid.');
    $verified = openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256);
    openssl_free_key($publicKey);
    if ($verified !== 1) return $reject('Token signature verification failed.');

    $now = time();
    $firebase = $claims['firebase'] ?? [];
    if (($claims['aud'] ?? '') !== $projectId) return $reject('Token belongs to a different Firebase project (audience mismatch).');
    if (($claims['iss'] ?? '') !== 'https://securetoken.google.com/' . $projectId) return $reject('Token issuer does not match the configured Firebase project.');
    if (empty($claims['sub']) || strlen((string)$claims['sub']) > 128) return $reject('Token subject is missing or invalid.');
    if ((int)($claims['exp'] ?? 0) <= $now) return $reject('Firebase ID token has expired.');
    if ((int)($claims['iat'] ?? 0) > $now + 60 || (int)($claims['auth_time'] ?? 0) > $now + 60) return $reject('Token issue time is invalid.');
    if (($firebase['sign_in_provider'] ?? '') !== 'google.com') return $reject('Token was not issued for Google sign-in.');
    if (($claims['email_verified'] ?? false) !== true) return $reject('Google email is not verified.');
    if (empty($claims['email']) || !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) return $reject('Token does not contain a valid email address.');
    return $claims;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') firebase_json_error('Use POST to sign in.', 405);
if (me()) firebase_json_error('You are already signed in.', 409);

$projectId = trim((string)(getenv('LABTRACK_FIREBASE_PROJECT_ID') ?: 'project1-303d9'));
if ($projectId === '' || !preg_match('/^[a-z0-9-]+$/', $projectId)) {
    firebase_json_error('Firebase sign-in is not configured. Follow the Firebase setup in README.md.', 503);
}
if (!extension_loaded('curl') || !extension_loaded('openssl')) {
    error_log('Firebase sign-in requires PHP curl and openssl extensions.');
    firebase_json_error('Firebase sign-in is unavailable because a required PHP extension is disabled.', 500);
}

$idToken = trim(post('id_token'));
if ($idToken === '' || strlen($idToken) > 16384) firebase_json_error('Firebase sign-in token is missing or invalid.', 400);
$verificationFailure = null;
$claims = firebase_verify_id_token($idToken, $projectId, $verificationFailure);
if (!$claims) {
    error_log('Firebase ID token verification rejected: ' . ($verificationFailure ?: 'Unknown verification error.'));
    firebase_json_error('Firebase could not verify your sign-in. Please try again.', 401);
}

$email = strtolower($claims['email']);
$user = one('SELECT user_id,role,status FROM users WHERE LOWER(email)=? LIMIT 1', [$email]);
if (!$user) firebase_json_error('No LabTrack account uses this Google email. Create an account first.', 403);
if ($user['status'] !== 'active') firebase_json_error('This LabTrack account is disabled.', 403);

session_regenerate_id(true);
$_SESSION['uid'] = (int)$user['user_id'];
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['redirect' => url(home_for($user['role']))]);
