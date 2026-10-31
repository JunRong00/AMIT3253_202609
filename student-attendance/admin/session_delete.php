<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];

    $conn->begin_transaction();

    $stmt = $conn->prepare('DELETE FROM attendance WHERE session_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM class_sessions WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
}

header('Location: sessions.php');
exit;
