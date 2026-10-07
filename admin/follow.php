<?php
/** Which departments and floors a staff member follows (gets the requests of). */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();

$st = db()->prepare("SELECT * FROM users WHERE id = ? AND hotel_id = ? AND role IN ('staff','manager')");
$st->execute([(int) ($_GET['id'] ?? $_POST['id'] ?? 0), $hotelId]);
$person = $st->fetch();
if (!$person) redirect('admin/staff.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $all = !empty($_POST['all']);
    save_following((int) $person['id'], $hotelId, $all ? [] : (array) ($_POST['departments'] ?? []), $all ? [] : (array) ($_POST['zones'] ?? []));
    flash('Reparti e piani di ' . $person['name'] . ' salvati.');
    redirect('admin/staff.php');
}

$depts = departments($hotelId);
$zones = hotel_zones($hotelId);
$myDepts = user_department_ids($person);
$myZones = user_zones($person);

page_head('Cosa segue ' . $person['name']);
admin_nav('staff');
?>
<main class="wrap">
  <form class="card form follow-form" method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
    <h2>Cosa segue <?= h($person['name']) ?></h2>
    <p class="small muted">Riceve solo le richieste dei reparti scelti, e solo dei piani scelti. Niente spuntato = tutto.
      Una richiesta che non segue nessuno arriva comunque a tutti, e le urgenze arrivano sempre a tutti.</p>
    <fieldset class="follow-zone">
      <legend><strong>Reparti</strong></legend>
      <div class="chips">
        <?php foreach ($depts as $d): ?>
          <label class="chip"><input type="checkbox" name="departments[]" value="<?= (int) $d['id'] ?>"<?= in_array((int) $d['id'], $myDepts, true) ? ' checked' : '' ?>> <?= h($d['icon'] . ' ' . $d['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <?php if ($zones): ?>
    <fieldset class="follow-zone">
      <legend><strong>Piani</strong></legend>
      <div class="chips">
        <?php foreach ($zones as $z): ?>
          <label class="chip"><input type="checkbox" name="zones[]" value="<?= h($z) ?>"<?= in_array($z, $myZones, true) ? ' checked' : '' ?>> <?= h($z) ?></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endif; ?>
    <div class="row-btns">
      <button class="btn primary">Salva</button>
      <button class="btn" name="all" value="1">Segue tutto</button>
      <a class="btn ghost" href="<?= h(app_path('admin/staff.php')) ?>">Annulla</a>
    </div>
  </form>
</main>
<?php
page_foot();
