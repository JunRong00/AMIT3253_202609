<?php
require 'config.php';
require 'auth.php';
require_lecturer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)$_POST['id'];
    $uid = current_user_id();

    // An Approved replacement is already on students' timetables, so only
    // an admin can cancel it - lecturers can withdraw Pending/Rejected ones.
    $stmt = $conn->prepare("DELETE FROM replacement_classes WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Rejected')");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
}

header('Location: index.php#my-requests');
exit;
