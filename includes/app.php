<?php
/**
 * Bootstrap for every page: DB, settings, staff login (with "remember me"),
 * CSRF, the guest's room cookie and the request logic shared by the APIs.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Europe/Rome');

const BRAND_COLOR = '#0786c4';          // Upgrade blue, default colour of a hotel's guest pages
const CALL_REPEAT_SECONDS = 30;      // a guest can send the same request again (reminder) after this
const CODE_MAX_FAILS_ROOM = 8;       // wrong codes per IP and room, in 15 minutes
const CODE_MAX_FAILS_IP = 25;        // wrong codes per IP on any room, in 15 minutes
const GUEST_COOKIE_DAYS = 14;        // the guest types the room code once per stay
const DEPT_KINDS = [
    'reception'    => ['Reception', '🛎️'],
    'housekeeping' => ['Piani', '🧹'],
    'maintenance'  => ['Manutenzione', '🔧'],
    'bar'          => ['Bar', '🍹'],
    'kitchen'      => ['Cucina', '🍽️'],
    'other'        => ['Altro', '📋'],
];

function db(): PDO
{
    return getDBConnection();
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------------

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Path of $rel inside the app ("/staff/" on the server, "/progetto/staff/" in a local subfolder). */
function app_path(string $rel = ''): string
{
    static $base = null;
    if ($base === null) {
        $doc  = str_replace('\\', '/', (string) (realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''));
        $root = str_replace('\\', '/', (string) (realpath(__DIR__ . '/..') ?: ''));
        $base = ($doc !== '' && str_starts_with($root, $doc)) ? rtrim(substr($root, strlen(rtrim($doc, '/'))), '/') : '';
    }
    return $base . '/' . ltrim($rel, '/');
}

function abs_url(string $rel = ''): string
{
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_path($rel);
}

/** The fixed link printed in a room's QR. */
function room_url(string $token): string
{
    return abs_url('r/' . $token);
}

/** Static asset URL with a version so phones pick up new deploys. */
function asset(string $rel): string
{
    $file = __DIR__ . '/../' . $rel;
    return app_path($rel) . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

/** Small "powered by Upgrade" footer, on staff and guest pages. */
function powered_by(): string
{
    return '<footer class="powered"><span>powered by</span><img src="' . h(asset('assets/brand/upgrade-logo.png'))
        . '" alt="Upgrade" width="96" height="31"></footer>';
}

function redirect(string $rel): never
{
    header('Location: ' . app_path($rel));
    exit;
}

// ---------------------------------------------------------------------------
// JSON
// ---------------------------------------------------------------------------

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Answers the browser now, then runs $after (e.g. push notifications) without making it wait. */
function json_out_then(array $data, callable $after): never
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $body = json_encode($data, JSON_UNESCAPED_UNICODE);
    ignore_user_abort(true);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Connection: close');
    header('Content-Length: ' . strlen($body));
    while (ob_get_level() > 0) ob_end_flush();
    echo $body;
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    try {
        $after();
    } catch (Throwable $e) {
        error_log('roomhotel: after-response task failed: ' . $e->getMessage());
    }
    exit;
}

/** POST body: JSON or form fields. */
function input(): array
{
    static $in = null;
    if ($in === null) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $in = is_array($json) ? $json + $_POST : $_POST;
    }
    return $in;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------------------------------------------------------------------------
// App settings (key/value in the DB: secrets, VAPID keys)
// ---------------------------------------------------------------------------

