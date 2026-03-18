<?php
/**
 * login.php
 * POST  { email, password }         → authenticate, store token in Redis
 * POST  { action: 'logout', token } → delete Redis session
 *
 * Session is stored in Redis only.
 * No PHP sessions are used anywhere.
 * The token is returned to JS which saves it in localStorage.
 */

require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
}

/* ── LOGOUT action ── */
if (isset($body['action']) && $body['action'] === 'logout') {
    $token = isset($body['token']) ? trim($body['token']) : '';
    if ($token) {
        deleteSession($token);
    }
    jsonResponse(['success' => true, 'message' => 'Logged out.']);
}

/* ── LOGIN ── */
$email    = isset($body['email'])    ? trim(strtolower($body['email'])) : '';
$password = isset($body['password']) ? $body['password']                : '';

if (!$email || !$password) {
    jsonResponse(['success' => false, 'message' => 'Email and password are required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address.'], 400);
}

/* ── MySQL: fetch user by email – PREPARED STATEMENT ── */
try {
    $pdo  = getMySQL();
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, username, email, password
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[MySQL] login error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}

/* ── Verify password ── */
if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

/* ── Generate token & store in Redis ── */
$token = generateToken((int)$user['id']);

try {
    storeSession($token, (int)$user['id']);
} catch (Exception $e) {
    error_log('[Redis] session store error: ' . $e->getMessage());
    // Allow login to continue even if Redis is down (token returned, but won't validate on next request)
    // If you want to strictly require Redis, uncomment below:
    // jsonResponse(['success' => false, 'message' => 'Session service unavailable.'], 503);
}

jsonResponse([
    'success'    => true,
    'token'      => $token,
    'user_id'    => (int)$user['id'],
    'username'   => $user['username'],
    'email'      => $user['email'],
    'first_name' => $user['first_name'],
    'last_name'  => $user['last_name'],
]);
