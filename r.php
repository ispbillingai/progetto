<?php
/**
 * Guest page, opened from the room QR (/r/<token>). The QR never changes:
 * the guest types the code given at check-in, valid until check-out.
 */
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/guest_i18n.php';
require __DIR__ . '/includes/catalog.php';

$token = (string) ($_GET['k'] ?? '');
$room = room_by_token($token);
$hotel = $room ? hotel((int) $room['hotel_id']) : null;
$lang = guest_lang();

header('Cache-Control: no-store');

if (!$room || !$room['active'] || !$hotel || !$hotel['active']) {
    http_response_code(404);
    ?><!doctype html><html lang="<?= h($lang) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>RoomHotel</title><link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>"></head>
<body class="guest center"><div class="card"><p><?= h(gt('not_found')) ?></p></div></body></html><?php
    exit;
}

$sid = guest_cookie_stay((int) $room['id']);
$verified = $sid !== null && $sid === (int) $room['current_stay_id'];
$expired = $sid !== null && !$verified;

// Departments and what the guest can ask each one, in the guest's language.
$depts = [];
foreach (departments((int) $hotel['id']) as $d) {
    $depts[(int) $d['id']] = [
        'id' => (int) $d['id'], 'kind' => $d['kind'], 'name' => guest_department_name($d), 'icon' => $d['icon'],
        'hours' => department_hours($d), 'open' => department_open($d), 'types' => [], 'items' => [],
    ];
}
$fallbackType = null;   // where a spoken request goes when no button matches: the first plain reception button
foreach (request_types((int) $hotel['id']) as $t) {
    if (!isset($depts[(int) $t['department_id']])) continue;
    $names = names_decode($t['names']);
    $kw = trim((string) ($t['keywords'] ?? '')) !== '' ? $t['keywords'] : default_keywords($names['it'] ?? '');
    $kw = array_values(array_filter(array_map('trim', explode(',', mb_strtolower($kw)))));
    $depts[(int) $t['department_id']]['types'][] = [
        'id' => (int) $t['id'], 'icon' => $t['icon'], 'name' => name_in($names, $lang),
        'hint' => $lang === 'it' ? $t['hint'] : null,
        'time' => (bool) $t['ask_time'], 'items' => (bool) $t['ask_items'], 'urgent' => (bool) $t['urgent'],
        'kw' => $kw, 'names' => array_values($names),
    ];
    if ($fallbackType === null && $depts[(int) $t['department_id']]['kind'] === 'reception'
        && !$t['ask_time'] && !$t['ask_items'] && !$t['urgent']) $fallbackType = (int) $t['id'];
}
foreach (menu_items((int) $hotel['id']) as $it) {
    if (!isset($depts[(int) $it['department_id']])) continue;
    $depts[(int) $it['department_id']]['items'][] = [
        'id' => (int) $it['id'], 'name' => name_in(names_decode($it['names']), $lang), 'category' => $it['category'],
        'price' => $it['price'] === null ? null : (float) $it['price'],
    ];
}
$depts = array_values(array_filter($depts, fn($d) => $d['types']));

$config = [
    'api'        => app_path('api/guest.php'),
    'token'      => $token,
    'verified'   => $verified,
    'codeLength' => (int) $hotel['code_length'],
    'depts'      => $depts,
    'dnd'        => (bool) $room['dnd'],
    'lang'       => $lang,
    'speechLang' => ['it' => 'it-IT', 'en' => 'en-US', 'de' => 'de-DE', 'fr' => 'fr-FR', 'es' => 'es-ES'][$lang],
    'fallback'   => $fallbackType,
    't'          => guest_texts(),
];

