<?php // Selfcare original template. Variables: $code, $message, $title
use function Selfcare\e;
$app = \Selfcare\app(); ?>
<section class="sc-section"><div class="sc-wrap sc-center">
  <p class="sc-eyebrow">Error <?= (int) $code ?></p>
  <h1 style="font-size:2.4rem"><?= e((string) $title) ?></h1>
  <p class="sc-lead"><?= e((string) $message) ?></p>
  <p class="sc-cta" style="justify-content:center"><a class="sc-btn sc-btn-primary" href="<?= e($app->tenant->mainUrl('')) ?>">Go to the home page</a><a class="sc-btn" href="javascript:history.back()">Go back</a></p>
</div></section>
