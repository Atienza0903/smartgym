<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$page_title = 'System Settings';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'regen_key') {
        set_setting('device_api_key', bin2hex(random_bytes(16)));
        flash('success', 'New device API key generated. Update API_KEY in the ESP32 firmware.');
    } else {
        $cap = (int)post('max_capacity');
        if ($cap < 1 || post('gym_name') === '' || !post('open_time') || !post('close_time')) {
            flash('danger', 'Please fill in all fields with valid values.');
        } else {
            set_setting('gym_name', post('gym_name'));
            set_setting('max_capacity', (string)$cap);
            set_setting('open_time', post('open_time'));
            set_setting('close_time', post('close_time'));
            set_setting('scan_cooldown_seconds', (string)max(0, (int)post('scan_cooldown_seconds')));
            flash('success', 'Settings saved.');
        }
    }
    redirect('settings.php');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$apiUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/api/rfid.php';
$key = setting('device_api_key');
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-building"></i> Gym Settings</div>
            <div class="card-body">
                <form method="post" class="row g-3"><?= csrf_field() ?>
                    <div class="col-12"><label class="form-label">Gym Name</label><input name="gym_name" class="form-control" value="<?= e(setting('gym_name')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Maximum Capacity (people inside)</label><input type="number" min="1" name="max_capacity" class="form-control" value="<?= e(setting('max_capacity')) ?>" required>
                        <small class="text-secondary">RFID entry is denied once this is reached.</small></div>
                    <div class="col-md-6"><label class="form-label">Duplicate-tap cooldown (seconds)</label><input type="number" min="0" name="scan_cooldown_seconds" class="form-control" value="<?= e(setting('scan_cooldown_seconds', 5)) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Opening Time</label><input type="time" name="open_time" class="form-control" value="<?= e(setting('open_time')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Closing Time</label><input type="time" name="close_time" class="form-control" value="<?= e(setting('close_time')) ?>" required></div>
                    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-cpu"></i> ESP32 RFID Device</div>
            <div class="card-body">
                <label class="form-label">API Endpoint (SERVER_URL)</label>
                <input class="form-control font-monospace mb-3" value="<?= e($apiUrl) ?>" readonly onclick="this.select()">
                <label class="form-label">Device API Key (API_KEY)</label>
                <div class="input-group mb-2">
                    <input class="form-control font-monospace" value="<?= e($key) ?>" readonly onclick="this.select()">
                    <form method="post" data-confirm="Generate a new key? The ESP32 must be re-flashed with the new key."><?= csrf_field() ?><button name="action" value="regen_key" class="btn btn-outline-warning"><i class="bi bi-arrow-repeat"></i> Regenerate</button></form>
                </div>
                <?php if ($key === 'CHANGE-ME-DEVICE-KEY'): ?><div class="alert alert-warning py-2 small">You are using the default key. Click <b>Regenerate</b> before deploying.</div><?php endif; ?>
                <p class="small text-secondary mb-1">Put these values in <code>firmware/esp32_rfid/esp32_rfid.ino</code>. Use your PC's LAN IP (not <code>localhost</code>) so the ESP32 can reach it, e.g. <code>http://192.168.1.10/smartgym/api/rfid.php</code>.</p>
                <label class="form-label mt-2">Test with curl</label>
                <pre class="small bg-dark p-2 rounded mb-0" style="white-space:pre-wrap">curl -X POST "<?= e($apiUrl) ?>" -H "X-API-KEY: <?= e($key) ?>" -H "Content-Type: application/json" -d '{"uid":"A1B2C3D4","device_id":"ESP32-01"}'</pre>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
