<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

header('Content-Type: application/json');

// Fills the "Original Class" dropdown on create.php / edit.php with the
// chosen course's regular classes on the chosen date.
$courseId = (int)($_GET['course_id'] ?? 0);
$date = $_GET['date'] ?? '';

if ($date === '' || !strtotime($date) || !lecturer_owns_course($conn, current_user_id(), $courseId)) {
    echo json_encode(['classes' => []]);
    exit;
}

echo json_encode(['classes' => array_map(fn($c) => [
    'id'         => (int)$c['id'],
    'class_type' => $c['class_type'],
    'label'      => original_class_label($c),
], regular_classes_on($conn, $courseId, $date))]);
