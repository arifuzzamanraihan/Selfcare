<?php // Selfcare original template. Variables: $title, $content, $user ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= \Selfcare\e((string) ($title ?? 'Selfcare')) ?></title>
<link rel="stylesheet" href="css/selfcare-app.css">
</head>
<body>
<header class="sc-head">
  <a class="sc-brand" href="./">Selfcare</a>
  <nav>
    <?php if (!empty($user)): ?>
      <span><?= \Selfcare\e(trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))) ?></span>
      <form method="post" action="logout" class="sc-inline"><button type="submit">Sign out</button></form>
    <?php else: ?>
      <a href="login">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<main class="sc-main"><?= $content ?></main>
<footer class="sc-foot">Selfcare — proprietary hospital management system.</footer>
</body>
</html>
