<?php // Selfcare original template. Main website home. Variables: $types, $typeCounts, $vendors, $free, $plans, $stats
use function Selfcare\{e, icon, upload_url, tone, initials, money};
$app = \Selfcare\app();
$S = static fn (string $k) => $app->site->get(null, $k);
$main = static fn (string $p = ''): string => $app->tenant->mainUrl($p);
$title_html = e($S('hero_title'));
$acc = $S('hero_title_accent');
if ($acc !== '') { $title_html = str_replace(e($acc), '<span class="acc">' . e($acc) . '</span>', $title_html); }
?>
<section class="sc-hero"><div class="sc-wrap sc-hero-grid">
  <div class="rv">
    <span class="sc-badge"><i></i><?= e($S('hero_badge')) ?></span>
    <h1><?= $title_html ?></h1>
    <p class="sc-lead"><?= e($S('hero_text')) ?></p>
    <div class="sc-cta"><a class="sc-btn sc-btn-primary sc-btn-lg" href="<?= e($main('register')) ?>"><?= e($S('hero_cta')) ?> <?= icon('arrow', 18) ?></a><a class="sc-btn sc-btn-lg" href="<?= e($main('directory')) ?>">Find a provider</a></div>
  </div>
  <div class="rv"><?= $app->view->partial('partials/art', ['name' => 'hero']) ?></div>
</div></section>

<div class="sc-marquee" aria-hidden="true"><div><?php for ($i = 0; $i < 2; $i++) { foreach ($types as $t) { echo '<span>' . e($t['name']) . '</span>'; } } ?></div></div>

<section class="sc-section" id="who"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">Who we serve</span><h2><?= e($S('who_title')) ?></h2><p class="sc-lead"><?= e($S('who_text')) ?></p></div>
  <div class="sc-grid g4" style="margin-top:32px">
    <?php foreach ($types as $t): ?>
    <a class="sc-card rv" href="<?= e($main('types/' . $t['slug'])) ?>"><div class="sc-ico"><?= icon((string) $t['icon'], 22) ?></div><h3><?= e($t['name']) ?></h3><p><?= e($t['tagline']) ?></p><div class="sc-meta"><span><?= (int) ($typeCounts[$t['slug']] ?? 0) ?> registered</span></div></a>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="sc-section alt" id="features"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">Features</span><h2><?= e($S('features_title')) ?></h2></div>
  <div class="sc-grid g3" style="margin-top:32px"><?php foreach ($app->site->items(null, 'features') as $f): ?>
    <div class="sc-card rv"><div class="sc-ico"><?= icon((string) ($f['meta'] ?: 'layers'), 22) ?></div><h3><?= e($f['title']) ?></h3><p><?= e((string) $f['body']) ?></p></div>
  <?php endforeach; ?></div>
</div></section>

<section class="sc-section" id="how"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">How it works</span><h2><?= e($S('steps_title')) ?></h2></div>
  <div class="rv" style="margin:24px 0"><?= $app->view->partial('partials/art', ['name' => 'how']) ?></div>
  <div class="sc-steps"><?php foreach ($app->site->items(null, 'steps') as $s): ?><div class="sc-step rv"><h3><?= e($s['title']) ?></h3><p class="d-mute"><?= e((string) $s['body']) ?></p></div><?php endforeach; ?></div>
</div></section>

<?php $vc = \Selfcare\Currency::visitor(); ?>
<?= $app->view->partial('partials/pricing', ['plans' => $plans, 'audiences' => $audiences ?? [], 'S' => $S, 'main' => $main, 'vc' => $vc]) ?>

<section class="sc-section alt" id="providers"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">Registered providers</span><h2><?= e($S('directory_title')) ?></h2><p class="sc-lead"><?= e($S('directory_text')) ?></p></div>
  <?php if ($vendors): ?><div class="sc-grid g3" style="margin-top:32px"><?php foreach ($vendors as $v): [$h1] = tone((string) $v['slug']); ?>
    <a class="sc-card rv" href="<?= e($app->tenant->siteUrl((string) $v['slug'])) ?>"><div class="sc-vendor"><div class="av" style="background:hsl(<?= $h1 ?> 55% 40%)"><?= e(initials((string) $v['name'])) ?></div><div><h3><?= e($v['name']) ?></h3><p><?= e((string) $v['tagline']) ?></p></div></div><div class="sc-meta"><span class="sc-tag"><?= e((string) $v['type_name']) ?></span><?php if ($v['city']): ?><span><?= icon('pin', 14) ?> <?= e($v['city']) ?></span><?php endif; ?></div></a>
  <?php endforeach; ?></div>
  <p class="sc-center" style="margin-top:24px"><a class="sc-btn" href="<?= e($main('directory')) ?>">See all providers</a></p>
  <?php else: ?><p class="sc-center d-mute" style="margin-top:24px">The first providers will appear here once they register.</p><?php endif; ?>
