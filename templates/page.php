<?php // Selfcare original template. Terms and privacy. Variables: $heading, $slug
use function Selfcare\e;
$app = \Selfcare\app();
$custom = $app->site->get(null, $slug . '_text');
?>
<section class="sc-section"><div class="sc-wrap sc-prose">
  <h1 style="font-size:2.2rem"><?= e($heading) ?></h1>
  <?php if ($custom !== ''): ?><?= \Selfcare\nl2p($custom) ?><?php elseif ($slug === 'terms'): ?>
  <p>These terms describe how healthcare providers use Self Care. By registering you agree to use the service lawfully and to keep your sign in details private.</p>
  <h2>Your account</h2><p>You are responsible for the people you give access to. Remove access when someone leaves. Give staff only the role they need.</p>
  <h2>Plans</h2><p>Each plan has limits and an end date. When a plan ends, your account becomes read-only until it is renewed. Your data is not deleted.</p>
  <h2>Your data</h2><p>Records you enter belong to your organisation. You must have the right to store patient information and follow the laws that apply to you.</p>
  <?php else: ?>
  <p>Self Care stores the information that providers and patients enter, so that appointments, billing and records work.</p>
  <h2>What we keep</h2><p>Account details, the records a provider enters, and basic technical logs that protect the service from abuse.</p>
  <h2>Who can see it</h2><p>Only the people of the provider that owns a record, according to their role. Self Care staff do not read records unless you ask for support.</p>
  <h2>Your choices</h2><p>You can ask your provider to correct or export your information. Write to us using the contact details in the footer.</p>
  <?php endif; ?>
  <p class="d-mute" style="font-size:.85rem">The super admin can replace this text in the website content settings.</p>
</div></section>
