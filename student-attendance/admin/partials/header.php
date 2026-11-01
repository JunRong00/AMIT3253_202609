<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<script>
(function () {
    var saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') {
        document.documentElement.setAttribute('data-theme', saved);
    }
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?></title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../style.css?v=<?= @filemtime(__DIR__ . '/../../style.css') ?>">
</head>
<body>
<nav class="navbar">
<a class="brand" href="sessions.php"><img src="../assets/tarumt-logo.png" alt="TAR UMT" class="brand-logo">Admin &middot; Student Attendance System</a>
<div class="nav-links">
<a href="sessions.php" class="<?= $currentPage === 'sessions.php' ? 'active' : '' ?>">Sessions</a>
<a href="semesters.php" class="<?= $currentPage === 'semesters.php' ? 'active' : '' ?>">Semesters</a>
<a href="courses.php" class="<?= $currentPage === 'courses.php' ? 'active' : '' ?>">Courses</a>
<a href="enrolments.php" class="<?= $currentPage === 'enrolments.php' ? 'active' : '' ?>">Enrolments</a>
<a href="feedback.php" class="<?= $currentPage === 'feedback.php' ? 'active' : '' ?>">Feedback</a>
<a href="messages.php" class="<?= $currentPage === 'messages.php' ? 'active' : '' ?>">Messages</a>
<a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">Users</a>
<a href="../logout.php">Logout</a>
<button id="theme-toggle" class="theme-toggle" type="button" aria-label="Toggle dark mode">&#9728;</button>
</div>
</nav>
<main class="container">
