<?php // Selfcare original template. Public layout. Variables: $title, $content, $user, optional $seo, $vendor
use function Selfcare\{asset, e, icon, url};
$app = \Selfcare\app();
$S = static fn (string $k, string $d = '') => $app->site->get(null, $k, $d);
$cfg = require dirname(__DIR__) . '/core/config/site.php';
$name = $S('site_name', 'Self Care');
$logo = $S('logo') !== '' ? \Selfcare\upload_url($S('logo')) : asset('img/logo-mark.svg');
$cols = $cfg['footer_columns'];
$custom = json_decode($S('footer_json'), true);
if (is_array($custom) && $custom) { $cols = $custom; }
$link = static fn (string $u): string => preg_match('~^(https?:|mailto:|tel:)~', $u) ? $u : (str_starts_with($u, '/') ? $app->tenant->mainUrl(ltrim($u, '/')) : $u);
$desc = $S('seo_description');
$flash = \Selfcare\flash();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((string) ($title ?? $name)) ?><?= !empty($seo) ? '' : ' | ' . e($name) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0e7c6f">
<link rel="icon" href="<?= e(asset('img/favicon.ico')) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/favicon-180.png')) ?>">
<script>try{var t=localStorage.getItem('sc-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}document.documentElement.classList.add('js')</script>
<link rel="stylesheet" href="<?= e(asset('css/selfcare-ui.css')) ?>">
<?php if (!empty($extraCss)): ?><link rel="stylesheet" href="<?= e(asset($extraCss)) ?>"><?php endif; ?>
<script src="<?= e(asset('js/selfcare-app.js')) ?>" defer></script>
</head>
<body>
<a class="sc-skip" href="#main">Skip to content</a>
<header class="sc-head" data-sc-head>
  <div class="sc-head-in">
    <a class="sc-brand" href="<?= e($app->tenant->mainUrl('')) ?>" aria-label="<?= e($name) ?> home"><img src="<?= e($logo) ?>" width="34" height="34" alt=""><span><?= e($name) ?></span></a>
    <button class="sc-burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="sc-nav" data-sc-burger><?= icon('menu', 22) ?></button>
    <nav class="sc-nav" id="sc-nav" aria-label="Main">
      <a href="<?= e($app->tenant->mainUrl('') . '#who') ?>">Who we serve</a>
      <a href="<?= e($app->tenant->mainUrl('directory')) ?>">Directory</a>
      <a href="<?= e($app->tenant->mainUrl('free-services')) ?>">Free services</a>
      <a href="<?= e($app->tenant->mainUrl('') . '#pricing') ?>">Pricing</a>
      <button class="sc-theme" type="button" data-theme-toggle aria-label="Switch light or dark mode"><span class="i-moon"><?= icon('moon', 18) ?></span><span class="i-sun"><?= icon('sun', 18) ?></span></button>
      <?php if (!empty($user)): ?>
        <a class="sc-btn sc-btn-primary sc-btn-sm" href="<?= e($app->home($user)) ?>">Open dashboard</a>
      <?php else: ?>
        <a href="<?= e($app->tenant->mainUrl('login')) ?>">Sign in</a>
        <a class="sc-btn sc-btn-primary sc-btn-sm" href="<?= e($app->tenant->mainUrl('register')) ?>">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="main">
<?php if ($flash): ?><div class="sc-wrap" style="padding-top:14px"><div class="sc-alert <?= e($flash['t']) ?>" role="status"><?= e($flash['m']) ?></div></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="sc-foot">
  <div class="sc-wrap">
    <div class="sc-foot-grid">
      <div>
        <a class="sc-brand" href="<?= e($app->tenant->mainUrl('')) ?>"><img src="<?= e($logo) ?>" width="30" height="30" alt=""><span><?= e($name) ?></span></a>
        <p style="margin-top:12px"><?= e($S('footer_note')) ?></p>
        <?php if ($S('contact_email') || $S('contact_phone')): ?><p><?= e(trim($S('contact_email') . ($S('contact_email') && $S('contact_phone') ? ' · ' : '') . $S('contact_phone'))) ?></p><?php endif; ?>
        <form class="sc-news" method="post" action="<?= e($app->tenant->mainUrl('newsletter')) ?>"><?= \Selfcare\csrf_field() ?><input type="email" name="email" required placeholder="Your email" aria-label="Email for the newsletter"><button class="sc-btn sc-btn-sm" type="submit">Join</button></form>
        <p style="font-size:.8rem;margin:6px 0 0"><?= e($S('newsletter_text')) ?></p>
        <div class="sc-social"><?= $app->view->partial('partials/social', ['links' => ['facebook' => $S('social_facebook'), 'x' => $S('social_x'), 'linkedin' => $S('social_linkedin'), 'youtube' => $S('social_youtube'), 'instagram' => $S('social_instagram')]]) ?></div>
      </div>
      <?php foreach ($cols as $head => $links): ?>
      <div><h4><?= e((string) $head) ?></h4><ul><?php foreach ($links as $l): ?><li><a href="<?= e($link((string) $l[1])) ?>"><?= e((string) $l[0]) ?></a></li><?php endforeach; ?></ul></div>
      <?php endforeach; ?>
    </div>
    <div class="sc-foot-base"><span>&copy; <?= date('Y') ?> <?= e($name) ?>. All rights reserved.</span><span>Made for hospitals, clinics, labs, pharmacies and care providers.</span></div>
  </div>
</footer>
</body>
</html>
