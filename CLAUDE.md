# RoomHotel (progetto) — handoff notes

`F:\progetto`, repo `ispbillingai/progetto` (**public**). **RoomHotel**: guest-to-staff requests for
hotels. Started on 2026-10-07 from a copy of the table-call app `F:\chiamata` (ispbillingai/chiamata):
same engine (fixed QR + code, PWA staff app with push, reminders/escalation, multi-venue superadmin),
reworked for hotels. Nothing of the Focacciami POS that was here before is used.

## How it works
- **Multi-hotel**: a superadmin (`/super/`) creates hotels (name, manager login, rooms from–to) and can
  "Gestisci" any hotel. `includes/catalog.php` seeds each new hotel with the departments (Reception,
  Piani, Manutenzione, Bar, Cucina), ~35 request types in 5 languages and a few example menu items.
- **Fixed QR per room** (`/r/<qr_token>`, rewrite in `.htaccess` → `r.php`). The QR never changes.
- **Code per stay**: each room always has a current `stays` row with a numeric code (3–6 digits).
  The guest types it once (signed cookie `cg<roomId>`, 14 days). Check-out (staff app › Camere › tap
  the room › Check-out, or Admin › Camere) closes the stay, its open requests and DND, and makes a new
  code. Check-in = optionally type the guest's name on the same sheet (shown to the staff only).
- **Departments** (`departments`, kind reception|housekeeping|maintenance|bar|kitchen|other) with
  optional service hours (`open_from`/`open_to`, may cross midnight): outside hours the guest sees
  "Ora chiuso" and cannot send. A department switched off disappears from the guest page.
- **Request types** (`request_types`): the guest's buttons, each in one department. `names` is JSON
  it/en/de/fr/es (fallback en → it). Flags: `ask_time` (guest picks now/today/tomorrow + HH:MM),
  `ask_items` (guest composes an order from `menu_items` of that department, qty 1–20, prices optional),
  `urgent` (push to ALL the hotel's staff at once, distinct sound), `lead_minutes` (how long before a
  scheduled time the staff is alerted). A note (≤500 chars) is always allowed; `hint` is its Italian placeholder.
- **Requests** (`requests`): status scheduled → open → taken → done (or cancelled by the guest while
  not taken). `scheduled` ones become open at `due_at − lead_minutes` (cron). A second tap on the same
  simple type while open is a reminder (`repeat_count`, every 30 s). `items` is a JSON snapshot,
  `type_name`/`icon` are copied so history survives catalogue edits. Staff can `reply` (≤300 chars,
  quick replies in the app): the guest sees it under the request; replying also takes the request.
- **Who gets what**: `users.department_ids` and `users.zones` (JSON; empty = all). `receives_request()`:
  follows it, or nobody follows it. Urgent and escalated requests reach everyone.
- **Staff app** `/staff/` (PWA): tabs Richieste / Programmate / Camere; polls `api/staff.php?a=feed`
  every 3 s, rings (urgent = fast beeps) and vibrates, wake lock; Web Push without payload
  (`includes/push.php`, VAPID keys in `app_settings`; `staff/sw.js` fetches `?a=push_summary`).
  iPhone: push only from the Home-screen app (iOS 16.4+). Needs HTTPS.
- **Cron** `bin/tick.php` every minute (checks twice): opens due scheduled requests; after
  `hotels.remind_after` s re-pushes the same people, after `escalate_after` s pushes everyone, repeated
  (0 = off; Admin › Impostazioni). Cron file (server config, not in git): `/etc/cron.d/roomhotel`.
