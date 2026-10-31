<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $uploadDir = __DIR__ . '/../uploads';

    $stmt = $conn->prepare('SELECT image_url FROM locations WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $location = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM locations WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($location) {
            delete_image_file($location['image_url'], $uploadDir);
        }
    } else {
        $_SESSION['flash_error'] = 'Cannot delete this location: it still has issue reports referencing it.';
    }
    $stmt->close();
}

header('Location: locations.php');
exit;
