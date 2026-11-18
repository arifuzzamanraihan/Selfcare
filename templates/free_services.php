<?php // Selfcare original template. Variables: $types, $type, $q, $items
use function Selfcare\{e, icon};
$app = \Selfcare\app(); $self = $app->tenant->mainUrl('free-services'); ?>
<section class="sc-section"><div class="sc-wrap">
  <span class="sc-eyebrow">Free services</span><h1 style="font-size:2.2rem"><?= e($app->site->get(null, 'free_title')) ?></h1>
  <p class="sc-lead"><?= e($app->site->get(null, 'free_text')) ?></p>
  <form class="sc-search" method="get" action="<?= e($self) ?>" style="margin-top:24px"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search free services" aria-label="Search"><select name="type" aria-label="Kind of provider"><option value="">All kinds of provider</option><?php foreach ($types as $t): ?><option value="<?= e($t['slug']) ?>"<?= $type === $t['slug'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select><button class="sc-btn sc-btn-primary" type="submit">Search</button></form>
  <?php if ($items): ?><div class="sc-grid g3"><?php foreach ($items as $f): ?>
    <a class="sc-card" href="<?= e($app->tenant->siteUrl((string) $f['slug'])) ?>#free"><div class="sc-ico"><?= icon((string) ($f['icon'] ?: 'heart'), 22) ?></div><h3><?= e($f['title']) ?></h3><p><?= e(mb_substr((string) $f['description'], 0, 160)) ?></p>
    <div class="sc-meta"><span class="sc-tag"><?= e($f['vendor_name']) ?></span><span class="sc-tag"><?= e((string) $f['type_name']) ?></span><?php if ($f['schedule']): ?><span><?= icon('clock', 14) ?> <?= e($f['schedule']) ?></span><?php endif; ?><?php if ($f['eligibility']): ?><span>For: <?= e($f['eligibility']) ?></span><?php endif; ?></div></a>
  <?php endforeach; ?></div>
  <?php else: ?><div class="sc-card sc-center" style="padding:44px"><?= $app->view->partial('partials/art', ['name' => 'empty']) ?><h3>No free services listed yet</h3><p>Providers can add free camps, delivery and more from their portal.</p></div><?php endif; ?>
</div></section>
