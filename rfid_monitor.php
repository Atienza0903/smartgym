<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rfid.php';
require_role('admin', 'staff');
$page_title = 'RFID Access Monitor';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'checkout') {
        manual_checkout((int)post('id'));
        flash('success', 'Member checked out.');
    } elseif (post('action') === 'checkout_all') {
        $ids = q('SELECT id FROM members WHERE is_inside = 1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $mid) {
            manual_checkout((int)$mid, 'End-of-day auto check-out');
        }
        flash('success', count($ids) . ' member(s) checked out.');
    }
    redirect('rfid_monitor.php');
}

$inside = q('SELECT m.id, m.full_name, m.member_code, m.last_scan_at FROM members m WHERE m.is_inside = 1 ORDER BY m.last_scan_at DESC')->fetchAll();
$cards = q("SELECT full_name, rfid_uid FROM members WHERE rfid_uid IS NOT NULL ORDER BY full_name LIMIT 200")->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-people-fill"></i> Live Capacity</div>
            <div class="card-body"><?php require __DIR__ . '/includes/gauge.php'; ?></div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card-2-front"></i> Simulate / Manual Tap</div>
            <div class="card-body">
                <p class="small text-secondary">Use this to test without the ESP32 reader, or if the reader is offline. Same rules apply (capacity, expired membership).</p>
                <form id="simForm" class="d-flex gap-2">
                    <input list="cardList" id="simUid" class="form-control font-monospace" placeholder="Card UID" required>
                    <datalist id="cardList"><?php foreach ($cards as $c): ?><option value="<?= e($c['rfid_uid']) ?>"><?= e($c['full_name']) ?></option><?php endforeach; ?></datalist>
                    <button class="btn btn-primary text-nowrap"><i class="bi bi-wifi"></i> Tap</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="scan-flash mb-3" id="scanFlash">
            <div class="scan-icon text-secondary"><i class="bi bi-broadcast-pin"></i></div>
            <div class="fs-5 fw-semibold mt-2" id="scanMsg">Waiting for RFID tap…</div>
            <div class="text-secondary small" id="scanSub">Live — updates every 2 seconds</div>
        </div>
        <div class="row g-3">
            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-activity"></i> Live Scan Feed</div>
                    <ul class="list-group list-group-flush scan-feed" id="feed"><li class="list-group-item text-secondary">Loading…</li></ul>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-door-open"></i> Currently Inside <span class="badge bg-success"><?= count($inside) ?></span>
                        <?php if ($inside): ?>
                            <form method="post" class="ms-auto" data-confirm="Check out ALL members currently inside?"><?= csrf_field() ?><button name="action" value="checkout_all" class="btn btn-sm btn-outline-danger">Check out all</button></form>
                        <?php endif; ?>
                    </div>
                    <?php if (!$inside): ?><div class="empty"><i class="bi bi-door-closed"></i>Gym is empty.</div><?php else: ?>
                    <ul class="list-group list-group-flush" style="max-height:420px;overflow:auto">
                        <?php foreach ($inside as $m): ?>
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <div class="flex-grow-1"><div class="fw-semibold"><?= e($m['full_name']) ?></div><small class="text-secondary">Since <?= date('h:i A', strtotime($m['last_scan_at'])) ?></small></div>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><button name="action" value="checkout" class="btn btn-sm btn-outline-secondary" title="Check out"><i class="bi bi-box-arrow-right"></i></button></form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$page_scripts = <<<'JS'
<script>
(function () {
  const feed = document.getElementById('feed'), flash = document.getElementById('scanFlash');
  const msg = document.getElementById('scanMsg'), sub = document.getElementById('scanSub');
  let lastId = 0, first = true;
  const icons = { IN: 'bi-check-circle-fill text-success', OUT: 'bi-box-arrow-right text-secondary', DENIED: 'bi-x-octagon-fill text-danger' };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function show(action, title, detail) {
    flash.className = 'scan-flash mb-3 ' + ({ IN: 'in', OUT: 'out', DENIED: 'denied' }[action] || '');
    flash.querySelector('.scan-icon').innerHTML = '<i class="bi ' + (icons[action] || 'bi-info-circle') + '"></i>';
    msg.textContent = title; sub.textContent = detail;
  }

  function row(s, isNew) {
    const badge = { IN: 'success', OUT: 'secondary', DENIED: 'danger' }[s.direction];
    return '<li class="list-group-item' + (isNew ? ' new' : '') + '"><i class="bi ' + icons[s.direction] + '"></i>' +
      '<div class="flex-grow-1"><div class="fw-semibold">' + (s.full_name ? esc(s.full_name) : 'Unregistered card') + '</div>' +
      '<small class="text-secondary"><code>' + esc(s.rfid_uid) + '</code> ' + (s.reason ? '· ' + esc(s.reason) : '') + '</small></div>' +
      '<div class="text-end"><span class="badge bg-' + badge + '">' + s.direction + '</span><br><small class="text-secondary">' + s.time + '</small></div></li>';
  }

  function poll() {
    fetch('api/recent_scans.php', { cache: 'no-store' }).then(r => r.json()).then(d => {
      if (!d.scans) return;
      const newest = d.scans[0];
      feed.innerHTML = d.scans.length ? d.scans.map(s => row(s, !first && s.id > lastId)).join('') : '<li class="list-group-item text-secondary">No scans yet.</li>';
      if (newest && newest.id > lastId) {
        if (!first) {
          const name = newest.full_name || 'Unregistered card (' + newest.rfid_uid + ')';
          show(newest.direction, (newest.direction === 'IN' ? 'Welcome, ' : newest.direction === 'OUT' ? 'Goodbye, ' : 'Access denied: ') + name,
               (newest.reason || newest.direction) + ' · ' + newest.time);
          window.SmartGym && SmartGym.refreshCapacity();
        }
        lastId = newest.id;
      }
      first = false;
    }).catch(() => {});
  }
  poll(); setInterval(poll, 2000);

  document.getElementById('simForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const body = new URLSearchParams({ uid: document.getElementById('simUid').value, csrf: window.CSRF });
    fetch('api/simulate.php', { method: 'POST', body }).then(r => r.json()).then(r => {
      show(r.action, r.message + (r.member ? ' (' + r.member.name + ')' : ''), 'UID ' + (r.uid || '') + ' · ' + (r.time || ''));
      setTimeout(() => location.reload(), 1500);
    });
  });
})();
</script>
JS;
require __DIR__ . '/includes/footer.php';
