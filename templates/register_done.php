<?php // Selfcare original template. Shown after a public registration is sent. Variables: $name, $email, $resent
use function Selfcare\{e, csrf_field}; ?>
<section class="sc-auth"><div class="sc-auth-card">
  <h1 style="font-size:1.6rem">Check your email</h1>
  <div class="sc-alert ok" role="status"><?= !empty($resent) ? 'If that address is waiting for confirmation, we sent a new link.' : 'We have your registration' . (($name ?? '') !== '' ? ' for <b>' . e((string) $name) . '</b>' : '') . '.' ?> We sent a confirmation link to <b><?= e((string) ($email ?? '')) ?></b>. Open it to confirm your email. After that our team checks every new organisation before it goes live, and you get another email when it is approved.</div>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('register/resend')) ?>"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e((string) ($email ?? '')) ?>">
    <p class="d-mute">No email after a few minutes? Check the spam folder, then</p>
    <button class="sc-btn sc-btn-soft" type="submit">Send the link again</button></form>
  <p><a href="<?= e(\Selfcare\url('')) ?>">Back to the home page</a></p>
</div></section>
