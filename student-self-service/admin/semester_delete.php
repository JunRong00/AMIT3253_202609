<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM semesters WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $_SESSION['flash_error'] = 'Cannot delete this semester: it still has results entered against it.';
    }
    $stmt->close();
}

header('Location: semesters.php');
exit;