function setting(string $k): ?string
{
    $st = db()->prepare('SELECT v FROM app_settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function setting_set(string $k, ?string $v): void
{
    db()->prepare('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$k, $v]);
}

/** Value created once and then shared by every request (first writer wins). */
function setting_once(string $k, callable $make): string
{
    $v = setting($k);
    if ($v === null) {
        db()->prepare('INSERT IGNORE INTO app_settings (k, v) VALUES (?, ?)')->execute([$k, $make()]);
        $v = (string) setting($k);
    }
    return $v;
}

function app_secret(): string
{
    static $s = null;
    return $s ??= setting_once('app_secret', fn() => bin2hex(random_bytes(32)));
}

// ---------------------------------------------------------------------------
// Staff login
// ---------------------------------------------------------------------------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('roomhotel_s');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
    session_start();
}

const REMEMBER_COOKIE = 'roomhotel_r';
const REMEMBER_DAYS = 180;

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires' => $expires, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
}

function load_user(int $id): ?array
{
    $st = db()->prepare('SELECT u.*, v.active AS hotel_active, v.name AS hotel_name
                           FROM users u LEFT JOIN hotels v ON v.id = u.hotel_id WHERE u.id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u || !$u['active'] || ($u['hotel_id'] !== null && !$u['hotel_active'])) return null;
    return $u;
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    start_session();
    $user = null;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if (!$id) $id = (int) restore_remember();
    if ($id) {
        $user = load_user($id);
        if (!$user) logout_user();
    }
    return $user;
}

function login_user(array $user, bool $remember): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['hotel_ctx']);
    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + REMEMBER_DAYS * 86400;
        db()->prepare('INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);
        set_remember_cookie($selector . ':' . $validator, $expires);
    }
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
}

function restore_remember(): ?int
{
    $cookie = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) return null;
    $st = db()->prepare('SELECT user_id, token_hash FROM auth_tokens WHERE selector = ? AND expires_at > NOW()');
    $st->execute([$m[1]]);
    $row = $st->fetch();
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $m[2]))) {
        set_remember_cookie('', time() - 3600);
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['user_id'];
    return (int) $row['user_id'];
}

function logout_user(): void
{
    start_session();
    $cookie = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{24}):/', $cookie, $m)) {
        db()->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$m[1]]);
    }
    set_remember_cookie('', time() - 3600);
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Hotel the user works in; for the superadmin, the hotel chosen in Super › Gestisci. */
function current_hotel_id(): ?int
{
    $u = current_user();
    if (!$u) return null;
    if ($u['role'] === 'superadmin') return isset($_SESSION['hotel_ctx']) ? (int) $_SESSION['hotel_ctx'] : null;
    return (int) $u['hotel_id'];
}

function home_for(array $u): string
{
    if ($u['role'] === 'superadmin') return 'super/';
    return $u['role'] === 'manager' ? 'admin/' : 'staff/';
}

/**
 * Page guard. $roles: who may enter; $json: API (401/403 JSON instead of redirects).
 * Hotel pages (staff, admin) also need a hotel: a superadmin without one goes to Super.
 */
function require_role(array $roles, bool $json = false, bool $needHotel = true): array
{
    $u = current_user();
    if (!$u) {
        if ($json) json_out(['error' => 'login'], 401);
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    if (!in_array($u['role'], $roles, true)) {
        if ($json) json_out(['error' => 'forbidden'], 403);
        redirect(home_for($u));
    }
    if ($needHotel && !current_hotel_id()) {
        if ($json) json_out(['error' => 'hotel'], 403);
        redirect('super/');
    }
    return $u;
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(bool $json = false): void
{
    $t = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf_token(), $t)) {
        if ($json) json_out(['error' => 'csrf'], 400);
        http_response_code(400);
        exit('Sessione scaduta: ricarica la pagina e riprova.');
    }
}

/** One-shot message shown on the next page. */
function flash(?string $msg = null, string $kind = 'ok'): ?array
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------------
// Who follows what: departments and floors (zones)
// ---------------------------------------------------------------------------

function user_zones(array $u): array
{
    $z = json_decode((string) ($u['zones'] ?? ''), true);
    return is_array($z) ? array_values(array_filter($z, 'is_string')) : [];
}

function user_department_ids(array $u): array
{
    $d = json_decode((string) ($u['department_ids'] ?? ''), true);
    return is_array($d) ? array_values(array_unique(array_map('intval', $d))) : [];
}

