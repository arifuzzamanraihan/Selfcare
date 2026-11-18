<?php // Selfcare original template. Variables: $counts, $user ?>
<section class="sc-hero">
  <h1>Selfcare hospital management</h1>
  <p>Running on the original Selfcare core — PHP and MySQL only.</p>
  <?php if (empty($user)): ?><p><a class="sc-btn" href="login">Sign in</a></p><?php endif; ?>
</section>
<section class="sc-cards">
  <?php foreach (($counts ?? []) as $label => $value): ?>
    <article class="sc-card"><strong><?= (int) $value ?></strong><span><?= \Selfcare\e((string) $label) ?></span></article>
  <?php endforeach; ?>
</section>