/** Department name for the guest: the default kinds are translated, custom names stay as typed. */
function guest_department_name(array $d): string
{
    $defaults = ['reception' => 'Reception', 'housekeeping' => 'Piani', 'maintenance' => 'Manutenzione', 'bar' => 'Bar', 'kitchen' => 'Cucina'];
    $tr = [
        'reception'    => ['it' => 'Reception', 'en' => 'Reception', 'de' => 'Rezeption', 'fr' => 'Réception', 'es' => 'Recepción'],
        'housekeeping' => ['it' => 'Servizio ai piani', 'en' => 'Housekeeping', 'de' => 'Zimmerservice (Reinigung)', 'fr' => 'Service d\'étage', 'es' => 'Servicio de pisos'],
        'maintenance'  => ['it' => 'Manutenzione', 'en' => 'Maintenance', 'de' => 'Technik', 'fr' => 'Maintenance', 'es' => 'Mantenimiento'],
        'bar'          => ['it' => 'Bar', 'en' => 'Bar', 'de' => 'Bar', 'fr' => 'Bar', 'es' => 'Bar'],
        'kitchen'      => ['it' => 'Cucina', 'en' => 'Kitchen', 'de' => 'Küche', 'fr' => 'Cuisine', 'es' => 'Cocina'],
    ];
    if (isset($defaults[$d['kind']]) && $d['name'] === $defaults[$d['kind']]) return $tr[$d['kind']][guest_lang()] ?? $d['name'];
    return $d['name'];
}
?><!doctype html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= h($hotel['color']) ?>">
<title><?= h($hotel['name']) ?> · <?= h(room_name($room['label'], gt('room'))) ?></title>
<link rel="icon" href="<?= h(app_path('icon.php?s=192')) ?>">
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
<?php if ($css = hotel_css($hotel)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="guest">
<main class="guest-main">
  <header class="guest-head">
    <?php if ($hotel['logo_file']): ?>
      <img class="guest-logo" src="<?= h(app_path('file.php?v=' . $hotel['id'] . '&f=logo&h=' . substr(md5($hotel['logo_file']), 0, 8))) ?>" alt="<?= h($hotel['name']) ?>">
    <?php else: ?>
      <h1 class="guest-name"><?= h($hotel['name']) ?></h1>
    <?php endif; ?>
    <div class="guest-table"><strong><?= h(room_name($room['label'], gt('room'))) ?></strong></div>
    <?php if ($hotel['welcome_text']): ?><p class="guest-welcome"><?= nl2br(h($hotel['welcome_text'])) ?></p><?php endif; ?>
  </header>

  <section id="codeBox" class="card guest-card"<?= $verified ? ' hidden' : '' ?>>
    <?php if ($expired): ?><p class="notice" id="expiredMsg"><?= h(gt('expired')) ?></p><?php endif; ?>
    <form id="codeForm" autocomplete="off">
      <label for="code" class="code-label"><?= h(gt('enter_code')) ?></label>
      <input id="code" class="code-input" inputmode="numeric" pattern="[0-9]*" maxlength="<?= (int) $hotel['code_length'] ?>"
             autocomplete="one-time-code" placeholder="<?= str_repeat('•', (int) $hotel['code_length']) ?>" required>
      <p class="muted small"><?= h(gt('code_hint')) ?></p>
      <p class="err-text" id="codeErr" hidden></p>
      <button class="btn primary big block" id="codeBtn"><?= h(gt('confirm')) ?></button>
    </form>
  </section>

  <section id="actions"<?= $verified ? '' : ' hidden' ?>>
    <div id="status" class="guest-status" aria-live="polite"></div>
    <button class="btn speak" id="speakBtn" type="button" hidden><span class="ico">🎤</span><span><strong><?= h(gt('speak')) ?></strong><small><?= h(gt('speak_hint')) ?></small></span></button>
    <div id="deptList"></div>
    <button class="btn dnd" id="dndBtn" type="button"><span class="ico">🔕</span><span id="dndText"><?= h(gt('dnd')) ?></span></button>
    <?php if ($hotel['info_text']): ?>
      <section class="card guest-info"><h2><?= h(gt('info')) ?></h2><p><?= nl2br(h($hotel['info_text'])) ?></p></section>
    <?php endif; ?>
  </section>

  <p class="toast" id="toast" hidden></p>

  <footer class="langs">
    <?php foreach (GUEST_LANGS as $code => $name): ?>
      <a href="?k=<?= h(rawurlencode($token)) ?>&amp;lang=<?= $code ?>"<?= $code === $lang ? ' class="on"' : '' ?>><?= h($name) ?></a>
    <?php endforeach; ?>
  </footer>
  <?= powered_by() ?>
</main>

<dialog id="reqDialog" class="sheet req-sheet">
  <form method="dialog" id="reqForm">
    <h2 id="reqTitle"></h2>
    <p class="notice" id="reqUrgent" hidden><?= h(gt('urgent_q')) ?></p>
    <div id="reqItems" class="req-items" hidden></div>
    <div id="reqTime" class="req-time" hidden>
      <label class="req-label"><?= h(gt('time_label')) ?></label>
      <div class="chips time-chips">
        <label class="chip"><input type="radio" name="when" value="now" checked> <?= h(gt('now')) ?></label>
        <label class="chip"><input type="radio" name="when" value="today"> <?= h(gt('today')) ?></label>
        <label class="chip"><input type="radio" name="when" value="tomorrow"> <?= h(gt('tomorrow')) ?></label>
      </div>
      <input type="time" id="reqClock" step="300">
    </div>
    <label class="req-label" for="reqNote" id="reqNoteLabel"><?= h(gt('note_label')) ?></label>
    <div class="note-wrap"><textarea id="reqNote" rows="2" maxlength="500" placeholder="<?= h(gt('note_ph')) ?>"></textarea>
      <button class="mic-btn" id="noteMic" type="button" hidden aria-label="<?= h(gt('speak')) ?>">🎤</button></div>
    <p class="err-text" id="reqErr" hidden></p>
    <div class="row-btns">
      <button class="btn primary big" id="reqSend" value="send"><?= h(gt('send')) ?></button>
      <button class="btn ghost" value="cancel" formnovalidate><?= h(gt('cancel')) ?></button>
    </div>
  </form>
</dialog>

<script>window.GUEST = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(asset('assets/guest.js')) ?>"></script>
</body>
</html>
