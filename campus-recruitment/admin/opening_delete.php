<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $uploadDir = __DIR__ . '/../uploads';

    $stmt = $conn->prepare('SELECT image_url FROM openings WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $opening = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM openings WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($opening) {
            delete_image_file($opening['image_url'], $uploadDir);
        }
    } else {
        $_SESSION['flash_error'] = 'Cannot delete this opening: it still has applications referencing it.';
    }
    $stmt->close();
}

header('Location: openings.php');
exit;
