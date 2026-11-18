<?php // Selfcare original template. Variables: $title, $content, $user ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= \Selfcare\e((string) ($title ?? 'Selfcare')) ?></title>
<meta name="description" content="Selfcare is a hospital management system for patients, appointments, billing, pharmacy, labs and beds in one place.">
<meta name="theme-color" content="#0b5f56">
<link rel="icon" href="img/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png">
<link rel="apple-touch-icon" href="img/favicon-180.png">
<link rel="stylesheet" href="css/selfcare-app.css">
<script>document.documentElement.classList.add('js');</script>
<script src="js/selfcare-app.js" defer></script>
</head>
<body>
<a class="sc-skip" href="#main">Skip to content</a>
<header class="sc-head" data-sc-head>
  <div class="sc-head-in">
    <a class="sc-brand" href="./" aria-label="Self Care home">
      <img src="img/logo-mark.svg" width="34" height="34" alt="">
      <span>Self Care</span>
    </a>
    <button class="sc-burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="sc-nav" data-sc-burger>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <nav class="sc-nav" id="sc-nav" aria-label="Main">
      <a href="./#features">Features</a>
      <a href="./#modules">Modules</a>
      <a href="./#numbers">Platform</a>
      <?php if (!empty($user)): ?>
        <span class="sc-user"><?= \Selfcare\e(trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))) ?></span>
        <form method="post" action="logout" class="sc-inline"><button type="submit" class="sc-btn sc-btn-ghost sc-btn-sm">Sign out</button></form>
      <?php else: ?>
        <a class="sc-btn sc-btn-shimmer sc-btn-sm" href="login">Sign in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="sc-main" id="main"><?= $content ?></main>
<footer class="sc-foot">
  <div class="sc-foot-in">
    <div class="sc-foot-brand">
      <a class="sc-brand" href="./"><img src="img/logo-mark.svg" width="30" height="30" alt=""><span>Self Care</span></a>
      <p>One calm workspace for every hospital team. Patients, appointments, billing, pharmacy, labs and beds.</p>
    </div>
    <nav class="sc-foot-links" aria-label="Footer">
      <a href="./#features">Features</a>
      <a href="./#modules">Modules</a>
      <a href="login">Sign in</a>
    </nav>
  </div>
  <p class="sc-foot-copy">&copy; <?= date('Y') ?> Self Care. Proprietary hospital management system.</p>
</footer>
</body>
</html>
