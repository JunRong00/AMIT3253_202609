<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $note   = $note === '' ? null : $note;

    if (in_array($status, request_statuses(), true)) {
        $stmt = $conn->prepare('UPDATE document_requests SET status = ?, admin_note = ? WHERE id = ?');
        $stmt->bind_param('ssi', $status, $note, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'requests.php'));
exit;