/**
 * $u follows a request when it is of one of their departments (none chosen = all)
 * and of one of their floors (none chosen = all floors; a room without a floor
 * reaches everyone of the department).
 */
function follows_request(array $u, array $r): bool
{
    $depts = user_department_ids($u);
    if ($depts && !in_array((int) $r['department_id'], $depts, true)) return false;
    $zones = user_zones($u);
    $zone = (string) ($r['zone'] ?? '');
    return !$zones || $zone === '' || in_array($zone, $zones, true);
}

/** Active staff of the hotel that receives requests (staff and managers), with their choices. */
function hotel_followers(int $hotelId): array
{
    static $cache = [];
    if (!isset($cache[$hotelId])) {
        $st = db()->prepare("SELECT id, department_ids, zones FROM users
                              WHERE hotel_id = ? AND active = 1 AND role IN ('staff','manager')");
        $st->execute([$hotelId]);
        $cache[$hotelId] = $st->fetchAll();
    }
    return $cache[$hotelId];
}

/** $u gets this request: they follow it, or nobody does (so no request is ever lost). */
function receives_request(array $u, array $r): bool
{
    if (follows_request($u, $r)) return true;
    foreach (hotel_followers((int) $r['hotel_id']) as $f) {
        if (follows_request($f, $r)) return false;
    }
    return true;
}

/** Saves which departments and floors a user follows (only ones of their hotel). Empty = all. */
function save_following(int $userId, int $hotelId, array $deptIds, array $zones): void
{
    $validDepts = array_column(departments($hotelId, false), 'id');
    $validZones = hotel_zones($hotelId);
    $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds), fn($i) => in_array($i, $validDepts, true))));
    $zones = array_values(array_unique(array_filter(array_map('strval', $zones), fn($z) => in_array($z, $validZones, true))));
    db()->prepare('UPDATE users SET department_ids = ?, zones = ? WHERE id = ?')->execute([
        $deptIds ? json_encode($deptIds) : null,
        $zones ? json_encode($zones, JSON_UNESCAPED_UNICODE) : null,
        $userId,
    ]);
}

/** "Tutti i reparti" or e.g. "Piani · Piano 1, Piano 2", for the staff list and the staff app. */
function following_summary(array $u, array $deptsById): string
{
    $d = [];
    foreach (user_department_ids($u) as $id) {
        if (isset($deptsById[$id])) $d[] = $deptsById[$id]['name'];
    }
    $parts = [];
    if ($d) $parts[] = implode(', ', $d);
    if ($z = user_zones($u)) $parts[] = implode(', ', $z);
    return $parts ? implode(' · ', $parts) : 'Tutti i reparti';
}

// ---------------------------------------------------------------------------
// Hotels, departments, rooms
// ---------------------------------------------------------------------------

