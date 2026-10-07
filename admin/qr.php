<?php
/** Printable QR cards (A4, 6 per page), one per room. ?room=ID for one, ?png=ID to download the image. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();
$hotel = hotel($hotelId);

function qr_out(string $text, string $type): ?string
{
    if (!is_executable('/usr/bin/qrencode')) return null;
    $out = shell_exec('/usr/bin/qrencode -t ' . $type . ' -s 12 -m 2 -l M -o - ' . escapeshellarg($text));
    return is_string($out) && $out !== '' ? $out : null;
}

if (isset($_GET['png'])) {
    $st = db()->prepare('SELECT * FROM rooms WHERE id = ? AND hotel_id = ?');
    $st->execute([(int) $_GET['png'], $hotelId]);
    $r = $st->fetch();
    $png = $r ? qr_out(room_url($r['qr_token']), 'PNG') : null;
    if (!$png) { http_response_code(404); exit('QR non disponibile.'); }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="qr-' . preg_replace('/[^A-Za-z0-9]+/', '-', $r['label']) . '.png"');
    echo $png;
    exit;
}

$sql = 'SELECT * FROM rooms WHERE hotel_id = ? AND active = 1';
$args = [$hotelId];
if (isset($_GET['room'])) { $sql .= ' AND id = ?'; $args[] = (int) $_GET['room']; }
if (($_GET['zone'] ?? '') !== '') { $sql .= ' AND zone = ?'; $args[] = $_GET['zone']; }
$st = db()->prepare($sql);
$st->execute($args);
$rooms = $st->fetchAll();
sort_rooms($rooms);
$zones = hotel_zones($hotelId);

page_head('QR da stampare', 'qr-print');
admin_nav('qr');
?>
<main class="wrap">
  <div class="card no-print qr-tools">
    <form method="get" class="inline">
      <label>Piano
        <select name="zone" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <?php foreach ($zones as $z): ?><option<?= ($_GET['zone'] ?? '') === $z ? ' selected' : '' ?>><?= h($z) ?></option><?php endforeach; ?>
        </select>
      </label>
    </form>
    <button class="btn primary" onclick="window.print()">Stampa</button>
    <span class="small muted">Il QR di ogni camera non cambia mai: cambia solo il codice dato al check-in.</span>
  </div>
  <?php if (!$rooms): ?><p class="card">Nessuna camera attiva. <a href="<?= h(app_path('admin/rooms.php')) ?>">Crea le camere</a>.</p><?php endif; ?>
  <div class="qr-sheet">
    <?php foreach ($rooms as $r): $url = room_url($r['qr_token']); $svg = qr_out($url, 'SVG'); ?>
      <div class="qr-card" style="--brand:<?= h($hotel['color']) ?>">
        <div class="qr-venue"><?= h($hotel['name']) ?></div>
        <div class="qr-label"><?= h(room_name($r['label'])) ?></div>
        <div class="qr-img"><?= $svg ? preg_replace('/^.*?(<svg)/s', '$1', $svg) : '<p>qrencode non installato</p>' ?></div>
        <div class="qr-text">Inquadra per contattare reception, servizio in camera e assistenza</div>
        <div class="qr-text en">Scan to reach reception, room service and assistance</div>
        <img class="qr-brand" src="<?= h(asset('assets/brand/upgrade-logo.png')) ?>" alt="Upgrade" width="70" height="23">
        <a class="no-print small" href="?png=<?= $r['id'] ?>">Scarica PNG</a>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php
page_foot();
