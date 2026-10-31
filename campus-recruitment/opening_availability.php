<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$uid = current_user_id();

$stmt = $conn->prepare('SELECT opening_id FROM applications WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$applied = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'opening_id'));
$stmt->close();

$filled = array_map('intval', array_column($conn->query("
    SELECT o.id
    FROM openings o
    JOIN applications a ON a.opening_id = o.id AND a.status = 'Accepted'
    GROUP BY o.id, o.vacancies
    HAVING COUNT(*) >= o.vacancies
")->fetch_all(MYSQLI_ASSOC), 'id'));

echo json_encode(['applied_opening_ids' => $applied, 'filled_opening_ids' => $filled]);
