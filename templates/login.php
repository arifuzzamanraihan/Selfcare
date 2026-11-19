<?php // Selfcare original template. Variables: $vendor (null on the main site), $error
use function Selfcare\{e, csrf_field, asset, upload_url};
$app = \Selfcare\app(); $next = (string) ($_GET['next'] ?? '');
?>
<section class="sc-auth"><div class="sc-auth-card rv in">
  <h1 style="font-size:1.7rem"><?= $vendor ? 'Sign in to ' . e($vendor['name']) : 'Sign in' ?></h1>
  <p class="d-mute"><?= $vendor ? 'Staff and patients of this organisation.' : 'Use the email and password of your Self Care account.' ?></p>
  <?php if (!empty($error)): ?><div class="sc-alert bad" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('login')) ?>"><?= csrf_field() ?><?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
    <label>Email<input type="email" name="email" required autocomplete="username" value="<?= e(\Selfcare\old('email')) ?>" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="sc-btn sc-btn-primary" type="submit">Sign in</button>
  </form>
  <p style="margin-top:16px"><a href="<?= e(\Selfcare\url('forgot')) ?>">Forgot your password?</a></p>
  <?php if (!$vendor): ?><p class="d-mute">New here? <a href="<?= e($app->tenant->mainUrl('register')) ?>">Register your organisation</a></p><?php endif; ?>
</div></section>
<?php \Selfcare\clear_old(); ?>
