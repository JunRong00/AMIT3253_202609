<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What the Student Attendance System is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>The Student Attendance System replaces the paper attendance list passed around the lecture hall.
Lecturers open check-in at the start of class and show a one-time code; students type it in on their
phone, and their attendance is recorded instantly &mdash; Present, or Late if they arrive more than
15 minutes after the class starts.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128218;</div>
<h3>1. Enrol</h3>
<p>The Academic Administration Office enrols each student in the courses they're taking.</p>
</div>
<div class="card">
<div class="card-icon">&#128273;</div>
<h3>2. Lecturer Opens Check-In</h3>
<p>A fresh six-character code is generated for each class, valid until the class ends.</p>
</div>
<div class="card">
<div class="card-icon">&#9989;</div>
<h3>3. Check In</h3>
<p>Enter the code within seconds and see your attendance record update.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple campus digital service deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
