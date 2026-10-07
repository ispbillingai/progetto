<?php
/** Requests of a day with response times, per department. */
require __DIR__ . '/../includes/app.php';
require __DIR__ . '/../includes/layout.php';

require_role(['manager', 'superadmin']);
$hotelId = (int) current_hotel_id();
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['day'] ?? '')) ? $_GET['day'] : date('Y-m-d');

$st = db()->prepare("SELECT q.*, r.label, r.zone, d.name AS dept, d.icon AS dept_icon, s.guest_name,
                            ut.name AS taken_name, ud.name AS done_name,
                            TIMESTAMPDIFF(SECOND, q.created_at, COALESCE(q.taken_at, q.done_at)) AS response
                       FROM requests q JOIN rooms r ON r.id = q.room_id JOIN departments d ON d.id = q.department_id
                       LEFT JOIN stays s ON s.id = q.stay_id
                       LEFT JOIN users ut ON ut.id = q.taken_by LEFT JOIN users ud ON ud.id = q.done_by
                      WHERE q.hotel_id = ? AND q.created_at >= ? AND q.created_at < ? + INTERVAL 1 DAY
                      ORDER BY q.created_at DESC");
$st->execute([$hotelId, $day, $day]);
$list = $st->fetchAll();

$answered = array_filter($list, fn($c) => $c['status'] !== 'cancelled' && $c['response'] !== null && $c['due_at'] === null);
$avg = $answered ? array_sum(array_column($answered, 'response')) / count($answered) : null;
$fmt = fn($s) => $s === null ? '—' : ($s < 60 ? $s . ' s' : floor($s / 60) . ' min ' . ($s % 60) . ' s');
$status = ['scheduled' => 'Programmata', 'open' => 'In attesa', 'taken' => 'Presa', 'done' => 'Fatta', 'cancelled' => 'Annullata dall\'ospite'];
$perDept = [];
foreach ($list as $c) $perDept[$c['dept']] = ($perDept[$c['dept']] ?? 0) + 1;

page_head('Storico');
admin_nav('history');
?>
<main class="wrap">
  <div class="card">
    <form method="get" class="inline">
      <label>Giorno <input type="date" name="day" value="<?= h($day) ?>" onchange="this.form.submit()"></label>
    </form>
    <div class="stats">
      <div><strong><?= count($list) ?></strong><span>richieste</span></div>
      <?php foreach ($perDept as $name => $n): ?><div><strong><?= (int) $n ?></strong><span><?= h($name) ?></span></div><?php endforeach; ?>
      <div><strong><?= h($fmt($avg === null ? null : (int) round($avg))) ?></strong><span>risposta media</span></div>
      <div><strong><?= count(array_filter($list, fn($c) => $c['urgent'])) ?></strong><span>urgenze</span></div>
    </div>
  </div>
  <div class="card">
    <?php if (!$list): ?><p class="muted">Nessuna richiesta in questo giorno.</p><?php else: ?>
    <div class="table-wrap"><table class="list">
      <thead><tr><th>Ora</th><th>Camera</th><th>Reparto</th><th>Richiesta</th><th>Stato</th><th>Risposta</th><th>Personale</th></tr></thead>
      <tbody>
      <?php foreach ($list as $c): $items = json_decode((string) $c['items'], true) ?: []; ?>
        <tr>
          <td><?= h(date('H:i', strtotime($c['created_at']))) ?></td>
          <td><?= h($c['label']) ?><?= $c['zone'] ? ' <span class="muted small">' . h($c['zone']) . '</span>' : '' ?><?= $c['guest_name'] ? '<br><span class="muted small">' . h($c['guest_name']) . '</span>' : '' ?></td>
          <td><?= h($c['dept_icon'] . ' ' . $c['dept']) ?></td>
          <td><?= h($c['icon'] . ' ' . $c['type_name']) ?>
              <?= $c['urgent'] ? ' <span class="tag warn">urgente</span>' : '' ?>
              <?= $c['due_at'] ? ' <span class="tag">per le ' . h(date('d/m H:i', strtotime($c['due_at']))) . '</span>' : '' ?>
              <?= $c['repeat_count'] ? ' <span class="tag">sollecitato ' . (int) $c['repeat_count'] . '×</span>' : '' ?>
              <?= $c['escalated_at'] ? ' <span class="tag warn">nessuna risposta: avvisati tutti</span>' : ($c['reminded_at'] ? ' <span class="tag">promemoria inviato</span>' : '') ?>
              <?php if ($items): ?><br><span class="small"><?= h(implode(', ', array_map(fn($i) => $i['qty'] . '× ' . $i['name'], $items))) ?><?= $c['total'] !== null ? ' · ' . h(money((float) $c['total'])) : '' ?></span><?php endif; ?>
              <?= $c['note'] ? '<br><span class="small muted">“' . h($c['note']) . '”</span>' : '' ?>
              <?= $c['reply'] ? '<br><span class="small">💬 ' . h($c['reply']) . '</span>' : '' ?></td>
          <td><?= h($status[$c['status']]) ?></td>
          <td><?= $c['status'] === 'cancelled' || $c['due_at'] ? '—' : h($fmt($c['response'] === null ? null : (int) $c['response'])) ?></td>
          <td><?= h($c['taken_name'] ?: $c['done_name'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</main>
<?php
page_foot();
