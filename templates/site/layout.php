<?php // Selfcare original template. Layout of a vendor website. Variables: $vendor, $profile, $tpl, $base, $user, $title, $content
use function Selfcare\{asset, e, icon, upload_url};
$app = \Selfcare\app();
$flash = \Selfcare\flash();
$logo = $profile['logo'] !== '' ? upload_url($profile['logo']) : asset('img/logo-mark.svg');
$main = static fn (string $p = ''): string => $app->tenant->mainUrl($p);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((string) $title) ?></title>
<meta name="description" content="<?= e($profile['tagline'] ?: $vendor['name']) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.ico')) ?>" sizes="any">
<script>try{var t=localStorage.getItem('sc-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}document.documentElement.classList.add('js')</script>
<link rel="stylesheet" href="<?= e(asset('css/selfcare-ui.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/selfcare-dash.css')) ?>">
<script src="<?= e(asset('js/selfcare-app.js')) ?>" defer></script>
</head>
<body>
<a class="sc-skip" href="#main">Skip to content</a>
<header class="sc-head" data-sc-head><div class="sc-head-in">
  <a class="sc-brand" href="<?= e($base('')) ?>"><img src="<?= e($logo) ?>" width="34" height="34" alt=""><span><?= e($vendor['name']) ?></span></a>
  <button class="sc-burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="sc-nav" data-sc-burger><?= icon('menu', 22) ?></button>
  <nav class="sc-nav" id="sc-nav" aria-label="Main">
    <?php foreach ($tpl['sections'] as $s): if (in_array($s['kind'], ['cards', 'team', 'departments', 'hours'], true) && !empty($data[$s['key']])): ?><a href="#<?= e($s['key']) ?>"><?= e((string) $s['label']) ?></a><?php endif; endforeach; ?>
    <a href="#contact">Contact</a>
    <button class="sc-theme" type="button" data-theme-toggle aria-label="Switch light or dark mode"><span class="i-moon"><?= icon('moon', 18) ?></span><span class="i-sun"><?= icon('sun', 18) ?></span></button>
    <div class="sc-nav-cta">
      <a class="sc-btn sc-btn-primary sc-btn-sm" href="#request"><?= e((string) $tpl['request']['cta']) ?></a>
      <a class="sc-btn sc-btn-soft sc-btn-sm" href="<?= e($user ? $portalUrl : \Selfcare\url('login')) ?>"><?= $user ? 'Portal' : 'Sign in' ?></a>
    </div>
  </nav>
</div></header>
<main id="main">
<?php if ($flash): ?><div class="sc-wrap" style="padding-top:14px"><div class="sc-alert <?= e($flash['t']) ?>" role="status"><?= e($flash['m']) ?></div></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="sc-foot"><div class="sc-wrap">
  <div class="sc-foot-grid" style="grid-template-columns:2fr 1fr 1fr">
    <div><b><?= e($vendor['name']) ?></b><p><?= e($profile['tagline']) ?></p><?php if ($profile['address'] || $profile['city']): ?><p><?= icon('pin', 14) ?> <?= e(trim($profile['address'] . ' ' . $profile['city'])) ?></p><?php endif; ?>
      <div class="sc-social"><?= $app->view->partial('partials/social', ['links' => ['facebook' => $profile['facebook'], 'x' => $profile['x'], 'linkedin' => $profile['linkedin'], 'youtube' => $profile['youtube'], 'instagram' => $profile['instagram']]]) ?></div></div>
    <div><h4>Visit</h4><ul><?php foreach ($tpl['sections'] as $s): if (in_array($s['kind'], ['cards', 'team', 'hours'], true)): ?><li><a href="#<?= e($s['key']) ?>"><?= e((string) $s['label']) ?></a></li><?php endif; endforeach; ?><li><a href="#contact">Contact</a></li></ul></div>
    <div><h4>Self Care</h4><ul><li><a href="<?= e($main('directory')) ?>">Provider directory</a></li><li><a href="<?= e($main('free-services')) ?>">Free services</a></li><li><a href="<?= e($main('')) ?>">About Self Care</a></li></ul></div>
  </div>
  <div class="sc-foot-base"><span>&copy; <?= date('Y') ?> <?= e($vendor['name']) ?></span><span>Powered by <a href="<?= e($main('')) ?>">Self Care</a></span></div>
</div></footer>
</body></html>
