<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM document_requests WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'requests.php'));
exit;
