<?php // Selfcare original template. Variables: $vendor, $sent
use function Selfcare\{e, csrf_field}; ?>
<section class="sc-auth"><div class="sc-auth-card">
  <h1 style="font-size:1.6rem">Forgot your password?</h1>
  <?php if (!empty($sent)): ?><div class="sc-alert ok" role="status">If that email has an account, we have sent a link to choose a new password. It works once and expires in 1 hour.</div><p><a href="<?= e(\Selfcare\url('login')) ?>">Back to sign in</a></p>
  <?php else: ?><p class="d-mute">Enter your email and we will send you a link.</p>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('forgot')) ?>"><?= csrf_field() ?><label>Email<input type="email" name="email" required autocomplete="username" autofocus></label><button class="sc-btn sc-btn-primary" type="submit">Send the link</button></form>
  <p style="margin-top:16px"><a href="<?= e(\Selfcare\url('login')) ?>">Back to sign in</a></p><?php endif; ?>
</div></section>
