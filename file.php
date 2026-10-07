<?php
/** Serves a hotel's logo from storage/ (not reachable directly). */
require __DIR__ . '/includes/app.php';

$hotel = hotel((int) ($_GET['v'] ?? 0));
$kind = (string) ($_GET['f'] ?? '');
$name = $hotel && $kind === 'logo' ? $hotel['logo_file'] : null;
$path = $name ? __DIR__ . '/storage/v' . $hotel['id'] . '/' . basename($name) : '';

if (!$hotel || !$hotel['active'] || !$name || !is_file($path)) {
    http_response_code(404);
    exit('Non trovato.');
}

$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
// URLs carry a hash of the file name (h=...), so a new upload gets a new URL.
header('Cache-Control: public, max-age=' . (isset($_GET['h']) ? 604800 : 300));
readfile($path);
