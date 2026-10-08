<?php
/** What a guest can ask for: the buttons of the room page, per department, in five languages. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/guest_i18n.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();
$depts = departments($hotelId, false);
$deptsById = array_column($depts, null, 'id');

function names_from_post(): array
{
    $n = [];
    foreach (array_keys(GUEST_LANGS) as $l) {
        $v = mb_substr(trim((string) ($_POST['name_' . $l] ?? '')), 0, 120);
        if ($v !== '') $n[$l] = $v;
    }
    return $n;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $names = names_from_post();
    $deptId = (int) ($_POST['department_id'] ?? 0);
    $fields = [
        isset($deptsById[$deptId]) ? $deptId : 0,
        mb_substr(trim((string) ($_POST['icon'] ?? '')), 0, 8),
        json_encode($names, JSON_UNESCAPED_UNICODE),
        mb_substr(trim((string) ($_POST['hint'] ?? '')), 0, 200) ?: null,
        mb_substr(trim((string) ($_POST['speech'] ?? '')), 0, 200) ?: null,
        empty($_POST['ask_time']) ? 0 : 1, empty($_POST['ask_items']) ? 0 : 1, empty($_POST['urgent']) ? 0 : 1,
        max(0, min(720, (int) ($_POST['lead_minutes'] ?? 0))), (int) ($_POST['sort'] ?? 0),
    ];

    if ($action === 'add') {
        if (empty($names['it']) || !$fields[0]) {
            flash('Servono almeno il nome in italiano e il reparto.', 'err');
        } else {
            db()->prepare('INSERT INTO request_types (hotel_id, department_id, icon, names, hint, speech, ask_time, ask_items, urgent, lead_minutes, sort)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(array_merge([$hotelId], $fields));
            flash('Richiesta "' . $names['it'] . '" aggiunta.');
        }
    } else {
        $st = db()->prepare('SELECT * FROM request_types WHERE id = ? AND hotel_id = ?');
        $st->execute([(int) ($_POST['id'] ?? 0), $hotelId]);
        $t = $st->fetch();
        if ($t && $action === 'save' && !empty($names['it']) && $fields[0]) {
            db()->prepare('UPDATE request_types SET department_id = ?, icon = ?, names = ?, hint = ?, speech = ?, ask_time = ?, ask_items = ?, urgent = ?,
                                  lead_minutes = ?, sort = ? WHERE id = ?')->execute(array_merge($fields, [$t['id']]));
            flash('Richiesta salvata.');
        } elseif ($t && $action === 'toggle') {
            db()->prepare('UPDATE request_types SET active = 1 - active WHERE id = ?')->execute([$t['id']]);
            flash($t['active'] ? 'Richiesta nascosta agli ospiti.' : 'Richiesta di nuovo visibile.');
        }
    }
    redirect('admin/catalog.php');
}

$types = request_types($hotelId, false);
$byDept = [];
foreach ($types as $t) $byDept[(int) $t['department_id']][] = $t;

page_head('Richieste');
admin_nav('catalog');

function type_form(array $t, array $depts, string $action): void
{
    $n = names_decode($t['names'] ?? '');
    ?>
    <form method="post" class="type-form<?= empty($t['active']) && $action === 'save' ? ' off' : '' ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'save'): ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><?php endif; ?>
      <div class="type-main">
        <input name="icon" value="<?= h($t['icon'] ?? '') ?>" maxlength="8" class="w-icon" placeholder="🙂" aria-label="Icona">
        <input name="name_it" value="<?= h($n['it'] ?? '') ?>" maxlength="120" required placeholder="Nome in italiano" aria-label="Italiano">
        <input name="name_en" value="<?= h($n['en'] ?? '') ?>" maxlength="120" placeholder="English" aria-label="English">
        <select name="department_id" aria-label="Reparto">
          <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"<?= (int) ($t['department_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= h($d['icon'] . ' ' . $d['name']) ?><?= $d['active'] ? '' : ' (spento)' ?></option><?php endforeach; ?>
        </select>
        <button class="btn small primary"><?= $action === 'add' ? 'Aggiungi' : 'Salva' ?></button>
      </div>
      <div class="type-opts">
        <label class="check"><input type="checkbox" name="ask_time" value="1"<?= !empty($t['ask_time']) ? ' checked' : '' ?>> chiede un orario</label>
        <label class="check"><input type="checkbox" name="ask_items" value="1"<?= !empty($t['ask_items']) ? ' checked' : '' ?>> sceglie dal menu del reparto</label>
        <label class="check"><input type="checkbox" name="urgent" value="1"<?= !empty($t['urgent']) ? ' checked' : '' ?>> urgente (subito a tutti)</label>
        <label class="check">preavviso <input type="number" name="lead_minutes" value="<?= (int) ($t['lead_minutes'] ?? 0) ?>" min="0" max="720" class="w-sort"> min</label>
        <label class="check">ordine <input type="number" name="sort" value="<?= (int) ($t['sort'] ?? 0) ?>" class="w-sort"></label>
        <details class="type-more"><summary>Altre lingue, suggerimento e voce</summary>
          <div class="row">
            <label>Deutsch<input name="name_de" value="<?= h($n['de'] ?? '') ?>" maxlength="120"></label>
            <label>Français<input name="name_fr" value="<?= h($n['fr'] ?? '') ?>" maxlength="120"></label>
            <label>Español<input name="name_es" value="<?= h($n['es'] ?? '') ?>" maxlength="120"></label>
          </div>
          <label>Suggerimento nel campo note (in italiano)<input name="hint" value="<?= h($t['hint'] ?? '') ?>" maxlength="200" placeholder="es. Quanti? Per quante persone?"></label>
          <label>Testo letto a voce dall'app del personale (vuoto = il nome in italiano)<input name="speech" value="<?= h($t['speech'] ?? '') ?>" maxlength="200" placeholder="es. Servono asciugamani"></label>
        </details>
      </div>
    </form>
    <?php
}
?>
<main class="wrap">
  <div class="card">
    <h2>Le richieste che l'ospite può fare</h2>
    <p class="small muted">Ogni voce è un bottone sulla pagina della camera, nel riquadro del suo reparto. L'ospite può sempre aggiungere una nota.
      "Chiede un orario": sveglia, colazione, pulizia… la richiesta compare al reparto all'ora giusta (meno il preavviso).
      "Sceglie dal menu": l'ospite compone l'ordine dalle voci in <a href="<?= h(app_path('admin/menu.php')) ?>">Menu</a>.
      L'app del personale legge a voce ogni richiesta che arriva ("Camera 101, Asciugamani"): il testo letto si cambia in "Altre lingue, suggerimento e voce".
      Le lingue mancanti usano l'inglese, poi l'italiano.</p>
  </div>

  <?php foreach ($depts as $d): ?>
    <div class="card" id="d<?= $d['id'] ?>">
      <h2><?= h($d['icon'] . ' ' . $d['name']) ?><?= $d['active'] ? '' : ' <span class="tag">reparto spento</span>' ?></h2>
      <?php foreach ($byDept[(int) $d['id']] ?? [] as $t): ?>
        <div class="type-row">
          <?php type_form($t, $depts, 'save'); ?>
          <form method="post" class="inline type-toggle"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="btn small<?= $t['active'] ? ' ghost' : '' ?>"><?= $t['active'] ? 'Nascondi' : 'Mostra' ?></button></form>
        </div>
      <?php endforeach; ?>
      <?php if (empty($byDept[(int) $d['id']])): ?><p class="muted small">Nessuna richiesta per questo reparto.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <h2>Nuova richiesta</h2>
    <?php type_form(['sort' => count($types)], $depts, 'add'); ?>
  </div>
</main>
<?php
page_foot();
