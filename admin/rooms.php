<?php
/** Rooms: create (one or many), rename, floor, enable/disable, new code, new QR link. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$user = require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();

function own_room(int $id, int $hotelId): ?array
{
    $st = db()->prepare('SELECT * FROM rooms WHERE id = ? AND hotel_id = ?');
    $st->execute([$id, $hotelId]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $zone = trim((string) ($_POST['zone'] ?? ''));
    $zone = $zone === '' ? null : mb_substr($zone, 0, 60);

    if ($action === 'bulk') {
        $prefix = trim((string) ($_POST['prefix'] ?? ''));
        $from = max(1, (int) ($_POST['from'] ?? 1));
        $to = min($from + 299, max($from, (int) ($_POST['to'] ?? $from)));
        $existing = db()->prepare('SELECT label FROM rooms WHERE hotel_id = ?');
        $existing->execute([$hotelId]);
        $have = array_flip(array_map('mb_strtolower', $existing->fetchAll(PDO::FETCH_COLUMN)));
        $made = 0;
        for ($n = $from; $n <= $to; $n++) {
            $label = mb_substr(trim($prefix . ' ' . $n), 0, 40);
            if (isset($have[mb_strtolower($label)])) continue;
            create_room($hotelId, $label, $zone);
            $made++;
        }
        flash($made ? "Create $made camere." : 'Nessuna camera nuova: esistono già tutte.', $made ? 'ok' : 'err');
    } elseif ($action === 'add') {
        $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 40);
        if ($label !== '') { create_room($hotelId, $label, $zone); flash("Camera $label creata."); }
    } elseif ($r = own_room((int) ($_POST['id'] ?? 0), $hotelId)) {
        if ($action === 'save') {
            $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 40);
            if ($label !== '') {
                db()->prepare('UPDATE rooms SET label = ?, zone = ? WHERE id = ?')->execute([$label, $zone, $r['id']]);
                flash('Camera salvata.');
            }
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE rooms SET active = 1 - active WHERE id = ?')->execute([$r['id']]);
            flash($r['active'] ? "Camera {$r['label']} disattivata: il suo QR non funziona più." : "Camera {$r['label']} riattivata.");
        } elseif ($action === 'code') {
            $code = rotate_room_code((int) $r['id'], (int) $user['id']);
            flash("Camera {$r['label']}: check-out fatto, nuovo codice $code.");
        } elseif ($action === 'token') {
            db()->prepare('UPDATE rooms SET qr_token = ? WHERE id = ?')->execute([random_token(), $r['id']]);
            flash("Camera {$r['label']}: nuovo link QR. Ristampa il suo QR, il vecchio non funziona più.");
        }
    }
    redirect('admin/rooms.php');
}

$st = db()->prepare('SELECT r.*, s.code, s.guest_name FROM rooms r LEFT JOIN stays s ON s.id = r.current_stay_id WHERE r.hotel_id = ?');
$st->execute([$hotelId]);
$rooms = $st->fetchAll();
sort_rooms($rooms);
$zones = array_values(array_unique(array_filter(array_column($rooms, 'zone'))));

page_head('Camere');
admin_nav('rooms');
?>
<main class="wrap">
  <datalist id="zones"><?php foreach ($zones as $z): ?><option value="<?= h($z) ?>"><?php endforeach; ?></datalist>
  <div class="grid2">
    <form class="card form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
      <h2>Crea più camere</h2>
      <div class="row">
        <label>Prefisso (facoltativo)<input name="prefix" value="" maxlength="30" placeholder="es. Camera"></label>
        <label>da<input type="number" name="from" value="101" min="1"></label>
        <label>a<input type="number" name="to" value="110" min="1"></label>
      </div>
      <label>Piano o ala (facoltativo: Piano 1, Ala Mare…)<input name="zone" list="zones" maxlength="60"></label>
      <button class="btn primary">Crea</button>
      <p class="small muted">Esempio: da 101 a 110 con piano "Piano 1", poi da 201 a 210 con "Piano 2".</p>
    </form>
    <form class="card form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <h2>Aggiungi una camera</h2>
      <label>Nome<input name="label" required maxlength="40" placeholder="es. 12, Suite Mare, Appartamento 3"></label>
      <label>Piano o ala<input name="zone" list="zones" maxlength="60"></label>
      <button class="btn">Aggiungi</button>
    </form>
  </div>

  <div class="card">
    <h2>Camere (<?= count($rooms) ?>)</h2>
    <?php if (!$rooms): ?><p class="muted">Ancora nessuna camera.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Nome</th><th>Piano</th><th>Codice attuale</th><th>Ospite</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rooms as $r): ?>
        <tr class="<?= $r['active'] ? '' : 'off' ?>">
          <td colspan="2">
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input name="label" value="<?= h($r['label']) ?>" maxlength="40" required aria-label="Nome">
              <input name="zone" value="<?= h($r['zone']) ?>" list="zones" maxlength="60" placeholder="Piano" aria-label="Piano">
              <button class="btn small">Salva</button>
            </form>
          </td>
          <td><span class="code"><?= h($r['code']) ?></span></td>
          <td><?= h($r['guest_name'] ?: '') ?><?= $r['dnd'] ? ' <span class="tag">🔕 non disturbare</span>' : '' ?></td>
          <td class="actions">
            <a class="btn small" href="<?= h(room_url($r['qr_token'])) ?>" target="_blank">Apri</a>
            <a class="btn small" href="<?= h(app_path('admin/qr.php?room=' . $r['id'])) ?>">QR</a>
            <?php foreach (['code' => 'Check-out / nuovo codice', 'toggle' => $r['active'] ? 'Disattiva' : 'Attiva', 'token' => 'Nuovo link QR'] as $a => $label): ?>
              <form method="post" class="inline"<?= $a === 'token' ? ' onsubmit="return confirm(\'Il QR stampato di questa camera smetterà di funzionare. Continuare?\')"' : ($a === 'code' ? ' onsubmit="return confirm(\'Chiudere il soggiorno e generare un nuovo codice?\')"' : '') ?>>
                <?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn small<?= $a === 'token' ? ' ghost' : '' ?>"><?= h($label) ?></button>
              </form>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</main>
<?php
page_foot();
