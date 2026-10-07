<?php
/**
 * Guest API (room QR page). POST JSON {a, k, ...}:
 *   verify  {code}                                   -> checks the room code, sets the guest cookie
 *   request {type_id, note?, when?, time?, items?}   -> sends a request (when: now|today|tomorrow, time: HH:MM,
 *                                                        items: [{id, qty}])
 *   cancel  {id}                                     -> withdraws a request not yet taken
 *   dnd     {on}                                     -> "do not disturb" on/off
 *   status                                           -> this guest's requests, DND, which departments are open
 * The guest cookie is SameSite=Lax, so other sites cannot send requests on a guest's behalf.
 */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/guest_i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);

$in = input();
$room = room_by_token((string) ($in['k'] ?? ''));
$hotel = $room ? hotel((int) $room['hotel_id']) : null;
if (!$room || !$room['active'] || !$hotel || !$hotel['active']) json_out(['error' => 'not_found'], 404);

$roomId = (int) $room['id'];
$current = (int) $room['current_stay_id'];
$action = (string) ($in['a'] ?? '');
$lang = guest_lang();

if ($action === 'verify') {
    $ip = client_ip();
    if (code_attempts_blocked($roomId, $ip)) json_out(['error' => 'too_many'], 429);
    $code = preg_replace('/\D/', '', (string) ($in['code'] ?? ''));
    if ($code === '' || !$room['code'] || !hash_equals((string) $room['code'], $code)) {
        code_attempt_failed($roomId, $ip);
        json_out(['error' => 'wrong_code'], 400);
    }
    guest_cookie_set($roomId, $current);
    json_out(['ok' => true] + status_payload($room, $current, $lang));
}

// Every other action needs the code of the current guests.
$sid = guest_cookie_stay($roomId);
if ($sid === null) json_out(['error' => 'code'], 403);
if ($sid !== $current) json_out(['error' => 'expired'], 403);

if ($action === 'request') {
    $st = db()->prepare('SELECT t.* FROM request_types t JOIN departments d ON d.id = t.department_id
                          WHERE t.id = ? AND t.hotel_id = ? AND t.active = 1 AND d.active = 1');
    $st->execute([(int) ($in['type_id'] ?? 0), $hotel['id']]);
    $type = $st->fetch();
    if (!$type) json_out(['error' => 'type'], 400);
    $dept = department((int) $type['department_id'], (int) $hotel['id']);
    if (!department_open($dept)) json_out(['error' => 'closed', 'hours' => department_hours($dept)], 400);

    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 500) ?: null;

    $dueAt = null;
    if ($type['ask_time'] && ($in['when'] ?? 'now') !== 'now') {
        $time = (string) ($in['time'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) json_out(['error' => 'pick_time'], 400);
        $day = ($in['when'] ?? '') === 'tomorrow' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
        $dueAt = "$day $time:00";
        if (strtotime($dueAt) < time() - 300) json_out(['error' => 'pick_time'], 400);
    }

    $items = [];
    if ($type['ask_items']) {
        $wanted = [];
        foreach ((array) ($in['items'] ?? []) as $it) {
            $id = (int) ($it['id'] ?? 0);
            $qty = max(1, min(20, (int) ($it['qty'] ?? 0)));
            if ($id && (int) ($it['qty'] ?? 0) > 0) $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
        }
        foreach (menu_items((int) $hotel['id'], (int) $type['department_id']) as $m) {
            if (!isset($wanted[(int) $m['id']])) continue;
            $names = names_decode($m['names']);
            $items[] = ['id' => (int) $m['id'], 'name' => name_in($names, 'it'), 'name_g' => name_in($names, $lang),
                        'qty' => $wanted[(int) $m['id']], 'price' => $m['price'] === null ? null : (float) $m['price']];
        }
        if (!$items) json_out(['error' => 'pick_items'], 400);
    }

    $res = request_create($room, $current, $type, $note, $dueAt, $items);
    $data = ['ok' => true, 'result' => $res['result']] + status_payload($room, $current, $lang);
    if ($res['result'] === 'wait' || $res['result'] === 'scheduled') json_out($data);
    $r = request_row($res['id']);
    json_out_then($data, fn() => notify_staff($r));
}

if ($action === 'cancel') {
    db()->prepare("UPDATE requests SET status = 'cancelled', done_at = NOW() WHERE id = ? AND stay_id = ? AND status IN ('scheduled','open')")
        ->execute([(int) ($in['id'] ?? 0), $current]);
    json_out(['ok' => true] + status_payload($room, $current, $lang));
}

if ($action === 'dnd') {
    db()->prepare('UPDATE rooms SET dnd = ? WHERE id = ?')->execute([empty($in['on']) ? 0 : 1, $roomId]);
    json_out(['ok' => true] + status_payload($room, $current, $lang));
}

if ($action === 'status') {
    json_out(['ok' => true] + status_payload($room, $current, $lang));
}

json_out(['error' => 'action'], 400);

/** The guest's requests (open, scheduled, taken and the ones done in the last 10 minutes), DND, open departments. */
function status_payload(array $room, int $stayId, string $lang): array
{
    $st = db()->prepare("SELECT q.id, q.type_name, q.icon, q.status, q.note, q.items, q.total, q.due_at, q.reply, q.urgent,
                                TIMESTAMPDIFF(SECOND, q.last_call_at, NOW()) AS ago, u.name AS staff, t.names
                           FROM requests q LEFT JOIN users u ON u.id = q.taken_by LEFT JOIN request_types t ON t.id = q.type_id
                          WHERE q.stay_id = ? AND (q.status IN ('scheduled','open','taken') OR (q.status = 'done' AND q.done_at > NOW() - INTERVAL 10 MINUTE))
                          ORDER BY q.status = 'done', COALESCE(q.due_at, q.created_at)");
    $st->execute([$stayId]);
    $list = [];
    foreach ($st->fetchAll() as $q) {
        $items = json_decode((string) $q['items'], true) ?: [];
        $due = null;
        if ($q['due_at']) {
            $due = date('H:i', strtotime($q['due_at']));
            if (date('Y-m-d', strtotime($q['due_at'])) !== date('Y-m-d')) $due = gt('tomorrow') . ' ' . $due;
        }
        $list[] = [
            'id' => (int) $q['id'], 'icon' => $q['icon'],
            'name' => $q['names'] ? name_in(names_decode($q['names']), $lang) : $q['type_name'],
            'status' => $q['status'], 'ago' => (int) $q['ago'], 'note' => $q['note'], 'reply' => $q['reply'],
            'urgent' => (bool) $q['urgent'], 'due' => $due,
            'items' => array_map(fn($i) => $i['qty'] . '× ' . ($i['name_g'] ?? $i['name']), $items),
            'total' => $q['total'] === null ? null : (float) $q['total'],
            // Only the first name of who took the request ("Mario"), never the surname.
            'staff' => $q['status'] === 'taken' && $q['staff'] ? strtok(trim($q['staff']), ' ') : null,
        ];
    }
    $st = db()->prepare('SELECT dnd FROM rooms WHERE id = ?');
    $st->execute([$room['id']]);
    $open = [];
    foreach (departments((int) $room['hotel_id']) as $d) $open[(int) $d['id']] = department_open($d);
    return ['requests' => $list, 'dnd' => (bool) $st->fetchColumn(), 'open' => $open];
}
