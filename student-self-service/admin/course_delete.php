<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $_SESSION['flash_error'] = 'Cannot delete this course: it still has results referencing it.';
    }
    $stmt->close();
}

header('Location: courses.php');
exit;
