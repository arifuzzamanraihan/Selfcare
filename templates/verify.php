<?php // Selfcare original template. Result of a confirmation link. Variables: $msg [ok, title, text]
use function Selfcare\e; ?>
<section class="sc-auth"><div class="sc-auth-card">
  <h1 style="font-size:1.6rem"><?= e((string) $msg['title']) ?></h1>
  <div class="sc-alert <?= $msg['ok'] ? 'ok' : 'bad' ?>" role="status"><?= e((string) $msg['text']) ?></div>
  <p><a class="sc-btn sc-btn-primary" href="<?= e(\Selfcare\url('login')) ?>">Go to sign in</a></p>
</div></section>
