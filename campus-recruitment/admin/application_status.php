<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $status = $_POST['status'] ?? '';

    if (in_array($status, application_statuses(), true)) {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT opening_id, status FROM applications WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application && $status === 'Accepted' && $application['status'] !== 'Accepted') {
            // Lock the opening so two admins accepting different applicants at
            // the same moment can't both push it past its vacancy count.
            $openingId = (int)$application['opening_id'];
            $stmt = $conn->prepare('SELECT vacancies FROM openings WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $openingId);
            $stmt->execute();
            $vacancies = (int)$stmt->get_result()->fetch_assoc()['vacancies'];
            $stmt->close();

            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM applications WHERE opening_id = ? AND status = 'Accepted'");
            $stmt->bind_param('i', $openingId);
            $stmt->execute();
            $accepted = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            if ($accepted >= $vacancies) {
                $_SESSION['flash_error'] = "Cannot accept: all $vacancies vacancies for this opening are already filled.";
                $conn->rollback();
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'applications.php'));
                exit;
            }
        }

        if ($application) {
            $stmt = $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'applications.php'));
exit;
