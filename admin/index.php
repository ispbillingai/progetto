<?php
/** Hotel settings: name, colour, logo, welcome and info texts, code length, reminders. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

$user = require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();
$hotel = hotel($hotelId);
$dir = __DIR__ . '/../storage/v' . $hotelId;

const REMIND_OPTIONS = [0 => 'Mai', 60 => 'Dopo 1 minuto', 120 => 'Dopo 2 minuti', 180 => 'Dopo 3 minuti', 300 => 'Dopo 5 minuti'];
const ESCALATE_OPTIONS = [0 => 'Mai', 120 => 'Dopo 2 minuti', 300 => 'Dopo 5 minuti', 600 => 'Dopo 10 minuti', 900 => 'Dopo 15 minuti'];

/** Saves an uploaded file in the hotel folder; returns the new file name or an error string in $err. */
function save_upload(string $field, array $allowed, int $maxBytes, string $prefix, string $dir, ?string &$err): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) { $err = 'Caricamento non riuscito (file troppo grande?).'; return null; }
    if ($f['size'] > $maxBytes) { $err = 'File troppo grande (massimo ' . round($maxBytes / 1048576) . ' MB).'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) { $err = 'Formato non valido.'; return null; }
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) { $err = 'Cartella di salvataggio non scrivibile.'; return null; }
    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $err = 'Salvataggio non riuscito.'; return null; }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $err = null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['color'] ?? '')) ? strtolower($_POST['color']) : $hotel['color'];
    if (!empty($_POST['brand_color'])) $color = BRAND_COLOR;
    if ($name === '') $err = 'Il nome dell\'hotel è obbligatorio.';

    $logo = $hotel['logo_file'];
    if (!$err && ($new = save_upload('logo', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 3 << 20, 'logo', $dir, $err))) {
        if ($logo) @unlink($dir . '/' . $logo);
        $logo = $new;
    }
    if (!empty($_POST['remove_logo']) && $logo) { @unlink($dir . '/' . $logo); $logo = null; }

    if ($err) {
        flash($err, 'err');
    } else {
        db()->prepare('UPDATE hotels SET name = ?, color = ?, welcome_text = ?, info_text = ?, logo_file = ?,
                              code_length = ?, remind_after = ?, escalate_after = ? WHERE id = ?')
            ->execute([
                mb_substr($name, 0, 120), $color,
                mb_substr(trim((string) ($_POST['welcome_text'] ?? '')), 0, 500) ?: null,
                mb_substr(trim((string) ($_POST['info_text'] ?? '')), 0, 3000) ?: null,
                $logo,
                max(3, min(6, (int) ($_POST['code_length'] ?? 4))),
                in_array((int) ($_POST['remind_after'] ?? 120), array_keys(REMIND_OPTIONS), true) ? (int) $_POST['remind_after'] : 120,
                in_array((int) ($_POST['escalate_after'] ?? 300), array_keys(ESCALATE_OPTIONS), true) ? (int) $_POST['escalate_after'] : 300,
                $hotelId,
            ]);
        flash('Impostazioni salvate.');
    }
    redirect('admin/');
}

