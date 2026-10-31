<?php
require '../config.php';
require '../auth.php';
require_admin();

$sessionId = (int)($_POST['session_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM attendance WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: session_view.php?id=' . $sessionId);
exit;
