<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

header('Content-Type: application/json');

$date      = $_GET['date'] ?? '';
$slotId    = (int)($_GET['time_slot_id'] ?? 0);
$excludeId = (int)($_GET['exclude_id'] ?? 0);

if ($date === '' || !strtotime($date) || $slotId < 1) {
    echo json_encode(['busy_venue_ids' => []]);
    exit;
}

echo json_encode(['busy_venue_ids' => busy_venue_ids($conn, $date, $slotId, $excludeId)]);
