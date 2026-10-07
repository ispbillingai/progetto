<?php
/**
 * Runs every minute (cron) and checks twice (now and after 30 s):
 *  1. Scheduled requests (wake-up call, breakfast at 8:30…): lead_minutes before due_at they
 *     become open and the department is alerted.
 *  2. Unanswered requests (nobody tapped "Prendo io"):
 *       after hotels.remind_after seconds   -> the push goes again to the same people;
 *       after hotels.escalate_after seconds -> it goes to ALL the hotel's staff, and again
 *                                              every escalate_after seconds until someone takes it.
 *     0 turns a step off.
 *   /etc/cron.d/roomhotel: * * * * * www-data /usr/bin/php /var/www/html/progetto/bin/tick.php
 */
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/app.php';

function tick_pass(): void
{
    $pdo = db();

    // 1. Scheduled requests whose time has come.
    $rows = $pdo->query("SELECT q.id FROM requests q JOIN hotels v ON v.id = q.hotel_id
                          LEFT JOIN request_types t ON t.id = q.type_id
                         WHERE q.status = 'scheduled' AND v.active = 1
                           AND q.due_at - INTERVAL COALESCE(t.lead_minutes, 0) MINUTE <= NOW()")->fetchAll();
    foreach ($rows as $q) {
        $st = $pdo->prepare("UPDATE requests SET status = 'open', last_call_at = NOW() WHERE id = ? AND status = 'scheduled'");
        $st->execute([$q['id']]);
        if ($st->rowCount() && ($r = request_row((int) $q['id']))) notify_staff($r);
    }

    // 2. Reminders and escalation of open requests.
    $rows = $pdo->query("SELECT q.id, q.reminded_at, q.escalated_at,
                                TIMESTAMPDIFF(SECOND, q.last_call_at, NOW()) AS age,
                                TIMESTAMPDIFF(SECOND, q.escalated_at, NOW()) AS since_escalated,
                                v.remind_after, v.escalate_after
                           FROM requests q JOIN hotels v ON v.id = q.hotel_id
                          WHERE q.status = 'open' AND v.active = 1")->fetchAll();
    foreach ($rows as $c) {
        $esc = (int) $c['escalate_after'];
        $rem = (int) $c['remind_after'];
        if ($esc > 0 && $c['age'] >= $esc && ($c['escalated_at'] === null || $c['since_escalated'] >= $esc)) {
            // The WHERE makes it happen once even if two runs overlap.
            $st = $pdo->prepare("UPDATE requests SET escalated_at = NOW(), alerts = alerts + 1
                                  WHERE id = ? AND status = 'open' AND (escalated_at IS NULL OR escalated_at <= NOW() - INTERVAL ? SECOND)");
            $st->execute([$c['id'], $esc]);
            if ($st->rowCount() && ($r = request_row((int) $c['id']))) notify_staff($r, true);
        } elseif ($rem > 0 && $c['age'] >= $rem && $c['reminded_at'] === null && $c['escalated_at'] === null) {
            $st = $pdo->prepare("UPDATE requests SET reminded_at = NOW(), alerts = alerts + 1 WHERE id = ? AND status = 'open' AND reminded_at IS NULL");
            $st->execute([$c['id']]);
            if ($st->rowCount() && ($r = request_row((int) $c['id']))) notify_staff($r);
        }
    }
}

$lock = fopen(sys_get_temp_dir() . '/roomhotel-tick.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) exit;   // previous run still going
tick_pass();
if (in_array('--once', $argv, true)) exit;   // manual test run
sleep(30);
tick_pass();
