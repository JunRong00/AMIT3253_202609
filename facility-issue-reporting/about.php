<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What Campus Facility Issue Reporting is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>Campus Facility Issue Reporting lets students and staff report broken facilities &mdash; a warm
lecture hall, a flickering projector, a leaking tap &mdash; straight to the Estate Management Office,
instead of telling whoever happens to be nearby and hoping it gets passed on.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128205;</div>
<h3>1. Pick a Location</h3>
<p>Choose the building and room where you spotted the problem.</p>
</div>
<div class="card">
<div class="card-icon">&#128221;</div>
<h3>2. Report the Issue</h3>
<p>Pick a category, describe what's wrong, and set a priority.</p>
</div>
<div class="card">
<div class="card-icon">&#128295;</div>
<h3>3. Follow the Repair</h3>
<p>Watch the status move from Open to In Progress to Resolved.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple campus digital service deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