- **Voice** (2026-10-08): the staff app reads each incoming request aloud (Web Speech synthesis, it-IT;
  `request_types.speech` = custom spoken text, toggle + test in the app's ☰ menu, saved in localStorage).
  The guest can speak a request (🎤 Parla, Web Speech recognition in the guest's language; also a mic in
  the note field): `guest.js` scores every button by `request_types.keywords` (comma list, defaults in
  `DEFAULT_KEYWORDS` of `includes/catalog.php` by Italian name, editable in Admin › Richieste) plus the
  button names, opens the matching sheet with the transcript as note ("🎤 …"), pre-picks menu items
  (quantities from number words, stem matching) and the time ("alle 7 e mezza", "domani"). No match →
  the first plain reception button. Only Chrome/Android and Safari/iOS have recognition; the 🎤 buttons
  hide elsewhere. `window.__rh` exposes `handleSpeech`/`matchType` for headless tests.
- **Guest page** `r.php` + `assets/guest.js`: departments with buttons, request sheet (note / time /
  items), live status (open, taken by first name, scheduled, done for 10 min, staff reply), cancel,
  "Non disturbare" toggle (`rooms.dnd`, shown on staff room cards), hotel `info_text`, 5 languages
  (`includes/guest_i18n.php`).
- **Admin** `/admin/`: Impostazioni (name, colour, logo, welcome/info texts, code digits, reminders),
  Camere (bulk 101…110 with floor, rename, check-out, new QR link), QR da stampare (`qrencode`),
  Reparti, Richieste (catalogue editor), Menu, Personale (role, departments, floors), Storico.
- Logins: `users.role` superadmin | manager | staff; "remember me" tokens in `auth_tokens` (180 days).
- Uploads (logo) in `storage/v<hotelId>/` (gitignored, denied by `.htaccess`), served by `file.php`.

## Where it runs
- Server: 217.160.131.242 (IONOS Ubuntu 24.04, Apache 2.4, PHP 8.3, MariaDB 10.11), shared with
  Focacciami and chiamata (see memory `pub-server.md`). SSH root; credentials and the plink one-liner
  are only in Claude's local memory (`C:\Users\magom\.claude\projects\f--progetto\memory\`).
- App folder: `/var/www/html/progetto` (git clone, branch `main`).
- Domain: **https://roomhotel.upgradesrls.com** (DNS → 217.160.131.242, Let's Encrypt via certbot,
  vhosts `roomhotel.conf` + `roomhotel-le-ssl.conf`). The old `progetto.upgradesrls.com` vhost was removed.
- DB `progetto`, user `progetto` (password only in the server's `config/database.php`). The POS tables
  copied from pub were dropped on 2026-10-07 (backup `/root/progetto-pos-backup-2026-10-07.sql.gz`).
- Logs: `/var/log/apache2/roomhotel.upgradesrls.com-error.log` (and `-access.log`).
- Superadmin: create/reset with `php bin/create-superadmin.php <user> <password> ["Name"]`.

## Workflow (do this after EVERY change, without being asked)
1. Edit locally in `F:\progetto`, `git commit`, `git push origin main`.
2. On the server, via plink:
   `cd /var/www/html/progetto && git pull origin main && php migrate.php`
3. Wait ~3 s (opcache revalidate_freq=2), then test live:
   - `php -l` each changed PHP file on the server;
   - `curl -s -o /dev/null -w '%{http_code}' https://roomhotel.upgradesrls.com/login.php`;
   - exercise changed pages/APIs (curl with a cookie jar, or a CLI script);
   - `tail /var/log/apache2/roomhotel.upgradesrls.com-error.log`: no new errors.
4. Report the commit hash and the test result.

## Rules
- Deploy this repo **only** to `/var/www/html/progetto`. Never pull it into `/var/www/html/pub`
  (Focacciami, live), `/var/www/html/chiamata`, `/var/www/html/presenzapro`, `/var/www/html/eliminacode`
  or ristorante.
- Never hand-edit tracked files on the server. `config/database.php` is gitignored and server-only.
- The repo is public: never commit passwords, API keys or tokens.
- New tables need `COLLATE utf8mb4_unicode_ci`.
- Users are never deleted, only disabled or enabled.
- Never change the WireGuard tunnel on this server (Focacciami uses it to reach the shop's printer).
- Local setup: copy `config/database.example.php` to `config/database.php`, create the DB, run
  `php migrate.php`, then `php bin/create-superadmin.php`.
