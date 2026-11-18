<?php // Selfcare original template. Variables: $types, $type, $q, $vendors
use function Selfcare\{e, icon, tone, initials};
$app = \Selfcare\app(); $self = $app->tenant->mainUrl('directory'); ?>
<section class="sc-section"><div class="sc-wrap">
  <span class="sc-eyebrow">Directory</span><h1 style="font-size:2.2rem"><?= e($app->site->get(null, 'directory_title')) ?></h1>
  <p class="sc-lead"><?= e($app->site->get(null, 'directory_text')) ?></p>
  <form class="sc-search" method="get" action="<?= e($self) ?>" style="margin-top:24px"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or city" aria-label="Search"><?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?><button class="sc-btn sc-btn-primary" type="submit">Search</button></form>
  <div class="sc-chips"><a class="sc-chip<?= $type === '' ? ' is-on' : '' ?>" href="<?= e($self . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>">All</a><?php foreach ($types as $t): ?><a class="sc-chip<?= $type === $t['slug'] ? ' is-on' : '' ?>" href="<?= e($self . '?type=' . $t['slug'] . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"><?= e($t['name']) ?></a><?php endforeach; ?></div>
  <?php if ($vendors): ?><div class="sc-grid g3"><?php foreach ($vendors as $v): [$h1] = tone((string) $v['slug']); ?>
    <a class="sc-card" href="<?= e($app->tenant->siteUrl((string) $v['slug'])) ?>">
      <div class="sc-vendor"><div class="av" style="background:hsl(<?= $h1 ?> 55% 40%)"><?php if ($v['cover']): ?><img src="<?= e(\Selfcare\upload_url($v['cover'])) ?>" alt=""><?php else: ?><?= e(initials((string) $v['name'])) ?><?php endif; ?></div><div><h3><?= e($v['name']) ?></h3><p><?= e((string) $v['tagline']) ?></p></div></div>
      <div class="sc-meta"><span class="sc-tag"><?= e((string) $v['type_name']) ?></span><?php if ($v['city']): ?><span><?= icon('pin', 14) ?> <?= e($v['city']) ?></span><?php endif; ?><?php if ($v['phone']): ?><span><?= icon('phone', 14) ?> <?= e($v['phone']) ?></span><?php endif; ?></div>
    </a><?php endforeach; ?></div>
  <?php else: ?><div class="sc-card sc-center" style="padding:44px"><?= $app->view->partial('partials/art', ['name' => 'empty']) ?><h3>No providers found</h3><p>Try another word or kind of provider, or <a href="<?= e($app->tenant->mainUrl('register')) ?>">register yours</a>.</p></div><?php endif; ?>
</div></section>
