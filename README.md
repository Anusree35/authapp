# AuthApp – GUVI Internship Project

## Flow: Register → Login → Profile

---

## Folder Structure

```
authapp/
├── assets/               ← images/icons (add as needed)
├── css/
│   └── style.css         ← all styles (no inline CSS anywhere)
├── js/
│   ├── register.js       ← jQuery AJAX registration
│   ├── login.js          ← jQuery AJAX login + localStorage
│   └── profile.js        ← jQuery AJAX profile get/update/logout
├── php/
│   ├── config.php        ← MySQL, MongoDB, Redis connections
│   ├── register.php      ← POST register → MySQL
│   ├── login.php         ← POST login → Redis session
│   └── profile.php       ← GET/POST profile → MongoDB
├── index.html
├── login.html
├── profile.html
├── register.html
├── setup.sql             ← run once to create MySQL DB/table
├── composer.json
└── README.md
```

---

## Requirements Checklist

- ✅ HTML, CSS, JS, PHP in **separate files** — no code co-existing in same file
- ✅ **jQuery AJAX only** — no HTML form submit used anywhere
- ✅ **Bootstrap 5** — all forms and layout use Bootstrap for responsiveness
- ✅ **MySQL** with **Prepared Statements only** — stores registration data
- ✅ **MongoDB** — stores profile details (dob, contact, gender, location, bio)
- ✅ **No plain SQL statements** — every query uses PDO + prepared statements
- ✅ **localStorage** session management — no PHP sessions used anywhere
- ✅ **Redis** stores session token on backend with 24-hour TTL

---

## Tech Stack

| Layer    | Technology            |
|----------|-----------------------|
| Frontend | HTML5, CSS3, Bootstrap 5 |
| JS       | Vanilla JS + jQuery AJAX |
| Backend  | PHP 8.x               |
| Auth DB  | MySQL 8 (PDO Prepared Statements) |
| Profiles | MongoDB 6             |
| Sessions | Redis 7 (token-based) |

---

## Setup Instructions

### Step 1 — Install PHP extensions

Make sure these are enabled in your `php.ini`:
```
extension=pdo_mysql
extension=redis       ; or php-redis
extension=mongodb     ; the PHP mongodb extension
```

On Ubuntu/Debian:
```bash
sudo apt install php-mysql php-redis php-mongodb
```

On Windows (XAMPP): enable in `php.ini`, install via PECL.

---

### Step 2 — Install Composer dependencies

```bash
cd authapp/
composer install
```

This installs the MongoDB PHP library.

---

### Step 3 — Create MySQL database

Option A – run the SQL file:
```bash
mysql -u root -p < setup.sql
```

Option B – the app auto-creates the DB on first registration.

---

### Step 4 — Configure credentials

Edit `php/config.php` and set:

```php
define('MYSQL_USER', 'root');
define('MYSQL_PASS', 'your_password');  // your MySQL password

define('MONGO_URI',  'mongodb://127.0.0.1:27017');

define('REDIS_HOST', '127.0.0.1');
define('REDIS_PASS', '');               // Redis password if any

define('TOKEN_SECRET', 'change_this_to_something_long_and_random');
```

---

### Step 5 — Start services

```bash
# MySQL
sudo systemctl start mysql

# MongoDB
sudo systemctl start mongod

# Redis
sudo systemctl start redis-server
```

---

### Step 6 — Run the project

Using PHP's built-in server:
```bash
php -S localhost:8000
```
Then open: http://localhost:8000

Or copy the folder to your XAMPP `htdocs/` and visit:
http://localhost/authapp/

---

## How It Works

### Registration
1. User fills form → JS validates → jQuery AJAX POST to `php/register.php`
2. PHP validates → checks duplicate email/username (prepared stmt)
3. Bcrypt hashes password → inserts into MySQL (prepared stmt)
4. Creates empty profile document in MongoDB
5. Returns `{success: true}` → JS redirects to login

### Login
1. User fills form → jQuery AJAX POST to `php/login.php`
2. PHP fetches user by email (prepared stmt) → `password_verify()`
3. Generates token → stores `sess:<token> = user_id` in Redis (24h TTL)
4. Returns token + user info → JS saves to `localStorage`
5. JS redirects to profile

### Profile
1. JS reads token from `localStorage` → sends in `X-Auth-Token` header
2. PHP validates token against Redis on every request
3. GET: fetches profile from MongoDB + created_at from MySQL
4. POST: upserts profile fields in MongoDB
5. Logout: JS sends logout action → PHP deletes Redis key → JS clears localStorage

---

## Security Notes

- Passwords hashed with bcrypt (cost=12)
- All MySQL queries use PDO Prepared Statements — SQL injection impossible
- Session tokens validated against Redis on every protected request
- No PHP `$_SESSION` used anywhere
- CORS headers set for API access
