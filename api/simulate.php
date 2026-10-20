<?php
// Staff-only: simulate an RFID tap from the web UI (for testing without hardware or as a front-desk fallback).
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/rfid.php';
if (!has_role('admin', 'staff')) {
    json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
}
if (!is_post()) {
    json_response(['ok' => false, 'message' => 'POST required'], 405);
}
verify_csrf();
json_response(process_scan((string)post('uid'), 'WEB-SIM', 'simulator'));
