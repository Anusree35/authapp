<?php
/**
 * profile.php
 * GET  ?user_id=N   → fetch profile from MongoDB  (auth via X-Auth-Token header)
 * POST { user_id, dob, contact, gender, location, bio } → upsert in MongoDB
 *
 * Authentication: token from X-Auth-Token header validated against Redis.
 */

require_once __DIR__ . '/config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];

/* ── Authenticate every request ── */
$token         = getRequestToken();
$sessionUserId = validateToken($token);

if ($sessionUserId === null) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

/* ══════════════════════════════════════════════
   GET  –  Fetch profile details from MongoDB
   ══════════════════════════════════════════════ */
if ($method === 'GET') {

    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    if ($userId !== $sessionUserId) {
        jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }

    /* Get created_at from MySQL */
    $createdAt = null;
    try {
        $pdo  = getMySQL();
        $stmt = $pdo->prepare('SELECT created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row) $createdAt = $row['created_at'];
    } catch (PDOException $e) {
        error_log('[MySQL] profile GET error: ' . $e->getMessage());
    }

    /* Get profile from MongoDB */
    $profile = [
        'dob'        => null,
        'contact'    => null,
        'gender'     => null,
        'location'   => null,
        'bio'        => null,
        'created_at' => $createdAt,
    ];

    try {
        $col = getMongo();
        $doc = $col->findOne(['user_id' => $userId]);
        if ($doc) {
            $profile['dob']      = isset($doc['dob'])      ? $doc['dob']      : null;
            $profile['contact']  = isset($doc['contact'])  ? $doc['contact']  : null;
            $profile['gender']   = isset($doc['gender'])   ? $doc['gender']   : null;
            $profile['location'] = isset($doc['location']) ? $doc['location'] : null;
            $profile['bio']      = isset($doc['bio'])      ? $doc['bio']      : null;
        }
    } catch (Exception $e) {
        error_log('[MongoDB] profile GET error: ' . $e->getMessage());
        // Return partial data even if Mongo fails
    }

    jsonResponse(['success' => true, 'profile' => $profile]);

/* ══════════════════════════════════════════════
   POST  –  Update profile in MongoDB (upsert)
   ══════════════════════════════════════════════ */
} elseif ($method === 'POST') {

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body)) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }

    $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;

    if ($userId !== $sessionUserId) {
        jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }

    /* Sanitize fields */
    $dob      = !empty($body['dob'])      ? $body['dob']             : null;
    $contact  = !empty($body['contact'])  ? trim($body['contact'])   : null;
    $gender   = !empty($body['gender'])   ? trim($body['gender'])    : null;
    $location = !empty($body['location']) ? trim($body['location'])  : null;
    $bio      = !empty($body['bio'])      ? trim($body['bio'])       : null;

    /* Validate DOB format if provided */
    if ($dob && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        jsonResponse(['success' => false, 'message' => 'Invalid date format.'], 400);
    }

    /* Upsert into MongoDB */
    try {
        $col = getMongo();
        $col->updateOne(
            ['user_id' => $userId],
            ['$set' => [
                'user_id'    => $userId,
                'dob'        => $dob,
                'contact'    => $contact,
                'gender'     => $gender,
                'location'   => $location,
                'bio'        => $bio,
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]],
            ['upsert' => true]
        );
    } catch (Exception $e) {
        error_log('[MongoDB] profile POST error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Failed to update profile. Please try again.'], 500);
    }

    jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);

} else {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
