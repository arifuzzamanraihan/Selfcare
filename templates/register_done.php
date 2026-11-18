<?php // Selfcare original template. Shown after a public registration is sent. Variables: $name
use function Selfcare\e; ?>
<section class="sc-auth"><div class="sc-auth-card">
  <h1 style="font-size:1.6rem">Thank you. Your request is with us.</h1>
  <div class="sc-alert ok" role="status">We have your registration for <b><?= e((string) ($name ?? '')) ?></b>. Our team checks every new organisation before it goes live. You will get an email as soon as it is approved, and you can then sign in with the email and password you just chose.</div>
  <p><a href="<?= e(\Selfcare\url('')) ?>">Back to the home page</a></p>
</div></section>
