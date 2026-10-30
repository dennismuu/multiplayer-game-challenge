<?php
declare(strict_types=1);

define('DB_HOST', getenv('TRIVIA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('TRIVIA_DB_NAME') ?: 'trivia_game');
define('DB_USER', getenv('TRIVIA_DB_USER') ?: 'root');
define('DB_PASS', getenv('TRIVIA_DB_PASS') ?: '');
define('SETUP_KEY', getenv('TRIVIA_SETUP_KEY') ?: '51X0h5Hh+zivjd/HUZSNZ9xAberYUorP0G6unsDA+GM=');

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Trivia Night database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The database is unavailable. Check the site database settings and server error log.');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function positive_int(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number !== false && $number > 0 ? $number : null;
}

function form_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function password_is_valid(string $password): bool
{
    return preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{6,}$/sD', $password) === 1;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('The form expired. Go back, reload the page, and try again.');
    }
}

function set_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function current_user(PDO $pdo): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        set_flash('Please sign in to continue.', 'error');
        redirect('index.php');
    }

    return $user;
}

function require_admin(PDO $pdo): array
{
    $user = require_login($pdo);
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }

    return $user;
}
