<?php
// Public endpoint: returns only aggregate occupancy (no personal data).
require __DIR__ . '/../includes/bootstrap.php';
json_response(capacity_info() + ['updated' => date('h:i:s A')]);
