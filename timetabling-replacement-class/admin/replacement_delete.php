<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM replacement_classes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'replacements.php'));
exit;
