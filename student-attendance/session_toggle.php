<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_lecturer();

$id = (int)($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session = lecturer_session($conn, current_user_id(), $id);

    if ($session && $session['is_open']) {
        $stmt = $conn->prepare('UPDATE class_sessions SET is_open = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($session && $session['session_date'] !== date('Y-m-d')) {
        $_SESSION['flash_error'] = 'Check-in can only be opened on the day of the session.';
    } elseif ($session) {
        // Fresh code every time check-in is opened, so a code shared in a
        // previous class can't be reused. Retry on the (unlikely) chance it
        // matches another session that's open right now.
        do {
            $code = generate_check_in_code();
            $stmt = $conn->prepare('SELECT 1 FROM class_sessions WHERE check_in_code = ? AND is_open = 1');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $taken = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
        } while ($taken);

        $stmt = $conn->prepare('UPDATE class_sessions SET is_open = 1, check_in_code = ? WHERE id = ?');
        $stmt->bind_param('si', $code, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: session_view.php?id=' . $id);
exit;