$counts = db()->prepare('SELECT (SELECT COUNT(*) FROM rooms WHERE hotel_id = ? AND active = 1) AS rooms,
                                (SELECT COUNT(*) FROM users WHERE hotel_id = ? AND active = 1) AS staff');
$counts->execute([$hotelId, $hotelId]);
$counts = $counts->fetch();

page_head('Impostazioni');
admin_nav('settings');
?>
<main class="wrap">
  <?php if (!$counts['rooms'] || $counts['staff'] < 2): ?>
  <div class="card steps">
    <h2>Configurazione rapida</h2>
    <ol>
      <li class="<?= $counts['rooms'] ? 'done' : '' ?>"><a href="<?= h(app_path('admin/rooms.php')) ?>">Crea le camere</a> (anche tutte insieme: 101…130, con il piano)</li>
      <li><a href="<?= h(app_path('admin/qr.php')) ?>">Stampa i QR</a> e mettili nelle camere</li>
      <li><a href="<?= h(app_path('admin/departments.php')) ?>">Controlla i reparti</a> (spegni bar o cucina se non ci sono) e <a href="<?= h(app_path('admin/catalog.php')) ?>">le richieste</a> che l'ospite può fare</li>
      <li class="<?= $counts['staff'] > 1 ? 'done' : '' ?>"><a href="<?= h(app_path('admin/staff.php')) ?>">Aggiungi il personale</a> e scegli per ognuno i reparti e i piani che segue</li>
      <li>Ognuno installa l'app sul telefono (<a href="<?= h(app_path('staff/installa.php')) ?>" target="_blank">guida per iPhone e Android</a>) e tocca "Attiva"</li>
      <li>Al check-in la reception dà all'ospite il codice della camera (lo vede nell'app, scheda Camere) e al check-out tocca la camera › Check-out</li>
    </ol>
  </div>
  <?php endif; ?>

  <form class="card form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>Hotel</h2>
    <label>Nome dell'hotel<input name="name" required maxlength="120" value="<?= h($hotel['name']) ?>"></label>
    <label>Colore della pagina ospiti<input type="color" name="color" value="<?= h($hotel['color']) ?>"></label>
    <label class="check"><input type="checkbox" name="brand_color" value="1"<?= strtolower($hotel['color']) === BRAND_COLOR ? ' checked' : '' ?>> Usa i colori Upgrade (azzurro del logo)</label>
    <label>Messaggio di benvenuto (facoltativo)<textarea name="welcome_text" rows="2" maxlength="500"><?= h($hotel['welcome_text']) ?></textarea></label>
    <label>Informazioni utili per l'ospite (facoltative: Wi‑Fi, orari colazione, piscina, check-out…)
      <textarea name="info_text" rows="5" maxlength="3000" placeholder="Wi‑Fi: rete HotelMare, password mare2026&#10;Colazione: 7:00–10:30 in sala al piano terra&#10;Check-out entro le 11:00"><?= h($hotel['info_text']) ?></textarea></label>
    <label>Logo (PNG, JPG o WEBP, max 3 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
    <?php if ($hotel['logo_file']): ?>
      <div class="preview"><img src="<?= h(app_path('file.php?v=' . $hotelId . '&f=logo&h=' . substr(md5($hotel['logo_file']), 0, 8))) ?>" alt="Logo">
        <label class="check"><input type="checkbox" name="remove_logo" value="1"> Rimuovi logo</label></div>
    <?php endif; ?>

    <h2>Codici camera</h2>
    <label>Cifre del codice
      <select name="code_length">
        <?php for ($i = 3; $i <= 6; $i++): ?><option value="<?= $i ?>"<?= (int) $hotel['code_length'] === $i ? ' selected' : '' ?>><?= $i ?> cifre</option><?php endfor; ?>
      </select>
      <span class="small muted">Vale per i nuovi codici, generati a ogni check-out.</span>
    </label>

    <h2>Se nessuno risponde</h2>
    <p class="small muted">Quando nessuno del reparto tocca "Prendo io". Le urgenze arrivano subito a tutti.</p>
    <label>Ricorda la richiesta allo stesso reparto
      <select name="remind_after">
        <?php foreach (REMIND_OPTIONS as $s => $label): ?><option value="<?= $s ?>"<?= (int) $hotel['remind_after'] === $s ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Avvisa tutto il personale, anche degli altri reparti (e ripeti finché qualcuno risponde)
      <select name="escalate_after">
        <?php foreach (ESCALATE_OPTIONS as $s => $label): ?><option value="<?= $s ?>"<?= (int) $hotel['escalate_after'] === $s ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select>
    </label>

    <button class="btn primary">Salva</button>
  </form>
</main>
<?php
page_foot();
