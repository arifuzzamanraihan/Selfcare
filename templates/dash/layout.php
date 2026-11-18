<?php // Selfcare original template. BoardUI dashboard shell (vendor portal and super admin).
// Variables: $title, $sub, $actions, $nav, $active, $orgName, $orgSub, $user, $roleLabel, $content, $banner
use function Selfcare\{asset, e, icon, initials, csrf_field, upload_url};
$app = \Selfcare\app();
$flash = \Selfcare\flash();
$logo = !empty($orgLogo) ? upload_url($orgLogo) : asset('img/logo-mark.svg');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((string) $title) ?> | <?= e((string) $orgName) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" href="<?= e(asset('img/favicon.ico')) ?>" sizes="any">
<script>try{var t=localStorage.getItem('sc-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}document.documentElement.classList.add('js')</script>
<link rel="stylesheet" href="<?= e(asset('css/selfcare-ui.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/selfcare-dash.css')) ?>">
<script src="<?= e(asset('js/selfcare-app.js')) ?>" defer></script><script src="<?= e(asset('js/selfcare-docs.js')) ?>" defer></script><script src="<?= e(asset('js/selfcare-rx.js')) ?>" defer></script><script src="<?= e(asset('js/ops-queue.js')) ?>" defer></script>
<?= $head ?? '' ?>
</head>
<body class="d-body">
<a class="sc-skip" href="#main">Skip to content</a>
<?php if (!empty($impBanner)): ?><div class="d-imp" role="alert"><?= $impBanner ?></div><?php endif; ?>
<div class="d-scrim" data-side-scrim aria-hidden="true"></div>
<div class="d-shell">
  <aside class="d-side" id="d-side" data-side aria-label="Menu">
    <a class="d-side-brand" href="<?= e(\Selfcare\url($navHome ?? 'portal')) ?>"><img src="<?= e($logo) ?>" width="32" height="32" alt=""><span>Self Care</span></a>
    <div class="d-org"><b><?= e((string) $orgName) ?></b><span><?= e((string) $orgSub) ?></span></div>
    <nav>
      <?php foreach ($nav as $g): ?><div class="d-nav-g"><h5><?= e((string) $g['group']) ?></h5><?php foreach ($g['items'] as $i): ?><a href="<?= e($i['url']) ?>" class="<?= $active === $i['key'] ? 'is-on' : '' ?>"<?= $active === $i['key'] ? ' aria-current="page"' : '' ?>><?= icon((string) $i['icon'], 18) ?><span><?= e($i['label']) ?></span><?php if (!empty($i['badge'])): ?><em class="d-navbadge"><?= (int) $i['badge'] ?></em><?php endif; ?></a><?php endforeach; ?></div><?php endforeach; ?>
    </nav>
    <div class="d-side-foot">
      <?php if (!empty($siteLink)): ?><a class="d-btn d-btn-soft" href="<?= e($siteLink) ?>" target="_blank" rel="noopener"><?= icon('globe', 16) ?> View my website</a><?php endif; ?>
      <form method="post" action="<?= e(\Selfcare\url('logout')) ?>" class="d-inline"><?= csrf_field() ?><button class="d-btn d-btn-soft" style="width:100%" type="submit"><?= icon('logout', 16) ?> Sign out</button></form>
    </div>
  </aside>
  <div>
    <header class="d-top">
      <button class="d-burger" type="button" data-side-toggle aria-label="Open the menu" aria-expanded="false" aria-controls="d-side"><?= icon('menu', 20) ?></button>
      <h1><?= e((string) $title) ?></h1><span class="sp"></span>
      <button class="sc-theme" type="button" data-theme-toggle aria-label="Switch light or dark mode"><span class="i-moon"><?= icon('moon', 18) ?></span><span class="i-sun"><?= icon('sun', 18) ?></span></button>
      <div class="d-me"><i><?= e(initials(trim($user['first_name'] . ' ' . $user['last_name']))) ?></i><span><b><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?></b><br><small class="d-mute"><?= e((string) $roleLabel) ?></small></span></div>
    </header>
    <main class="d-main" id="main">
      <?php if ($flash): ?><div class="d-flash <?= e($flash['t']) ?>" role="status"><?= e($flash['m']) ?></div><?php endif; ?>
      <?php if (!empty($banner)): ?><div class="d-banner" role="alert"><?= $banner ?></div><?php endif; ?>
      <?php if (!empty($sub) || !empty($actions)): ?><div class="d-phead"><div><?php if (!empty($sub)): ?><p style="margin:0"><?= e((string) $sub) ?></p><?php endif; ?></div><div><?= $actions ?? '' ?></div></div><?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
</body></html>
