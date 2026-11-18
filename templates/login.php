<?php // Selfcare original template. Variables: $error ?>
<section class="sc-panel">
  <h1>Sign in</h1>
  <?php if (!empty($error)): ?><p class="sc-error"><?= \Selfcare\e((string) $error) ?></p><?php endif; ?>
  <form method="post" action="login" class="sc-form">
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="sc-btn">Sign in</button>
  </form>
</section>
