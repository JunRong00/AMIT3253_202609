<?php
require '../config.php';
require '../auth.php';
require_admin();

$studentId = (int)($_POST['student_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM results WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: results.php?student_id=' . $studentId);
exit;
