<?php // Selfcare original template. Variables: $token, $valid, $error
use function Selfcare\{e, csrf_field}; ?>
<section class="sc-auth"><div class="sc-auth-card">
  <h1 style="font-size:1.6rem">Choose a new password</h1>
  <?php if (empty($valid)): ?><div class="sc-alert bad" role="alert">This link is not valid any more. It may have expired or already been used.</div><p><a class="sc-btn" href="<?= e(\Selfcare\url('forgot')) ?>">Send a new link</a></p>
  <?php else: ?>
  <?php if (!empty($error)): ?><div class="sc-alert bad" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('reset')) ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password <small>at least 8 characters</small><input type="password" name="password" required minlength="8" autocomplete="new-password" autofocus></label>
    <label>Repeat the password<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="sc-btn sc-btn-primary" type="submit">Save password</button></form><?php endif; ?>
</div></section>
