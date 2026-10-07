<?php
/**
 * Staff app API.
 *   GET  ?a=feed           open and scheduled requests, rooms with their current code and guest
 *   GET  ?a=push_summary   what to show in a push notification (service worker)
 *   GET  ?a=follow         departments and floors, and which ones this user follows
 *   POST take {id} | done {id} | reply {id, text} | checkin {room_id, guest_name} | checkout {room_id}
 *        set_follow {departments, zones} | push_subscribe {endpoint, keys} | push_unsubscribe {endpoint} | push_test
 * POSTs need the X-CSRF header.
 */
require __DIR__ . '/../includes/app.php';

$user = require_role(['staff', 'manager', 'superadmin'], true);
$hotelId = (int) current_hotel_id();
$uid = (int) $user['id'];
$action = (string) ($_GET['a'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'feed') json_out(feed($hotelId, $user));
    if ($action === 'push_summary') json_out(push_summary($hotelId, $user));
    if ($action === 'follow') {
        $depts = array_map(fn($d) => ['id' => (int) $d['id'], 'name' => $d['name'], 'icon' => $d['icon']], departments($hotelId));
        json_out(['departments' => $depts, 'zones' => hotel_zones($hotelId),
                  'my_departments' => user_department_ids($user), 'my_zones' => user_zones($user)]);
    }
    json_out(['error' => 'action'], 400);
}

csrf_check(true);
$in = input();
$action = (string) ($in['a'] ?? $action);

