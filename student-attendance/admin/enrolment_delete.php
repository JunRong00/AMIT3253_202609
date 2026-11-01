<?php
require '../config.php';
require '../auth.php';
require_admin();

$semesterId = (int)($_POST['semester_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Attendance records already made are kept - only the enrolment goes.
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare('DELETE FROM enrolments WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: enrolments.php?semester_id=' . $semesterId . '&course_id=' . $courseId);
exit;
