<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $uploadDir = __DIR__ . '/../uploads';

    $stmt = $conn->prepare('SELECT image_url FROM venues WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $venue = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM venues WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($venue) {
            delete_image_file($venue['image_url'], $uploadDir);
        }
    } else {
        $_SESSION['flash_error'] = 'Cannot delete this venue: it still has timetable classes or replacement requests referencing it.';
    }
    $stmt->close();
}

header('Location: venues.php');
exit;
