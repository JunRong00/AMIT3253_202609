<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM timetable_entries WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $_SESSION['flash_error'] = 'Cannot remove this class: replacement class requests refer to it. Delete those requests first.';
    }
    $stmt->close();
}

header('Location: timetable.php?semester_id=' . (int)($_POST['semester_id'] ?? 0));
exit;
