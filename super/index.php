<?php
/** Superadmin: every hotel; create a hotel (with its manager, rooms and default catalogue) in one form. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/catalog.php';

$me = require_role(['superadmin'], false, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'enter' && hotel($id)) {
        $_SESSION['hotel_ctx'] = $id;
        redirect('admin/');
    } elseif ($action === 'toggle' && ($v = hotel($id))) {
        db()->prepare('UPDATE hotels SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash($v['active'] ? "{$v['name']} disattivato: QR e accessi bloccati." : "{$v['name']} riattivato.");
    } elseif ($action === 'create') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $mName = mb_substr(trim((string) ($_POST['manager_name'] ?? '')), 0, 100);
        $mUser = mb_strtolower(trim((string) ($_POST['manager_username'] ?? '')));
        $mPass = (string) ($_POST['manager_password'] ?? '');
        $from = max(1, (int) ($_POST['from'] ?? 101));
        $to = min($from + 299, max($from - 1, (int) ($_POST['to'] ?? 0)));
        $exists = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $exists->execute([$mUser]);
        if ($name === '' || $mName === '') {
            flash('Nome dell\'hotel e del responsabile sono obbligatori.', 'err');
        } elseif (!preg_match('/^[a-z0-9._-]{3,60}$/', $mUser) || $exists->fetchColumn()) {
            flash('Utente del responsabile non valido o già esistente.', 'err');
        } elseif (strlen($mPass) < 6) {
            flash('La password del responsabile deve avere almeno 6 caratteri.', 'err');
        } else {
            $pdo = db();
            $pdo->prepare('INSERT INTO hotels (name) VALUES (?)')->execute([$name]);
            $hotelId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO users (hotel_id, role, name, username, password_hash) VALUES (?, 'manager', ?, ?, ?)")
                ->execute([$hotelId, $mName, $mUser, password_hash($mPass, PASSWORD_DEFAULT)]);
            seed_hotel_defaults($hotelId);
            $n = 0;
            for ($r = $from; $r <= $to; $r++) { create_room($hotelId, (string) $r, null); $n++; }
            flash("Hotel \"$name\" creato con $n camere, i reparti e le richieste di base. Il responsabile entra con l'utente \"$mUser\".");
        }
    }
    redirect('super/');
}

$hotels = db()->query("SELECT v.*,
        (SELECT COUNT(*) FROM rooms r WHERE r.hotel_id = v.id AND r.active = 1) AS rooms,
        (SELECT COUNT(*) FROM users u WHERE u.hotel_id = v.id AND u.active = 1) AS staff,
        (SELECT COUNT(*) FROM requests q WHERE q.hotel_id = v.id AND q.created_at >= CURDATE()) AS today
    FROM hotels v ORDER BY v.active DESC, v.name")->fetchAll();

page_head('Hotel');
?>
<header class="topbar">
  <div class="topbar-in">
    <span class="brand"><img src="<?= h(asset('assets/brand/upgrade-logo.png')) ?>" alt="Upgrade" class="brand-logo" width="98" height="32">RoomHotel · Super admin</span>
    <div class="who"><span><?= h($me['name']) ?></span> <a href="<?= h(app_path('logout.php')) ?>">Esci</a></div>
  </div>
</header>
<?php flash_box(); ?>
<main class="wrap">
  <form class="card form" method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>Nuovo hotel</h2>
    <div class="row">
      <label>Nome dell'hotel<input name="name" required maxlength="120" placeholder="Hotel Bellavista"></label>
      <label>Camere da<input type="number" name="from" value="101" min="1"></label>
      <label>a<input type="number" name="to" value="110" min="0"></label>
    </div>
    <div class="row">
      <label>Responsabile<input name="manager_name" required maxlength="100" placeholder="Mario Rossi"></label>
      <label>Utente<input name="manager_username" required maxlength="60" autocapitalize="none" placeholder="bellavista"></label>
      <label>Password<input name="manager_password" required minlength="6" autocomplete="new-password"></label>
    </div>
    <p class="small muted">L'hotel nasce con i reparti Reception, Piani, Manutenzione, Bar e Cucina, le richieste di base in 5 lingue e un menu di esempio.</p>
    <button class="btn primary">Crea hotel</button>
  </form>

  <div class="card">
    <h2>Hotel (<?= count($hotels) ?>)</h2>
    <?php if (!$hotels): ?><p class="muted">Nessun hotel.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Hotel</th><th>Camere</th><th>Personale</th><th>Richieste oggi</th><th>Creato</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($hotels as $v): ?>
        <tr class="<?= $v['active'] ? '' : 'off' ?>">
          <td><span class="dot" style="background:<?= h($v['color']) ?>"></span> <?= h($v['name']) ?><?= $v['active'] ? '' : ' <span class="tag">disattivato</span>' ?></td>
          <td><?= (int) $v['rooms'] ?></td>
          <td><?= (int) $v['staff'] ?></td>
          <td><?= (int) $v['today'] ?></td>
          <td><?= h(date('d/m/Y', strtotime($v['created_at']))) ?></td>
          <td class="actions">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="enter"><input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button class="btn small primary">Gestisci</button></form>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button class="btn small ghost"><?= $v['active'] ? 'Disattiva' : 'Riattiva' ?></button></form>
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
