<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What Campus Recruitment &amp; Applications is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>Campus Recruitment &amp; Applications brings every opportunity on campus &mdash; part-time campus
jobs, internships with industry partners, club and society positions, and scholarships &mdash; into one
place, so students no longer have to chase separate forms, emails and notice boards.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128269;</div>
<h3>1. Browse Openings</h3>
<p>Filter by category and see how many days are left before each deadline.</p>
</div>
<div class="card">
<div class="card-icon">&#128221;</div>
<h3>2. Apply Online</h3>
<p>Send your contact number and a short cover note in one form.</p>
</div>
<div class="card">
<div class="card-icon">&#128202;</div>
<h3>3. Track Your Status</h3>
<p>Follow each application from Submitted to Shortlisted to Accepted.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple campus digital service deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
