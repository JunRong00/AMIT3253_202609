<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What the Student Self-Service Portal is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>The Student Self-Service Portal lets students check their examination results and request
official documents &mdash; transcripts, confirmation letters, fee statements &mdash; online, instead
of queuing at the Registry counter, especially on results release day.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128202;</div>
<h3>1. Check Your Results</h3>
<p>See every grade released to you, grouped by semester.</p>
</div>
<div class="card">
<div class="card-icon">&#128221;</div>
<h3>2. Request a Document</h3>
<p>Choose the document, number of copies and how you want to receive it.</p>
</div>
<div class="card">
<div class="card-icon">&#128230;</div>
<h3>3. Collect It</h3>
<p>Track the request until it's Ready, then collect it or get it by email.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple campus digital service deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
