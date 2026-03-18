<?php
/**
 * config.php
 * Central configuration and helper functions.
 * All DB connections (MySQL, MongoDB, Redis) are here.
 */

/* ─────────────────────────────────────────────
   SETTINGS  –  Edit these for your environment
   ───────────────────────────────────────────── */

// MySQL
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_USER', 'root');
define('MYSQL_PASS', '');          // ← change if needed
define('MYSQL_DB',   'authapp');

// MongoDB
define('MONGO_URI',  'mongodb://127.0.0.1:27017');
define('MONGO_DB',   'authapp');
define('MONGO_COLL', 'profiles');

// Redis
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASS', '');          // ← leave empty if no password
define('SESSION_TTL', 86400);      // 24 hours

// Token secret
define('TOKEN_SECRET', 'authapp_super_secret_key_change_in_production');

/* ─────────────────────────────────────────────
   CORS + JSON headers
   ───────────────────────────────────────────── */
function setHeaders() {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/* ─────────────────────────────────────────────
   JSON response helper
   ───────────────────────────────────────────── */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ─────────────────────────────────────────────
   MySQL  –  PDO with real prepared statements
   ───────────────────────────────────────────── */
function getMySQL() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        MYSQL_HOST, MYSQL_PORT, MYSQL_DB
    );
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
    ];
    $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, $opts);
    return $pdo;
}

/* ─────────────────────────────────────────────
   MySQL bootstrap – create DB + table if needed
   ───────────────────────────────────────────── */
function bootMySQL() {
    $dsn  = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';charset=utf8mb4';
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $conn = new PDO($dsn, MYSQL_USER, MYSQL_PASS, $opts);

    $conn->exec("CREATE DATABASE IF NOT EXISTS `" . MYSQL_DB . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE `" . MYSQL_DB . "`");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `first_name` VARCHAR(100)  NOT NULL,
            `last_name`  VARCHAR(100)  NOT NULL,
            `username`   VARCHAR(60)   NOT NULL,
            `email`      VARCHAR(255)  NOT NULL,
            `password`   VARCHAR(255)  NOT NULL,
            `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_email`    (`email`),
            UNIQUE KEY `uq_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/* ─────────────────────────────────────────────
   MongoDB collection
   Requires: composer require mongodb/mongodb
   ───────────────────────────────────────────── */
function getMongo() {
    static $col = null;
    if ($col !== null) return $col;

    if (!class_exists('MongoDB\Client')) {
        throw new Exception('MongoDB driver not installed. Run: composer require mongodb/mongodb');
    }
    $client = new MongoDB\Client(MONGO_URI);
    $col    = $client->{MONGO_DB}->{MONGO_COLL};
    return $col;
}

/* ─────────────────────────────────────────────
   Redis connection
   ───────────────────────────────────────────── */
function getRedis() {
    static $redis = null;
    if ($redis !== null) return $redis;

    if (!class_exists('Redis')) {
        throw new Exception('Redis extension not installed.');
    }
    $redis = new Redis();
    $redis->connect(REDIS_HOST, (int)REDIS_PORT, 2.0);
    if (REDIS_PASS !== '') {
        $redis->auth(REDIS_PASS);
    }
    return $redis;
}

/* ─────────────────────────────────────────────
   Token helpers
   ───────────────────────────────────────────── */
function generateToken($userId) {
    $rand = bin2hex(random_bytes(24));
    return hash_hmac('sha256', $userId . '|' . time() . '|' . $rand, TOKEN_SECRET);
}

function getRequestToken() {
    // Check X-Auth-Token header
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'x-auth-token') return trim($v);
    }
    return null;
}

function validateToken($token) {
    if (!$token) return null;
    try {
        $redis  = getRedis();
        $userId = $redis->get('sess:' . $token);
        if ($userId !== false && $userId !== null) {
            $redis->expire('sess:' . $token, SESSION_TTL); // sliding expiry
            return (int)$userId;
        }
    } catch (Exception $e) {
        // Redis unavailable – deny access
        error_log('[Redis] validateToken error: ' . $e->getMessage());
    }
    return null;
}

function storeSession($token, $userId) {
    $redis = getRedis();
    $redis->setex('sess:' . $token, SESSION_TTL, (string)$userId);
}

function deleteSession($token) {
    try {
        $redis = getRedis();
        $redis->del('sess:' . $token);
    } catch (Exception $e) {
        error_log('[Redis] deleteSession: ' . $e->getMessage());
    }
}
