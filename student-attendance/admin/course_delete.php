<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $uploadDir = __DIR__ . '/../uploads';

    $stmt = $conn->prepare('SELECT image_url FROM courses WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($course) {
            delete_image_file($course['image_url'], $uploadDir);
        }
    } else {
        $_SESSION['flash_error'] = 'Cannot delete this course: it still has sessions or enrolments referencing it.';
    }
    $stmt->close();
}

header('Location: courses.php');
exit;