switch ($action) {
    case 'take':
        db()->prepare("UPDATE requests SET status = 'taken', taken_by = ?, taken_at = NOW()
                        WHERE id = ? AND hotel_id = ? AND status = 'open'")
            ->execute([$uid, (int) ($in['id'] ?? 0), $hotelId]);
        json_out(feed($hotelId, $user));

    case 'done':
        db()->prepare("UPDATE requests SET status = 'done', done_by = ?, done_at = NOW(),
                              taken_by = COALESCE(taken_by, ?), taken_at = COALESCE(taken_at, NOW())
                        WHERE id = ? AND hotel_id = ? AND status IN ('scheduled','open','taken')")
            ->execute([$uid, $uid, (int) ($in['id'] ?? 0), $hotelId]);
        json_out(feed($hotelId, $user));

    case 'reply':
        // A short message the guest sees under the request ("Arriviamo tra 10 minuti"). Also takes it.
        $text = mb_substr(trim((string) ($in['text'] ?? '')), 0, 300);
        if ($text === '') json_out(['error' => 'text'], 400);
        db()->prepare("UPDATE requests SET reply = ?, replied_at = NOW(),
                              status = IF(status = 'open', 'taken', status),
                              taken_by = COALESCE(taken_by, ?), taken_at = COALESCE(taken_at, NOW())
                        WHERE id = ? AND hotel_id = ? AND status IN ('scheduled','open','taken')")
            ->execute([$text, $uid, (int) ($in['id'] ?? 0), $hotelId]);
        json_out(feed($hotelId, $user));

    case 'checkin':
        own_room((int) ($in['room_id'] ?? 0), $hotelId);
        set_guest_name((int) $in['room_id'], (string) ($in['guest_name'] ?? ''));
        json_out(feed($hotelId, $user));

    case 'checkout':
        own_room((int) ($in['room_id'] ?? 0), $hotelId);
        rotate_room_code((int) $in['room_id'], $uid);
        json_out(feed($hotelId, $user));

    case 'set_follow':
        save_following($uid, $hotelId, (array) ($in['departments'] ?? []), (array) ($in['zones'] ?? []));
        json_out(feed($hotelId, load_user($uid)));

    case 'push_subscribe':
        $endpoint = (string) ($in['endpoint'] ?? '');
        $keys = (array) ($in['keys'] ?? []);
        if (!preg_match('#^https://#', $endpoint) || empty($keys['p256dh']) || empty($keys['auth'])) json_out(['error' => 'subscription'], 400);
        db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
                       VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth),
                                               user_agent = VALUES(user_agent)')
            ->execute([$uid, $endpoint, hash('sha256', $endpoint), (string) $keys['p256dh'], (string) $keys['auth'],
                       substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        json_out(['ok' => true]);

    case 'push_unsubscribe':
        db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?')
            ->execute([hash('sha256', (string) ($in['endpoint'] ?? '')), $uid]);
        json_out(['ok' => true]);

    case 'push_test':
        require_once __DIR__ . '/../includes/push.php';
        $st = db()->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
        $st->execute([$uid]);
        $_SESSION['push_test'] = time();
        json_out(['ok' => true] + push_send($st->fetchAll()));
}

json_out(['error' => 'action'], 400);

function own_room(int $roomId, int $hotelId): void
{
    $st = db()->prepare('SELECT id FROM rooms WHERE id = ? AND hotel_id = ?');
    $st->execute([$roomId, $hotelId]);
    if ($st->fetchColumn() === false) json_out(['error' => 'room'], 404);
}

/** Active rooms of the hotel (with the current code and guest), in natural order. */
function hotel_rooms(int $hotelId): array
{
    $st = db()->prepare('SELECT r.id, r.hotel_id, r.label, r.zone, r.dnd, s.code, s.guest_name,
                                TIMESTAMPDIFF(MINUTE, s.opened_at, NOW()) AS code_age
                           FROM rooms r LEFT JOIN stays s ON s.id = r.current_stay_id
                          WHERE r.hotel_id = ? AND r.active = 1');
    $st->execute([$hotelId]);
    $rows = $st->fetchAll();
    sort_rooms($rows);
    return $rows;
}

function feed(int $hotelId, array $user): array
{
    $deptsById = [];
    foreach (departments($hotelId) as $d) $deptsById[(int) $d['id']] = $d;
    $zones = [];
    $rooms = [];
    foreach (hotel_rooms($hotelId) as $r) {
        if ((string) $r['zone'] !== '') $zones[$r['zone']] = true;
        $rooms[] = ['id' => (int) $r['id'], 'label' => $r['label'], 'zone' => $r['zone'], 'code' => $r['code'],
                    'guest' => $r['guest_name'], 'dnd' => (bool) $r['dnd'], 'code_age' => (int) $r['code_age']];
    }

    $st = db()->prepare("SELECT q.*, r.label, r.zone, r.dnd, s.guest_name, u.name AS taken_name,
                                TIMESTAMPDIFF(SECOND, q.created_at, NOW()) AS age,
                                TIMESTAMPDIFF(SECOND, GREATEST(q.last_call_at, COALESCE(q.reminded_at, q.last_call_at),
                                                               COALESCE(q.escalated_at, q.last_call_at)), NOW()) AS alert_age,
                                TIMESTAMPDIFF(SECOND, NOW(), q.due_at) AS due_in
                           FROM requests q JOIN rooms r ON r.id = q.room_id
                           LEFT JOIN stays s ON s.id = q.stay_id LEFT JOIN users u ON u.id = q.taken_by
                          WHERE q.hotel_id = ? AND q.status IN ('scheduled','open','taken')
                          ORDER BY q.urgent DESC, COALESCE(q.due_at, q.created_at)");
    $st->execute([$hotelId]);
    $requests = [];
    $scheduled = [];
    foreach ($st->fetchAll() as $q) {
        // Other people's departments and floors are hidden, unless urgent or nobody answered and everyone was alerted.
        $escalated = $q['status'] === 'open' && $q['escalated_at'] !== null;
        if (!$q['urgent'] && !$escalated && !receives_request($user, $q)) continue;
        $d = $deptsById[(int) $q['department_id']] ?? null;
        $row = [
            'id' => (int) $q['id'], 'room_id' => (int) $q['room_id'], 'label' => $q['label'], 'zone' => $q['zone'],
            'guest' => $q['guest_name'], 'dnd' => (bool) $q['dnd'],
            'dept' => $d ? $d['name'] : '', 'dept_icon' => $d ? $d['icon'] : '',
            'what' => $q['type_name'], 'icon' => $q['icon'], 'note' => $q['note'],
            'items' => json_decode((string) $q['items'], true) ?: [], 'total' => $q['total'] === null ? null : (float) $q['total'],
            'due' => $q['due_at'] ? date('Y-m-d H:i', strtotime($q['due_at'])) : null, 'due_in' => $q['due_in'] === null ? null : (int) $q['due_in'],
            'urgent' => (bool) $q['urgent'], 'status' => $q['status'], 'reply' => $q['reply'],
            'repeat' => (int) $q['repeat_count'], 'age' => (int) $q['age'], 'alert_age' => (int) $q['alert_age'],
            'escalated' => $escalated,
            'bump' => (int) $q['repeat_count'] + (int) $q['alerts'],   // grows at every reminder: the app rings again
            'taken_name' => $q['taken_name'], 'mine' => (int) $q['taken_by'] === (int) $user['id'],
        ];
        if ($q['status'] === 'scheduled') $scheduled[] = $row; else $requests[] = $row;
    }
    return ['requests' => $requests, 'scheduled' => $scheduled, 'rooms' => $rooms, 'zones' => array_keys($zones),
            'departments' => array_values(array_map(fn($d) => ['id' => (int) $d['id'], 'name' => $d['name'], 'icon' => $d['icon']], $deptsById)),
            'following' => following_summary($user, $deptsById)];
}

/** Text of the notification: the newest open request, or how many are waiting. */
function push_summary(int $hotelId, array $user): array
{
    $open = array_values(array_filter(feed($hotelId, $user)['requests'], fn($c) => $c['status'] === 'open'));
    if (!$open) {
        start_session();
        if (time() - (int) ($_SESSION['push_test'] ?? 0) < 120) {
            return ['title' => 'Notifiche attive ✓', 'body' => 'Riceverai qui le richieste delle camere.', 'tag' => 'test'];
        }
        return ['title' => '', 'body' => '', 'tag' => ''];
    }
    // The request that has just been (re)notified: urgent and unanswered ones first.
    usort($open, fn($a, $b) => [$b['urgent'], $b['escalated'], $a['alert_age']] <=> [$a['urgent'], $a['escalated'], $b['alert_age']]);
    $c = $open[0];
    $wait = $c['age'] >= 60 ? ' · in attesa da ' . intdiv($c['age'], 60) . ' min' : '';
    $title = ($c['urgent'] ? '🚨 URGENTE · ' : ($c['escalated'] ? '⚠️ Nessuno ha risposto · ' : ''))
        . room_name($c['label']) . ' · ' . $c['what'] . ($c['repeat'] ? ' (sollecito)' : '') . $wait;
    $body = count($open) > 1
        ? count($open) . ' richieste in attesa: ' . implode(', ', array_unique(array_map(fn($x) => room_name($x['label']), $open)))
        : trim(($c['dept'] ? $c['dept'] . ' · ' : '') . ($c['note'] ?: ($c['zone'] ?: 'Tocca per aprire')));
    return ['title' => $title, 'body' => $body, 'tag' => 'requests'];
}
