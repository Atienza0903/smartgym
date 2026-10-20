<?php
/**
 * ESP32 RFID endpoint.
 *
 * POST /api/rfid.php
 *   Headers: X-API-KEY: <device_api_key from Settings>   (or field "api_key")
 *   Body (JSON or form): { "uid": "A1B2C3D4", "device_id": "ESP32-01" }
 *
 * Response: { ok, action: IN|OUT|DENIED|IGNORED, message, member, inside, capacity }
 * GET /api/rfid.php?ping=1 with the key returns { ok: true } for connectivity checks.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/rfid.php';

$input = $_POST;
$raw = file_get_contents('php://input');
if ($raw && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode($raw, true) ?: [];
}

$key = $_SERVER['HTTP_X_API_KEY'] ?? ($input['api_key'] ?? ($_GET['api_key'] ?? ''));
$expected = (string)setting('device_api_key', '');
if ($expected === '' || !is_string($key) || !hash_equals($expected, $key)) {
    json_response(['ok' => false, 'action' => 'ERROR', 'message' => 'Invalid device key'], 401);
}

if (isset($_GET['ping'])) {
    json_response(['ok' => true, 'message' => 'SmartGym server reachable', 'time' => now()] + capacity_info());
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'action' => 'ERROR', 'message' => 'POST required'], 405);
}

$uid = (string)($input['uid'] ?? '');
$device = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($input['device_id'] ?? 'ESP32-01')), 0, 50) ?: 'ESP32-01';

try {
    json_response(process_scan($uid, $device, 'rfid'));
} catch (Throwable $ex) {
    error_log('RFID scan error: ' . $ex->getMessage());
    json_response(['ok' => false, 'action' => 'ERROR', 'message' => 'Server error'], 500);
}
