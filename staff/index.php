<?php
/** Staff app (installable PWA): live requests, scheduled ones, rooms with codes, check-in/out. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/push.php';

$user = require_role(['staff', 'manager', 'superadmin']);
$hotel = hotel((int) current_hotel_id());

$config = [
    'api'   => app_path('api/staff.php'),
    'csrf'  => csrf_token(),
    'vapid' => vapid_public_key(),
    'admin' => in_array($user['role'], ['manager', 'superadmin'], true) ? app_path('admin/') : null,
];
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0369a1">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="RoomHotel">
<title>RoomHotel · <?= h($hotel['name']) ?></title>
<link rel="manifest" href="<?= h(app_path('staff/manifest.php')) ?>">
<link rel="apple-touch-icon" href="<?= h(app_path('icon.php?s=180')) ?>">
<link rel="icon" href="<?= h(app_path('icon.php?s=192')) ?>">
<link rel="stylesheet" href="<?= h(asset('assets/app.css')) ?>">
</head>
<body class="waiter">
<header class="w-head">
  <img src="<?= h(asset('assets/brand/upgrade-mark.png')) ?>" alt="Upgrade" class="w-mark" width="36" height="36">
  <div class="w-title">
    <strong><?= h($hotel['name']) ?></strong>
    <span class="muted small"><?= h($user['name']) ?></span>
  </div>
  <span id="conn" class="conn" title="Connessione"></span>
  <button class="icon-btn" id="menuBtn" aria-label="Opzioni">☰</button>
</header>

<nav class="w-tabs three">
  <button class="on" data-tab="calls">Richieste <span class="badge" id="callCount" hidden>0</span></button>
  <button data-tab="sched">Programmate <span class="badge soft" id="schedCount" hidden>0</span></button>
  <button data-tab="rooms">Camere</button>
</nav>

<button class="w-follow" id="followLine" type="button" hidden></button>

<div id="iosInstall" class="w-enable ios" hidden>
  <p><strong>Installa l'app su iPhone</strong><br><span class="small">Su iPhone le notifiche arrivano solo dall'app sulla schermata Home.</span></p>
  <a class="btn primary" href="<?= h(app_path('staff/installa.php')) ?>">Come fare</a>
</div>

<div id="enable" class="w-enable" hidden>
  <p><strong>Attiva suono e notifiche</strong><br><span class="small" id="enableText">Tocca qui per sentire le richieste e riceverle anche a telefono bloccato.</span></p>
  <button class="btn primary" id="enableBtn">Attiva</button>
</div>

<main>
  <section id="tab-calls" class="w-list"></section>
  <section id="tab-sched" class="w-list" hidden></section>
  <section id="tab-rooms" hidden>
    <div class="w-filter"><input id="roomSearch" type="search" placeholder="Cerca camera o ospite…"></div>
    <div id="roomGrid" class="t-grid"></div>
  </section>
</main>

<dialog id="menuDialog" class="sheet">
  <h2>Opzioni</h2>
  <div id="followBox">
    <h3 class="follow-h">Cosa segui</h3>
    <p class="small muted">Ricevi solo le richieste dei reparti e dei piani scelti. Niente spuntato = tutto. Le urgenze arrivano sempre a tutti.</p>
    <div id="followList" class="follow-list"><p class="small muted">Caricamento…</p></div>
    <button class="btn ghost small" id="followAll" type="button">Segui tutto</button>
  </div>
  <button class="btn block primary" id="codesBtn" type="button">🔢 Codici camere</button>
  <a class="btn block" id="installBtn" href="<?= h(app_path('staff/installa.php')) ?>">📲 Installa l'app sul telefono</a>
  <div class="voice-row">
    <label class="check"><input type="checkbox" id="voiceOn"> 🔊 Leggi a voce le richieste che arrivano</label>
    <button class="btn small" id="voiceTest" type="button">Prova voce</button>
  </div>
  <p class="small" id="pushState"></p>
  <button class="btn block" id="pushTest">Invia notifica di prova</button>
  <?php if ($config['admin']): ?><a class="btn block" href="<?= h($config['admin']) ?>">Gestione hotel</a><?php endif; ?>
  <a class="btn block" href="<?= h(app_path('logout.php')) ?>">Esci</a>
  <button class="btn ghost block" data-close>Chiudi</button>
</dialog>

<dialog id="codesDialog" class="sheet">
  <h2>Codici camere</h2>
  <p class="small muted">Il codice da dare all'ospite al check-in. Cambia a ogni check-out.</p>
  <input id="codeSearch" type="search" placeholder="Cerca camera o ospite…">
  <div id="codesList" class="codes-list"></div>
  <button class="btn ghost block" data-close>Chiudi</button>
</dialog>

<dialog id="replyDialog" class="sheet">
  <h2 id="replyTitle">Rispondi all'ospite</h2>
  <p class="small muted">Il messaggio compare sul telefono dell'ospite sotto la sua richiesta.</p>
  <div class="chips" id="quickReplies">
    <button class="chip" type="button" data-reply="Arrivo subito.">Arrivo subito</button>
    <button class="chip" type="button" data-reply="Arriviamo tra 10 minuti.">Tra 10 minuti</button>
    <button class="chip" type="button" data-reply="Arriviamo tra 20 minuti.">Tra 20 minuti</button>
    <button class="chip" type="button" data-reply="Lo portiamo in camera al più presto.">Lo portiamo</button>
    <button class="chip" type="button" data-reply="Passi pure alla reception.">Passi in reception</button>
  </div>
  <textarea id="replyText" rows="2" maxlength="300" placeholder="Oppure scrivi un messaggio…"></textarea>
  <button class="btn primary block" id="replySend">Invia risposta</button>
  <button class="btn ghost block" data-close>Annulla</button>
</dialog>

<dialog id="roomDialog" class="sheet">
  <h2 id="roomTitle">Camera</h2>
  <p class="small" id="roomInfo"></p>
  <label class="small">Ospite (check-in)<input id="roomGuest" maxlength="100" placeholder="es. Sig. Rossi"></label>
  <button class="btn primary block" id="roomSaveGuest">Salva nome ospite</button>
  <button class="btn danger block" id="roomCheckout">Check-out: chiudi il soggiorno e genera un nuovo codice</button>
  <button class="btn ghost block" data-close>Annulla</button>
</dialog>

<?= powered_by() ?>
<p class="toast" id="toast" hidden></p>
<script>window.STAFF = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(asset('assets/staff.js')) ?>"></script>
</body>
</html>
