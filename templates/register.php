<?php // Selfcare original template. Variables: $types, $pick, $suffix, $plans, $error
use function Selfcare\{e, csrf_field, icon, old};
$app = \Selfcare\app(); $pick = old('type', $pick); ?>
<section class="sc-auth"><div class="sc-auth-card wide">
  <h1 style="font-size:1.7rem">Register your organisation</h1>
  <p class="d-mute">Our team checks every new organisation, then your website and portal go live on your own address with a free HTTPS lock, usually within a day. Start free, change the plan any time.</p>
  <?php if (!empty($error)): ?><div class="sc-alert bad" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="sc-form" method="post" action="<?= e(\Selfcare\url('register')) ?>"><?= csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div><b style="font-size:.92rem">What kind of organisation are you?</b>
      <div class="sc-types" style="margin-top:8px"><?php foreach ($types as $t): ?><label><input type="radio" name="type" value="<?= e($t['slug']) ?>" required<?= $pick === $t['slug'] ? ' checked' : '' ?>><?= icon((string) $t['icon'], 18) ?><?= e($t['name']) ?></label><?php endforeach; ?></div></div>
    <?php $planPick = (int) old('plan_id', (string) ($_GET['plan'] ?? '0')); $periodPick = old('period', (string) ($_GET['period'] ?? 'monthly')) === 'yearly' ? 'yearly' : 'monthly'; ?>
    <div id="sc-regplans"><b style="font-size:.92rem">Choose your plan <small class="d-mute">you can change it later</small></b>
      <div class="sc-bill" role="group" aria-label="Billing period" style="margin:8px 0"><label class="sc-bill-b<?= $periodPick === 'monthly' ? ' on' : '' ?>"><input type="radio" name="period" value="monthly" <?= $periodPick === 'monthly' ? 'checked' : '' ?> hidden>Monthly</label><label class="sc-bill-b<?= $periodPick === 'yearly' ? ' on' : '' ?>"><input type="radio" name="period" value="yearly" <?= $periodPick === 'yearly' ? 'checked' : '' ?> hidden>Yearly</label></div>
      <div class="sc-pgrid" style="margin-top:6px"><?php foreach ((array) ($plans ?? []) as $pl): $vc = \Selfcare\Currency::visitor(); [$ma, $mc] = \Selfcare\Plans::price($pl, $vc); [$ya, $yc] = \Selfcare\Plans::yearPrice($pl, $vc); $free = (float) $pl['price'] == 0.0; ?>
        <label class="sc-card sc-regplan" data-types="<?= e((string) $pl['types']) ?>"><input type="radio" name="plan_id" value="<?= (int) $pl['id'] ?>" <?= $planPick === (int) $pl['id'] ? 'checked' : '' ?>> <b><?= e((string) $pl['name']) ?></b> <span class="d-mute"><?= e((string) ($pl['size_label'] ?? '')) ?></span><br><span class="sc-amt" data-m="<?= e($free ? 'Free' : \Selfcare\Currency::fmt($ma, $mc, true) . ' / month') ?>" data-y="<?= e($free ? 'Free' : ($ya > 0 ? \Selfcare\Currency::fmt($ya, $yc, true) . ' / year' : \Selfcare\Currency::fmt($ma, $mc, true) . ' / month')) ?>"><?= e($free ? 'Free' : \Selfcare\Currency::fmt($ma, $mc, true) . ' / month') ?></span></label>
      <?php endforeach; ?></div></div>
    <script>(function(){var box=document.getElementById('sc-regplans');if(!box)return;var f=box.closest('form');
    function types(){var c=f.querySelector('input[name=type]:checked');return c?c.value:''}
    function upd(){var t=types(),first=null,anyChecked=false;box.querySelectorAll('.sc-regplan').forEach(function(l){var ty=(l.getAttribute('data-types')||'').split(',').filter(Boolean);var show=!t||!ty.length||ty.indexOf(t)>-1;l.style.display=show?'':'none';var r=l.querySelector('input');if(!show&&r.checked)r.checked=false;if(show&&!first)first=r;if(r.checked)anyChecked=true});if(!anyChecked&&first)first.checked=true;
      var per=(f.querySelector('input[name=period]:checked')||{}).value==='yearly'?'y':'m';box.querySelectorAll('.sc-amt').forEach(function(e){e.textContent=e.getAttribute('data-'+per)});box.querySelectorAll('.sc-bill-b').forEach(function(b){b.classList.toggle('on',b.querySelector('input').checked)})}
    f.addEventListener('change',upd);upd();})();</script>
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
    <p class="d-mute" style="font-size:.85rem;margin:0">By registering you agree to the <a href="<?= e($app->tenant->mainUrl('terms')) ?>">terms</a> and <a href="<?= e($app->tenant->mainUrl('privacy')) ?>">privacy policy</a>.</p>
    <div class="sc-form-actions">
      <a class="sc-btn sc-btn-soft" href="<?= e($app->tenant->mainUrl('')) ?>">Cancel</a>
      <button class="sc-btn sc-btn-primary" type="submit">Create my account</button>
    </div>
  </form>
</div></section>
<?php \Selfcare\clear_old(); ?>
