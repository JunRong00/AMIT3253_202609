<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$uid = current_user_id();

$stmt = $conn->prepare("SELECT DISTINCT document_type_id FROM document_requests WHERE user_id = ? AND status IN ('Pending', 'Processing')");
$stmt->bind_param('i', $uid);
$stmt->execute();
$active = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'document_type_id'));
$stmt->close();

echo json_encode(['active_type_ids' => $active]);
