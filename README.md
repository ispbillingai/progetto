# RoomHotel

Guest-to-staff requests for hotels, B&Bs and residences.

- One fixed QR per room. The guest enters the code given at check-in, then can reach **reception**,
  **housekeeping**, **maintenance**, **bar** and **kitchen** with one tap: wake-up call, towels, room
  service order from a menu, taxi, late check-out, a problem in the room, an emergency…
- Requests with a time (wake-up at 7:00, breakfast at 8:30) reach the department at the right moment.
- The code changes at every check-out, so the next guest uses the same QR with a new code.
- Staff app (installable on the phone) with sound, vibration and push notifications, filtered by
  department and floor; reminders and escalation when nobody answers; reply to the guest in one tap;
  rooms with codes, guest names, check-in/check-out and "do not disturb".
- Guest page in Italian, English, German, French and Spanish.
- Multi-hotel: each hotel configures rooms, departments, requests, menu, staff, logo and colour.

PHP 8.1+, MariaDB/MySQL, Apache with mod_rewrite, `qrencode` for the printable QR codes.

Setup: copy `config/database.example.php` to `config/database.php`, run `php migrate.php`, then
`php bin/create-superadmin.php <user> <password>` and sign in at `/login.php`. Add
`bin/tick.php` to cron every minute (scheduled requests, reminders, escalation).
