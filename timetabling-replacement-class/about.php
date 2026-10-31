<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What Timetabling &amp; Replacement Class is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>Timetabling &amp; Replacement Class puts the weekly class timetable online and replaces the
email-and-spreadsheet process for replacement classes. Lecturers request a replacement slot, the
system makes sure the venue is free, and once the Academic Administration Office approves it, it
appears on every student's timetable straight away.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128197;</div>
<h3>1. Check the Timetable</h3>
<p>See every class for the week, by course, venue and time slot.</p>
</div>
<div class="card">
<div class="card-icon">&#128221;</div>
<h3>2. Lecturer Requests a Replacement</h3>
<p>Pick a new date, slot and a venue that's actually free.</p>
</div>
<div class="card">
<div class="card-icon">&#9989;</div>
<h3>3. Admin Approves</h3>
<p>The replacement shows up on the timetable and the original class is marked as replaced.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple campus digital service deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
