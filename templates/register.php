<?php // Selfcare original template. Variables: $types, $pick, $suffix, $plans, $error
use function Selfcare\{e, csrf_field, icon, old};
$app = \Selfcare\app(); $pick = old('type', $pick); ?>
<section class="sc-auth"><div class="sc-auth-card wide">
  <h1 style="font-size:1.7rem">Register your organisation</h1>
  <p class="d-mute">Your website goes live on its own address right away. Start free, change the plan any time.</p>
  <?php if (!empty($error)): ?><div class="sc-alert bad" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('register')) ?>"><?= csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div><b style="font-size:.92rem">What kind of organisation are you?</b>
      <div class="sc-types" style="margin-top:8px"><?php foreach ($types as $t): ?><label><input type="radio" name="type" value="<?= e($t['slug']) ?>" required<?= $pick === $t['slug'] ? ' checked' : '' ?>><?= icon((string) $t['icon'], 18) ?><?= e($t['name']) ?></label><?php endforeach; ?></div></div>
    <div class="sc-grid g2">
      <label>Organisation name<input name="name" required minlength="3" maxlength="120" value="<?= e(old('name')) ?>" placeholder="City Care Hospital"></label>
      <label>Web address <small><?= $suffix !== '' ? 'your-name' . e($suffix) : 'letters, numbers, dashes' ?></small><input name="slug" required minlength="3" maxlength="40" pattern="[a-zA-Z0-9\-]+" value="<?= e(old('slug')) ?>" placeholder="citycare"></label>
      <label>Your first name<input name="first_name" required value="<?= e(old('first_name')) ?>" autocomplete="given-name"></label>
      <label>Your last name<input name="last_name" value="<?= e(old('last_name')) ?>" autocomplete="family-name"></label>
      <label>Email (you will sign in with it)<input type="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="username"></label>
      <label>Phone<input type="tel" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel"></label>
      <label>City<input name="city" value="<?= e(old('city')) ?>" autocomplete="address-level2"></label><span></span>
      <label>Password <small>at least 8 characters</small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label>Repeat the password<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    </div>
    <button class="sc-btn sc-btn-primary sc-btn-lg" type="submit">Create my account</button>
    <p class="d-mute" style="font-size:.85rem;margin:0">By registering you agree to the <a href="<?= e($app->tenant->mainUrl('terms')) ?>">terms</a> and <a href="<?= e($app->tenant->mainUrl('privacy')) ?>">privacy policy</a>.</p>
  </form>
</div></section>
<?php \Selfcare\clear_old(); ?>