</div></section>

<?php if ($free): ?>
<section class="sc-section" id="free"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">Free services</span><h2><?= e($S('free_title')) ?></h2><p class="sc-lead"><?= e($S('free_text')) ?></p></div>
  <div class="sc-grid g3" style="margin-top:32px"><?php foreach ($free as $f): ?>
    <a class="sc-card rv" href="<?= e($app->tenant->siteUrl((string) $f['slug'])) ?>#free"><div class="sc-ico"><?= icon((string) ($f['icon'] ?: 'heart'), 22) ?></div><h3><?= e($f['title']) ?></h3><p><?= e(mb_substr((string) $f['description'], 0, 120)) ?></p><div class="sc-meta"><span class="sc-tag"><?= e($f['vendor_name']) ?></span><?php if ($f['schedule']): ?><span><?= icon('clock', 14) ?> <?= e($f['schedule']) ?></span><?php endif; ?></div></a>
  <?php endforeach; ?></div>
  <p class="sc-center" style="margin-top:24px"><a class="sc-btn" href="<?= e($main('free-services')) ?>">All free services</a></p>
</div></section>
<?php endif; ?>

<?php if ($app->site->items(null, 'testimonials')): ?><section class="sc-section alt" id="testimonials"><div class="sc-wrap">
  <div class="sc-grid g3"><?php foreach ($app->site->items(null, 'testimonials') as $t): ?>
    <figure class="sc-card rv" style="margin:0"><p style="color:var(--ink)">&ldquo;<?= e((string) $t['body']) ?>&rdquo;</p><figcaption class="sc-meta"><b style="color:var(--ink)"><?= e($t['title']) ?></b> <?= e((string) $t['meta']) ?></figcaption></figure>
  <?php endforeach; ?></div>
</div></section><?php endif; ?>

<section class="sc-section" id="faq"><div class="sc-wrap" style="max-width:780px">
  <div class="sc-center rv"><span class="sc-eyebrow">FAQ</span><h2><?= e($S('faq_title')) ?></h2></div>
  <div class="sc-faq rv" style="margin-top:24px"><?php foreach ($app->site->items(null, 'faq') as $q): ?><details><summary><?= e($q['title']) ?></summary><p><?= e((string) $q['body']) ?></p></details><?php endforeach; ?></div>
</div></section>

<section class="sc-section alt" id="contact"><div class="sc-wrap" style="max-width:640px">
  <div class="sc-center rv"><h2>Talk to us</h2><p class="sc-lead">Questions about plans or a custom set-up? Send a message.</p></div>
  <form class="sc-form rv" method="post" action="<?= e($main('contact')) ?>" style="margin-top:20px"><?= \Selfcare\csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="sc-grid g2"><label>Name<input name="name" required maxlength="160" value="<?= e(\Selfcare\old('name')) ?>"></label><label>Email<input type="email" name="email" required value="<?= e(\Selfcare\old('email')) ?>"></label></div>
    <label>Message<textarea name="message" rows="4" required maxlength="3000"><?= e(\Selfcare\old('message')) ?></textarea></label>
    <button class="sc-btn sc-btn-primary" type="submit">Send message</button>
  </form>
</div></section>

<section class="sc-section" style="padding-top:0"><div class="sc-wrap"><div class="sc-card sc-center rv" style="padding:44px"><h2><?= e($S('cta_title')) ?></h2><p class="sc-lead"><?= e($S('cta_text')) ?></p><p class="sc-cta" style="justify-content:center"><a class="sc-btn sc-btn-primary sc-btn-lg" href="<?= e($main('register')) ?>"><?= e($S('hero_cta')) ?></a></p></div></div></section>
<?php \Selfcare\clear_old(); ?>
