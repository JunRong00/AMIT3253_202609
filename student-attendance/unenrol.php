<?php
require 'config.php';
require 'auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = (int)$_POST['course_id'];
    $uid = current_user_id();

    // Attendance records already made are kept - only the enrolment goes.
    $stmt = $conn->prepare('DELETE FROM enrolments WHERE user_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $uid, $courseId);
    $stmt->execute();
    $stmt->close();
}

header('Location: courses.php');
exit;
