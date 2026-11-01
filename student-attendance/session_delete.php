<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $session = lecturer_session($conn, current_user_id(), $id);

    if ($session) {
        // A session students have already checked in to is part of their
        // attendance record, so it can't be deleted - only edited.
        $stmt = $conn->prepare('DELETE FROM class_sessions WHERE id = ? AND NOT EXISTS (SELECT 1 FROM attendance WHERE session_id = ?)');
        $stmt->bind_param('ii', $id, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $_SESSION['flash_error'] = 'Cannot delete a class that students have already checked in to.';
        }
        $stmt->close();
    }
}

header('Location: sessions.php');
exit;
