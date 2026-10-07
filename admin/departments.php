<?php
/** Departments of the hotel: name, icon, kind, service hours, order, on/off. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();

function clean_time(string $t): ?string
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t . ':00' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
    $icon = mb_substr(trim((string) ($_POST['icon'] ?? '')), 0, 8);
    $kind = isset(DEPT_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'other';
    $from = clean_time((string) ($_POST['open_from'] ?? ''));
    $to = clean_time((string) ($_POST['open_to'] ?? ''));
    if (!$from || !$to) $from = $to = null;
    $sort = (int) ($_POST['sort'] ?? 0);

    if ($action === 'add') {
        if ($name === '') {
            flash('Il nome del reparto è obbligatorio.', 'err');
        } else {
            db()->prepare('INSERT INTO departments (hotel_id, kind, name, icon, open_from, open_to, sort) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$hotelId, $kind, $name, $icon ?: DEPT_KINDS[$kind][1], $from, $to, $sort]);
            flash("Reparto \"$name\" creato. Ora aggiungi le sue richieste in Richieste.");
        }
    } elseif ($d = department((int) ($_POST['id'] ?? 0), $hotelId)) {
        if ($action === 'save' && $name !== '') {
            db()->prepare('UPDATE departments SET kind = ?, name = ?, icon = ?, open_from = ?, open_to = ?, sort = ? WHERE id = ?')
                ->execute([$kind, $name, $icon, $from, $to, $sort, $d['id']]);
            flash('Reparto salvato.');
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE departments SET active = 1 - active WHERE id = ?')->execute([$d['id']]);
            flash($d['active'] ? "{$d['name']} spento: le sue richieste non compaiono più agli ospiti." : "{$d['name']} riacceso.");
        }
    }
    redirect('admin/departments.php');
}

$depts = departments($hotelId, false);
$cnt = db()->prepare('SELECT department_id, COUNT(*) AS n FROM request_types WHERE hotel_id = ? AND active = 1 GROUP BY department_id');
$cnt->execute([$hotelId]);
$typeCount = array_column($cnt->fetchAll(), 'n', 'department_id');

page_head('Reparti');
admin_nav('departments');
?>
<main class="wrap">
  <div class="card">
    <h2>Reparti (<?= count($depts) ?>)</h2>
    <p class="small muted">Ogni richiesta dell'ospite va al reparto a cui appartiene. Un reparto spento non compare agli ospiti.
      Con un orario di servizio, fuori orario l'ospite vede "Ora chiuso" e non può ordinare (es. bar 10:00–23:00).</p>
    <?php foreach ($depts as $d): ?>
      <div class="type-row<?= $d['active'] ? '' : ' off' ?>">
        <form method="post" class="type-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <div class="type-main">
            <input name="icon" value="<?= h($d['icon']) ?>" maxlength="8" class="w-icon" aria-label="Icona">
            <input name="name" value="<?= h($d['name']) ?>" maxlength="60" required class="w-name" aria-label="Nome" placeholder="Nome">
            <select name="kind" aria-label="Tipo">
              <?php foreach (DEPT_KINDS as $k => [$label]): ?><option value="<?= $k ?>"<?= $d['kind'] === $k ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select>
            <label class="check nowrap">orario <input type="time" name="open_from" value="<?= h(substr((string) $d['open_from'], 0, 5)) ?>" class="w-time" aria-label="Apre"> –
              <input type="time" name="open_to" value="<?= h(substr((string) $d['open_to'], 0, 5)) ?>" class="w-time" aria-label="Chiude"></label>
            <label class="check nowrap">ordine <input type="number" name="sort" value="<?= (int) $d['sort'] ?>" class="w-sort" aria-label="Ordine"></label>
            <span class="small muted nowrap"><?= (int) ($typeCount[$d['id']] ?? 0) ?> richieste · <a href="<?= h(app_path('admin/catalog.php#d' . $d['id'])) ?>">vedi</a></span>
            <button class="btn small primary">Salva</button>
          </div>
        </form>
        <form method="post" class="inline type-toggle"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="btn small<?= $d['active'] ? ' ghost' : '' ?>"><?= $d['active'] ? 'Spegni' : 'Accendi' ?></button></form>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="card form" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>Nuovo reparto</h2>
    <div class="row">
      <label>Nome<input name="name" required maxlength="60" placeholder="es. Spa, Piscina, Ristorante"></label>
      <label>Icona (emoji)<input name="icon" maxlength="8" placeholder="💆"></label>
      <label>Tipo<select name="kind"><?php foreach (DEPT_KINDS as $k => [$label]): ?><option value="<?= $k ?>"<?= $k === 'other' ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
      <label>Apre<input type="time" name="open_from"></label>
      <label>Chiude<input type="time" name="open_to"></label>
      <label>Ordine<input type="number" name="sort" value="<?= count($depts) ?>"></label>
    </div>
    <button class="btn primary">Crea reparto</button>
  </form>
</main>
<?php
page_foot();