function hotel(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** CSS that gives the guest pages the hotel's own colour; empty when it uses the Upgrade palette. */
function hotel_css(array $hotel): string
{
    $c = strtolower((string) $hotel['color']);
    if ($c === BRAND_COLOR || !preg_match('/^#[0-9a-f]{6}$/', $c)) return '';
    return ":root{--brand:$c;--brand-dark:color-mix(in srgb,$c 80%,#000);--brand-soft:color-mix(in srgb,$c 10%,#fff);"
        . "--grad:linear-gradient(135deg,color-mix(in srgb,$c 70%,#fff) 0%,$c 100%)}";
}

function departments(int $hotelId, bool $activeOnly = true): array
{
    $st = db()->prepare('SELECT * FROM departments WHERE hotel_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort, id');
    $st->execute([$hotelId]);
    return $st->fetchAll();
}

function department(int $id, int $hotelId): ?array
{
    $st = db()->prepare('SELECT * FROM departments WHERE id = ? AND hotel_id = ?');
    $st->execute([$id, $hotelId]);
    return $st->fetch() ?: null;
}

/** Is the department taking requests now? No hours set = always. Hours may cross midnight. */
function department_open(array $d, ?int $at = null): bool
{
    if (!$d['open_from'] || !$d['open_to'] || $d['open_from'] === $d['open_to']) return true;
    $now = date('H:i:s', $at ?? time());
    $from = (string) $d['open_from'];
    $to = (string) $d['open_to'];
    return $from < $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
}

/** "7:00–23:00" or empty when always open. */
function department_hours(array $d): string
{
    if (!$d['open_from'] || !$d['open_to'] || $d['open_from'] === $d['open_to']) return '';
    return substr((string) $d['open_from'], 0, 5) . '–' . substr((string) $d['open_to'], 0, 5);
}

/** Floors / wings in use by the hotel's active rooms. */
function hotel_zones(int $hotelId): array
{
    $st = db()->prepare("SELECT DISTINCT zone FROM rooms WHERE hotel_id = ? AND active = 1 AND zone IS NOT NULL AND zone <> ''");
    $st->execute([$hotelId]);
    $z = $st->fetchAll(PDO::FETCH_COLUMN);
    natcasesort($z);
    return array_values($z);
}

function room_by_token(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{6,16}$/', $token)) return null;
    $st = db()->prepare('SELECT r.*, s.code, s.guest_name FROM rooms r
                           LEFT JOIN stays s ON s.id = r.current_stay_id WHERE r.qr_token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function random_token(int $len = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}

function random_code(int $len, ?string $avoid = null): string
{
    $len = max(3, min(6, $len));
    do {
        $code = str_pad((string) random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);
    } while ($code === $avoid);
    return $code;
}

/** Natural order: "Camera 2" before "Camera 10", grouped by floor. */
function sort_rooms(array &$rooms): void
{
    usort($rooms, fn($a, $b) => strnatcasecmp((string) $a['zone'], (string) $b['zone'])
        ?: strnatcasecmp($a['label'], $b['label']));
}

/** "12" -> "Camera 12"; names like "Camera 3" or "Suite Mare" stay as they are. $word: "Camera" in the reader's language. */
function room_name(string $label, string $word = 'Camera'): string
{
    return preg_match('/^\d+[A-Za-z]?$/', $label) ? $word . ' ' . $label : $label;
}

function create_room(int $hotelId, string $label, ?string $zone): int
{
    $pdo = db();
    $v = hotel($hotelId);
    $pdo->prepare('INSERT INTO rooms (hotel_id, label, zone, qr_token) VALUES (?, ?, ?, ?)')
        ->execute([$hotelId, $label, $zone ?: null, random_token()]);
    $roomId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO stays (hotel_id, room_id, code) VALUES (?, ?, ?)')
        ->execute([$hotelId, $roomId, random_code((int) $v['code_length'])]);
    $pdo->prepare('UPDATE rooms SET current_stay_id = ? WHERE id = ?')
        ->execute([(int) $pdo->lastInsertId(), $roomId]);
    return $roomId;
}

/**
 * Check-out: the guests' code stops working, their open requests are closed,
 * "do not disturb" is cleared and the room gets a new code for the next guests.
 */
function rotate_room_code(int $roomId, ?int $userId): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT r.id, r.hotel_id, r.current_stay_id, s.code, v.code_length
                               FROM rooms r JOIN hotels v ON v.id = r.hotel_id
                               LEFT JOIN stays s ON s.id = r.current_stay_id
                              WHERE r.id = ? FOR UPDATE');
        $st->execute([$roomId]);
        $r = $st->fetch();
        if (!$r) throw new RuntimeException('Camera non trovata');
        if ($r['current_stay_id']) {
            $pdo->prepare('UPDATE stays SET closed_at = NOW(), closed_by = ? WHERE id = ?')
                ->execute([$userId, $r['current_stay_id']]);
            $pdo->prepare("UPDATE requests SET status = 'done', done_by = ?, done_at = NOW()
                            WHERE stay_id = ? AND status IN ('scheduled','open','taken')")
                ->execute([$userId, $r['current_stay_id']]);
        }
        $code = random_code((int) $r['code_length'], $r['code']);
        $pdo->prepare('INSERT INTO stays (hotel_id, room_id, code) VALUES (?, ?, ?)')
            ->execute([$r['hotel_id'], $roomId, $code]);
        $pdo->prepare('UPDATE rooms SET current_stay_id = ?, dnd = 0 WHERE id = ?')
            ->execute([(int) $pdo->lastInsertId(), $roomId]);
        $pdo->commit();
        return $code;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Check-in: the name of who is in the room now (shown to the staff, never required). */
function set_guest_name(int $roomId, ?string $name): void
{
    $name = $name === null ? null : (mb_substr(trim($name), 0, 100) ?: null);
    db()->prepare('UPDATE stays s JOIN rooms r ON r.current_stay_id = s.id SET s.guest_name = ? WHERE r.id = ?')
        ->execute([$name, $roomId]);
}

// ---------------------------------------------------------------------------
// Guest: the code typed in the room is remembered in a signed cookie
// ---------------------------------------------------------------------------

function guest_sig(int $roomId, int $stayId): string
{
    return substr(hash_hmac('sha256', "guest|$roomId|$stayId", app_secret()), 0, 32);
}

function guest_cookie_set(int $roomId, int $stayId): void
{
    setcookie('cg' . $roomId, $stayId . '.' . guest_sig($roomId, $stayId), [
        'expires' => time() + GUEST_COOKIE_DAYS * 86400, 'path' => app_path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
}

/** Stay id from the guest's cookie for this room (may be an old, closed one), or null. */
function guest_cookie_stay(int $roomId): ?int
{
    $c = (string) ($_COOKIE['cg' . $roomId] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{32})$/', $c, $m)) return null;
    return hash_equals(guest_sig($roomId, (int) $m[1]), $m[2]) ? (int) $m[1] : null;
}

function code_attempts_blocked(int $roomId, string $ip): bool
{
    $st = db()->prepare('SELECT COUNT(*) AS total, SUM(room_id = ?) AS here FROM code_attempts
                          WHERE ip = ? AND created_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$roomId, $ip]);
    $r = $st->fetch();
    return (int) $r['here'] >= CODE_MAX_FAILS_ROOM || (int) $r['total'] >= CODE_MAX_FAILS_IP;
}

function code_attempt_failed(int $roomId, string $ip): void
{
    db()->prepare('INSERT INTO code_attempts (room_id, ip) VALUES (?, ?)')->execute([$roomId, $ip]);
    if (random_int(1, 50) === 1) db()->exec('DELETE FROM code_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
}

// ---------------------------------------------------------------------------
// Catalogue: request types and menu items (names in several languages)
// ---------------------------------------------------------------------------

/** {"it":..,"en":..} from the DB column. */
function names_decode($json): array
{
    $n = json_decode((string) $json, true);
    return is_array($n) ? array_filter(array_map('strval', $n), fn($s) => $s !== '') : [];
}

/** Name in the reader's language, else English, else Italian, else whatever there is. */
function name_in(array $names, string $lang): string
{
    return $names[$lang] ?? $names['en'] ?? $names['it'] ?? (string) (reset($names) ?: '');
}

function request_types(int $hotelId, bool $activeOnly = true): array
{
    $st = db()->prepare('SELECT t.*, d.active AS dept_active FROM request_types t JOIN departments d ON d.id = t.department_id
                          WHERE t.hotel_id = ?' . ($activeOnly ? ' AND t.active = 1 AND d.active = 1' : '') . ' ORDER BY t.sort, t.id');
    $st->execute([$hotelId]);
    return $st->fetchAll();
}

function menu_items(int $hotelId, ?int $deptId = null, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM menu_items WHERE hotel_id = ?' . ($activeOnly ? ' AND active = 1' : '');
    $args = [$hotelId];
    if ($deptId) { $sql .= ' AND department_id = ?'; $args[] = $deptId; }
    $st = db()->prepare($sql . ' ORDER BY department_id, category, sort, id');
    $st->execute($args);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------------
// Requests
// ---------------------------------------------------------------------------

/**
 * A guest sends a request. A second tap on the same simple request (no items, no time)
 * while it is still open is a reminder (repeat_count), allowed every CALL_REPEAT_SECONDS.
 * Returns ['result' => 'created'|'repeated'|'wait', 'id' => request id].
 */
function request_create(array $room, int $stayId, array $type, ?string $note, ?string $dueAt, array $items): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $simple = !$items && !$dueAt;
        if ($simple) {
            $st = $pdo->prepare("SELECT id, TIMESTAMPDIFF(SECOND, last_call_at, NOW()) AS ago FROM requests
                                  WHERE stay_id = ? AND type_id = ? AND status IN ('open','taken')
                                  ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $st->execute([$stayId, (int) $type['id']]);
            $open = $st->fetch();
            if ($open && (int) $open['ago'] < CALL_REPEAT_SECONDS) {
                $pdo->commit();
                return ['result' => 'wait', 'id' => (int) $open['id']];
            }
            if ($open) {
                $pdo->prepare('UPDATE requests SET repeat_count = repeat_count + 1, last_call_at = NOW(),
                                      note = COALESCE(?, note) WHERE id = ?')
                    ->execute([$note, $open['id']]);
                $pdo->commit();
                return ['result' => 'repeated', 'id' => (int) $open['id']];
            }
        }
        $total = null;
        if ($items) {
            $total = 0.0;
            foreach ($items as $it) if ($it['price'] !== null) $total += $it['price'] * $it['qty'];
        }
        // Scheduled for later: becomes open (and alerts the staff) lead_minutes before due_at (bin/tick.php).
        $scheduled = $dueAt !== null && strtotime($dueAt) - (int) $type['lead_minutes'] * 60 > time() + 60;
        $pdo->prepare('INSERT INTO requests (hotel_id, room_id, stay_id, department_id, type_id, type_name, icon, note, items, total,
                                             due_at, urgent, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$room['hotel_id'], $room['id'], $stayId, $type['department_id'], $type['id'],
                       name_in(names_decode($type['names']), 'it'), $type['icon'], $note,
                       $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null, $total,
                       $dueAt, (int) $type['urgent'], $scheduled ? 'scheduled' : 'open']);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['result' => $scheduled ? 'scheduled' : 'created', 'id' => $id];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Request row with what the notifications need (department, floor). */
function request_row(int $id): ?array
{
    $st = db()->prepare('SELECT q.*, r.zone, r.label FROM requests q JOIN rooms r ON r.id = q.room_id WHERE q.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Push to the hotel's staff and managers who follow this request (see receives_request),
 * or to all of them ($everyone: urgent, or nobody answered in time, see bin/tick.php).
 */
function notify_staff(array $r, bool $everyone = false): void
{
    require_once __DIR__ . '/push.php';
    $st = db()->prepare("SELECT p.*, u.department_ids, u.zones FROM push_subscriptions p JOIN users u ON u.id = p.user_id
                          WHERE u.hotel_id = ? AND u.active = 1 AND u.role IN ('staff','manager')");
    $st->execute([$r['hotel_id']]);
    $everyone = $everyone || !empty($r['urgent']);
    $subs = array_filter($st->fetchAll(), fn($s) => $everyone || receives_request($s, $r));
    push_send(array_values($subs));
}

/** "€ 12,50" for the staff pages. */
function money(?float $v): string
{
    return $v === null ? '' : '€ ' . number_format($v, 2, ',', '.');
}
