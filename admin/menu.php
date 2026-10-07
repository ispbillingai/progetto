<?php
/** Room service / bar list: the items a guest can order, per department, with prices. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();
$depts = departments($hotelId, false);
$deptsById = array_column($depts, null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $names = [];
    foreach (['it', 'en', 'de', 'fr', 'es'] as $l) {
        $v = mb_substr(trim((string) ($_POST['name_' . $l] ?? '')), 0, 120);
        if ($v !== '') $names[$l] = $v;
    }
    $deptId = (int) ($_POST['department_id'] ?? 0);
    $price = str_replace(',', '.', trim((string) ($_POST['price'] ?? '')));
    $price = $price === '' || !is_numeric($price) ? null : round((float) $price, 2);
    $fields = [
        isset($deptsById[$deptId]) ? $deptId : 0,
        mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 60) ?: null,
        json_encode($names, JSON_UNESCAPED_UNICODE), $price, (int) ($_POST['sort'] ?? 0),
    ];

    if ($action === 'add') {
        if (empty($names['it']) || !$fields[0]) {
            flash('Servono almeno il nome in italiano e il reparto.', 'err');
        } else {
            db()->prepare('INSERT INTO menu_items (hotel_id, department_id, category, names, price, sort) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(array_merge([$hotelId], $fields));
            flash('Voce "' . $names['it'] . '" aggiunta.');
        }
    } else {
        $st = db()->prepare('SELECT * FROM menu_items WHERE id = ? AND hotel_id = ?');
        $st->execute([(int) ($_POST['id'] ?? 0), $hotelId]);
        $m = $st->fetch();
        if ($m && $action === 'save' && !empty($names['it']) && $fields[0]) {
            db()->prepare('UPDATE menu_items SET department_id = ?, category = ?, names = ?, price = ?, sort = ? WHERE id = ?')
                ->execute(array_merge($fields, [$m['id']]));
            flash('Voce salvata.');
        } elseif ($m && $action === 'toggle') {
            db()->prepare('UPDATE menu_items SET active = 1 - active WHERE id = ?')->execute([$m['id']]);
            flash($m['active'] ? 'Voce nascosta agli ospiti.' : 'Voce di nuovo visibile.');
        }
    }
    redirect('admin/menu.php');
}

$items = menu_items($hotelId, null, false);
$byDept = [];
foreach ($items as $m) $byDept[(int) $m['department_id']][] = $m;
$cats = array_values(array_unique(array_filter(array_column($items, 'category'))));

page_head('Menu');
admin_nav('menu');

function item_form(array $m, array $depts, string $action): void
{
    $n = names_decode($m['names'] ?? '');
    ?>
    <form method="post" class="type-form<?= empty($m['active']) && $action === 'save' ? ' off' : '' ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'save'): ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php endif; ?>
      <div class="type-main">
        <input name="category" value="<?= h($m['category'] ?? '') ?>" maxlength="60" list="cats" placeholder="Categoria" class="w-cat" aria-label="Categoria">
        <input name="name_it" value="<?= h($n['it'] ?? '') ?>" maxlength="120" required placeholder="Nome in italiano" aria-label="Italiano">
        <input name="name_en" value="<?= h($n['en'] ?? '') ?>" maxlength="120" placeholder="English" aria-label="English">
        <input name="price" value="<?= !isset($m['price']) ? '' : h(number_format((float) $m['price'], 2, ',', '')) ?>" inputmode="decimal" placeholder="€" class="w-price" aria-label="Prezzo">
        <select name="department_id" aria-label="Reparto">
          <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"<?= (int) ($m['department_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= h($d['icon'] . ' ' . $d['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="sort" value="<?= (int) ($m['sort'] ?? 0) ?>" class="w-sort" aria-label="Ordine" title="Ordine">
        <button class="btn small primary"><?= $action === 'add' ? 'Aggiungi' : 'Salva' ?></button>
      </div>
      <details class="type-more"><summary>Altre lingue</summary>
        <div class="row">
          <label>Deutsch<input name="name_de" value="<?= h($n['de'] ?? '') ?>" maxlength="120"></label>
          <label>Français<input name="name_fr" value="<?= h($n['fr'] ?? '') ?>" maxlength="120"></label>
          <label>Español<input name="name_es" value="<?= h($n['es'] ?? '') ?>" maxlength="120"></label>
        </div>
      </details>
    </form>
    <?php
}
?>
<main class="wrap">
  <datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
  <div class="card">
    <h2>Menu per gli ordini in camera</h2>
    <p class="small muted">Le voci compaiono quando l'ospite usa una richiesta che "sceglie dal menu" (es. Servizio in camera, Ordina dal bar): vede solo quelle del reparto della richiesta.
      Il prezzo è facoltativo. Le voci di esempio si possono rinominare o nascondere.</p>
  </div>

  <?php foreach ($depts as $d): if (empty($byDept[(int) $d['id']])) continue; ?>
    <div class="card">
      <h2><?= h($d['icon'] . ' ' . $d['name']) ?></h2>
      <?php foreach ($byDept[(int) $d['id']] as $m): ?>
        <div class="type-row">
          <?php item_form($m, $depts, 'save'); ?>
          <form method="post" class="inline type-toggle"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $m['id'] ?>">
            <button class="btn small<?= $m['active'] ? ' ghost' : '' ?>"><?= $m['active'] ? 'Nascondi' : 'Mostra' ?></button></form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <h2>Nuova voce</h2>
    <?php item_form(['sort' => count($items)], $depts, 'add'); ?>
  </div>
</main>
<?php
page_foot();
