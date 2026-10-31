<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$locationId = (int)($_GET['location_id'] ?? 0);
$excludeId  = (int)($_GET['exclude_issue_id'] ?? 0);

$stmt = $conn->prepare("SELECT DISTINCT category FROM issues WHERE location_id = ? AND status IN ('Open', 'In Progress') AND id != ?");
$stmt->bind_param('ii', $locationId, $excludeId);
$stmt->execute();
$categories = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category');
$stmt->close();

echo json_encode(['active_categories' => $categories]);
