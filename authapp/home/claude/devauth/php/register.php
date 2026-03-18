<?php
/**
 * register.php
 * Accepts POST JSON with: first_name, last_name, username, email, password
 * Stores user in MySQL (Prepared Statements only).
 * Creates empty profile document in MongoDB.
 */

require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

/* ── Read & decode JSON body ── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
}

$first_name = isset($body['first_name']) ? trim($body['first_name']) : '';
$last_name  = isset($body['last_name'])  ? trim($body['last_name'])  : '';
$username   = isset($body['username'])   ? trim($body['username'])   : '';
$email      = isset($body['email'])      ? trim(strtolower($body['email'])) : '';
$password   = isset($body['password'])   ? $body['password']         : '';

/* ── Validate ── */
if (!$first_name || !$last_name || !$username || !$email || !$password) {
    jsonResponse(['success' => false, 'message' => 'All fields are required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    jsonResponse(['success' => false, 'message' => 'Username: 3-30 chars, letters/numbers/underscore only.'], 400);
}
if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
}

/* ── MySQL: create DB+table if needed, then insert ── */
try {
    bootMySQL();
    $pdo = getMySQL();

    // Check duplicates – PREPARED STATEMENT
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1'
    );
    $stmt->execute([':email' => $email, ':username' => $username]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Email or username is already registered.'], 409);
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert – PREPARED STATEMENT (no plain SQL)
    $ins = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, username, email, password)
         VALUES (:fn, :ln, :un, :em, :pw)'
    );
    $ins->execute([
        ':fn' => $first_name,
        ':ln' => $last_name,
        ':un' => $username,
        ':em' => $email,
        ':pw' => $hash,
    ]);
    $userId = (int)$pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('[MySQL] register error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}

/* ── MongoDB: create empty profile document ── */
try {
    $col = getMongo();
    $col->insertOne([
        'user_id'    => $userId,
        'dob'        => null,
        'contact'    => null,
        'gender'     => null,
        'location'   => null,
        'bio'        => null,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ]);
} catch (Exception $e) {
    // Non-fatal – profile can be created on first update
    error_log('[MongoDB] register profile insert error: ' . $e->getMessage());
}

jsonResponse(['success' => true, 'message' => 'Account created successfully!'], 201);
