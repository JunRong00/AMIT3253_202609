<?php
require 'config.php';
require 'auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !current_user_is_lecturer()) {
    $courseId = (int)$_POST['course_id'];
    $uid = current_user_id();

    // INSERT IGNORE + the uniq_enrolment key makes a double-click harmless.
    $stmt = $conn->prepare('INSERT IGNORE INTO enrolments (user_id, course_id) SELECT ?, id FROM courses WHERE id = ?');
    $stmt->bind_param('ii', $uid, $courseId);
    $stmt->execute();
    $stmt->close();
}

header('Location: courses.php');
exit;
