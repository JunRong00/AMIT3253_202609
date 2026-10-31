<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $myId = (int)current_user_id();

    if ($id === $myId) {
        $_SESSION['flash_error'] = 'You cannot delete your own account.';
    } else {
        $stmt = $conn->prepare('SELECT image_url FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $photo = $stmt->get_result()->fetch_assoc()['image_url'] ?? null;
        $stmt->close();

        $conn->begin_transaction();

        $stmt = $conn->prepare('DELETE FROM document_requests WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM results WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM feedback WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        delete_image_file($photo, __DIR__ . '/../uploads');
    }
}

header('Location: users.php');
exit;
