<?php // Selfcare original template. One page per vendor type. Variables: $type, $tpl, $vendors, $others, $plans
use function Selfcare\{e, icon, tone, initials};
$app = \Selfcare\app(); $main = static fn (string $p = ''): string => $app->tenant->mainUrl($p);
$serves = array_filter(array_map('trim', explode("\n", (string) $type['serves'])));
?>
<section class="sc-hero"><div class="sc-wrap sc-hero-grid">
  <div class="rv"><span class="sc-badge"><?= icon((string) $type['icon'], 16) ?> <?= e($type['name']) ?></span><h1><?= e($type['name']) ?> website and portal</h1><p class="sc-lead"><?= e($type['description']) ?></p>
    <div class="sc-cta"><a class="sc-btn sc-btn-primary sc-btn-lg" href="<?= e($main('register?type=' . $type['slug'])) ?>">Register a <?= e(strtolower((string) $type['name'])) ?></a><a class="sc-btn sc-btn-lg" href="<?= e($main('directory?type=' . $type['slug'])) ?>">See registered</a></div></div>
  <div class="rv"><?= $app->view->partial('partials/art', ['name' => 'hero']) ?></div>
</div></section>
<section class="sc-section alt"><div class="sc-wrap"><div class="sc-grid g2">
  <div class="sc-card"><h3>What your website has</h3><ul style="margin:0;padding-left:18px;line-height:1.9"><?php foreach ($tpl['sections'] as $s): ?><li><?= e((string) $s['label']) ?></li><?php endforeach; ?></ul></div>
  <div class="sc-card"><h3>What your portal handles</h3><ul style="margin:0;padding-left:18px;line-height:1.9"><li><?= e((string) $tpl['requests']) ?> from your website</li><li><?= e((string) $tpl['team']) ?> and staff accounts with their own roles</li><?php if (!empty($tpl['people'])): ?><li><?= e((string) $tpl['people']) ?></li><?php endif; ?><li>Invoices, receipts and printed forms as PDF</li><li>Reports for your day, month and stock</li><li>Free services shown on your site and the main site</li></ul></div>
</div></div></section>
<?php if ($serves): ?><section class="sc-section"><div class="sc-wrap"><h2>Who it is for</h2><div class="sc-chips"><?php foreach ($serves as $s): ?><span class="sc-chip"><?= e($s) ?></span><?php endforeach; ?></div></div></section><?php endif; ?>
<section class="sc-section alt"><div class="sc-wrap"><h2>Registered <?= e(strtolower((string) $type['plural'])) ?></h2>
  <?php if ($vendors): ?><div class="sc-grid g3"><?php foreach ($vendors as $v): [$h1] = tone((string) $v['slug']); ?><a class="sc-card" href="<?= e($app->tenant->siteUrl((string) $v['slug'])) ?>"><div class="sc-vendor"><div class="av" style="background:hsl(<?= $h1 ?> 55% 40%)"><?= e(initials((string) $v['name'])) ?></div><div><h3><?= e($v['name']) ?></h3><p><?= e((string) $v['tagline']) ?></p></div></div><div class="sc-meta"><?php if ($v['city']): ?><span><?= icon('pin', 14) ?> <?= e($v['city']) ?></span><?php endif; ?></div></a><?php endforeach; ?></div>
  <?php else: ?><p class="d-mute">Be the first <?= e(strtolower((string) $type['name'])) ?> on Self Care.</p><?php endif; ?></div></section>
<section class="sc-section"><div class="sc-wrap"><h2>Other kinds of provider</h2><div class="sc-chips"><?php foreach ($others as $o): ?><a class="sc-chip" href="<?= e($main('types/' . $o['slug'])) ?>"><?= e($o['name']) ?></a><?php endforeach; ?></div></div></section>
