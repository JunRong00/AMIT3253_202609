<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!lecturer_session($conn, current_user_id(), $id)) {
    http_response_code(404);
    echo json_encode(['error' => 'Class not found.']);
    exit;
}

// Polled every few seconds by session_view.php so the lecturer sees
// students appear as they check in, without reloading the page.
$stmt = $conn->prepare('
    SELECT u.id_number, u.name, a.status, a.checked_in_at
    FROM attendance a
    JOIN users u ON u.id = a.user_id
    WHERE a.session_id = ?
    ORDER BY a.checked_in_at DESC
');
$stmt->bind_param('i', $id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$checkIns = array_map(fn($r) => [
    'id_number' => $r['id_number'],
    'name'      => $r['name'],
    'status'    => $r['status'],
    'time'      => date('g:i:s a', strtotime($r['checked_in_at'])),
], $rows);

echo json_encode(['checked_in' => count($checkIns), 'check_ins' => $checkIns]);
