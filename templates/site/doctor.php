<?php // Selfcare original template. Variables: $doc, $vendor, $base, $profile
use function Selfcare\{e, icon, tone, initials, money};
[$h1] = tone((string) $doc['id'] . $doc['first_name']); ?>
<section class="sc-section"><div class="sc-wrap" style="max-width:760px">
  <p><a href="<?= e($base('')) ?>#team">&larr; <?= e($vendor['name']) ?></a></p>
  <div class="vs-doc" style="margin:16px 0 24px"><div class="av" style="background:hsl(<?= $h1 ?> 50% 40%);width:84px;height:84px;font-size:1.6rem"><?= e(initials($doc['first_name'] . ' ' . $doc['last_name'])) ?></div><div><h1 style="font-size:2rem;margin:0">Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?></h1><p class="sc-lead" style="margin:4px 0 0"><?= e((string) $doc['specialist']) ?></p></div></div>
  <div class="sc-meta" style="margin-bottom:18px"><?php if ($doc['department']): ?><span class="sc-tag"><?= e($doc['department']) ?></span><?php endif; ?><?php if ($doc['qualification']): ?><span class="sc-tag"><?= e($doc['qualification']) ?></span><?php endif; ?><?php if ((float) $doc['appointment_charge'] > 0): ?><span class="sc-tag">Fee <?= e(money((float) $doc['appointment_charge'])) ?></span><?php endif; ?></div>
  <?php if ($doc['description']): ?><?= \Selfcare\nl2p((string) $doc['description']) ?><?php endif; ?>
  <p class="sc-cta"><a class="sc-btn sc-btn-primary" href="<?= e($base('')) ?>#request">Book an appointment</a></p>
</div></section>
