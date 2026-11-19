<?php // Selfcare original template. Vendor website home. Variables: $vendor $type $tpl $data $profile $free $doctors $base
use function Selfcare\{e, icon, upload_url, tone, initials, money, old};
$app = \Selfcare\app();
$map = trim($profile['address'] . ' ' . $profile['city']) !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($vendor['name'] . ' ' . $profile['address'] . ' ' . $profile['city']) : '';
$cover = $profile['cover'] !== '' ? upload_url($profile['cover']) : '';
$rq = $tpl['request'];
?>
<section class="vs-hero<?= $cover ? ' has-cover' : '' ?>"<?= $cover ? ' style="background-image:url(' . e($cover) . ')"' : '' ?>><div class="sc-wrap">
  <span class="sc-badge"><?= icon((string) $type['icon'], 16) ?> <?= e($type['name']) ?></span>
  <h1><?= e($vendor['name']) ?></h1>
  <p class="sc-lead"><?= e($profile['tagline'] ?: (string) $type['tagline']) ?></p>
  <div class="sc-cta"><a class="sc-btn sc-btn-primary sc-btn-lg" href="#request"><?= e((string) $rq['cta']) ?></a><a class="sc-btn sc-btn-lg" href="#contact">Contact us</a></div>
  <div class="vs-info">
    <?php if ($profile['phone']): ?><span><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $profile['phone'])) ?>"><?= e($profile['phone']) ?></a></span><?php endif; ?>
    <?php if ($profile['email']): ?><span><?= icon('mail', 16) ?> <a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a></span><?php endif; ?>
    <?php if ($map): ?><span><?= icon('pin', 16) ?> <a href="<?= e($map) ?>" target="_blank" rel="noopener noreferrer"><?= e(trim($profile['address'] . ', ' . $profile['city'], ', ')) ?> (open map)</a></span><?php endif; ?>
    <?php if ($profile['license']): ?><span><?= icon('shield', 16) ?> Licence <?= e($profile['license']) ?></span><?php endif; ?>
  </div>
  <?php if ($profile['emergency']): ?><div class="vs-emerg"><?= icon('siren', 20) ?> <?= e((string) $tpl['profile']['emergency_phone']) ?>: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $profile['emergency'])) ?>" style="color:inherit"><?= e($profile['emergency']) ?></a></div><?php endif; ?>
</div></section>
<?php if ($profile['about']): ?><section class="sc-section" id="about"><div class="sc-wrap sc-prose"><h2>About us</h2><?= \Selfcare\nl2p($profile['about']) ?></div></section><?php endif; ?>
<?php foreach ($tpl['sections'] as $s): $k = $s['key']; $rows = $data[$k] ?? null; if (empty($rows)) { continue; } ?>
<section class="sc-section<?= $s['kind'] === 'faq' || $s['kind'] === 'hours' ? ' alt' : '' ?>" id="<?= e($k) ?>"><div class="sc-wrap">
  <h2><?= e((string) $s['label']) ?></h2>
  <?php switch ($s['kind']): case 'cards': ?>
    <div class="sc-grid g3"><?php foreach ($rows as $r): ?><div class="sc-card"><?php if ($r['image']): ?><img src="<?= e(upload_url($r['image'])) ?>" alt="" style="width:100%;height:150px;object-fit:cover;border-radius:10px;margin-bottom:12px"><?php else: ?><div class="sc-ico"><?= icon((string) ($s['icon'] ?? 'layers'), 22) ?></div><?php endif; ?><h3><?= e($r['title']) ?></h3><p><?= e((string) $r['body']) ?></p><?php if ($r['meta']): ?><div class="sc-meta"><span class="sc-tag"><?= e($r['meta']) ?></span></div><?php endif; ?></div><?php endforeach; ?></div>
  <?php break; case 'departments': ?>
    <div class="sc-grid g3"><?php foreach ($rows as $r): ?><div class="sc-card"><div class="sc-ico"><?= icon('layers', 22) ?></div><h3><?= e($r['title']) ?></h3><p><?= e((string) $r['description']) ?></p><div class="sc-meta"><span><?= (int) $r['doctors'] ?> <?= (int) $r['doctors'] === 1 ? 'doctor' : 'doctors' ?></span></div></div><?php endforeach; ?></div>
  <?php break; case 'team': ?>
    <div class="sc-grid g3"><?php foreach ($rows as $r): [$h1] = tone((string) $r['id'] . $r['first_name']); ?><a class="sc-card" href="<?= e($base('doctor/' . $r['id'])) ?>"><div class="vs-doc"><div class="av" style="background:hsl(<?= $h1 ?> 50% 40%)"><?= e(initials($r['first_name'] . ' ' . $r['last_name'])) ?></div><div><h3 style="margin:0">Dr. <?= e($r['first_name'] . ' ' . $r['last_name']) ?></h3><p><?= e((string) $r['specialist']) ?></p></div></div><div class="sc-meta"><?php if ($r['department']): ?><span class="sc-tag"><?= e($r['department']) ?></span><?php endif; ?><?php if ((float) $r['appointment_charge'] > 0): ?><span>Fee <?= e(money((float) $r['appointment_charge'])) ?></span><?php endif; ?></div></a><?php endforeach; ?></div>
  <?php break; case 'text': ?><div class="sc-prose"><?= \Selfcare\nl2p((string) $rows) ?></div>
  <?php break; case 'hours': ?><table class="vs-hours"><?php foreach ($rows as $day => $t): if ($t === '') { continue; } ?><tr><td><?= e($day) ?></td><td><?= e($t) ?></td></tr><?php endforeach; ?></table>
  <?php break; case 'faq': ?><div class="sc-faq" style="max-width:780px"><?php foreach ($rows as $r): ?><details><summary><?= e($r['title']) ?></summary><p><?= e((string) $r['body']) ?></p></details><?php endforeach; ?></div>
  <?php break; case 'testimonials': ?><div class="sc-grid g3"><?php foreach ($rows as $r): $n = max(1, min(5, (int) $r['meta'])); ?><figure class="sc-card vs-quote" style="margin:0"><div class="vs-stars" aria-label="<?= $n ?> out of 5"><?= str_repeat('★', $n) . str_repeat('☆', 5 - $n) ?></div><p style="color:var(--ink);margin-top:8px">&ldquo;<?= e((string) $r['body']) ?>&rdquo;</p><figcaption class="sc-meta"><b style="color:var(--ink)"><?= e($r['title']) ?></b></figcaption></figure><?php endforeach; ?></div>
  <?php break; case 'gallery': ?><div class="vs-gal"><?php foreach ($rows as $r): if ($r['image']): ?><img src="<?= e(upload_url($r['image'])) ?>" alt="<?= e($r['title']) ?>" loading="lazy"><?php endif; endforeach; ?></div>
  <?php break; case 'news': ?><div class="sc-grid g3"><?php foreach (array_slice($rows, 0, 6) as $r): ?><div class="sc-card"><?php if ($r['published_at']): ?><span class="sc-tag"><?= e(\Selfcare\dt((string) $r['published_at'])) ?></span><?php endif; ?><h3 style="margin-top:8px"><?= e($r['title']) ?></h3><p><?= e((string) $r['body']) ?></p></div><?php endforeach; ?></div>
  <?php break; endswitch; ?>
</div></section>
<?php endforeach; ?>
<?php if (!empty($lab)): $lc = (string) $lab['currency']; ?>
<section class="sc-section" id="branches"><div class="sc-wrap">
  <h2>Our branches</h2>
  <div class="sc-grid g3"><?php foreach ($lab['branches'] as $b): ?>
    <div class="sc-card"><div class="sc-ico"><?= icon('pin', 22) ?></div><h3><?= e((string) $b['name']) ?></h3>
      <p><?= e((string) ($b['address'] ?: 'Address will be added soon')) ?></p>
      <div class="sc-meta"><?php if ($b['phone']): ?><span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $b['phone'])) ?>"><?= e((string) $b['phone']) ?></a></span><?php endif; ?><?php if ($b['email']): ?><span><a href="mailto:<?= e((string) $b['email']) ?>"><?= e((string) $b['email']) ?></a></span><?php endif; ?></div></div>
  <?php endforeach; ?></div>
</div></section>
<?php if ($lab['tests']): ?>
<section class="sc-section alt" id="tests"><div class="sc-wrap">
  <h2>Tests and prices</h2>
  <p class="sc-lead">Prices are in <?= e($lc) ?> and may differ by branch. Ask at the counter for a package.</p>
  <?php foreach ($lab['tests'] as $dept => $rows): ?>
  <details class="sc-card" style="margin:10px 0"><summary><b><?= e((string) $dept) ?></b> <span class="d-mute">(<?= count($rows) ?>)</span></summary>
    <table class="vs-hours"><?php foreach ($rows as $t): ?><tr><td><?= e((string) $t['name']) ?><?php if ($t['specimen']): ?> <span class="d-mute">(<?= e((string) $t['specimen']) ?>)</span><?php endif; ?></td><td style="text-align:right"><?= e(money((float) $t['price'], $lc)) ?></td></tr><?php endforeach; ?></table>
  </details>
  <?php endforeach; ?>
</div></section>
<?php endif; ?>
<section class="sc-section<?= $lab['tests'] ? '' : ' alt' ?>" id="reports"><div class="sc-wrap sc-center" style="max-width:700px">
  <h2>Your reports</h2>
  <p class="sc-lead">Patients sign in to see approved reports, bills and visits. Reports stay private to the patient.</p>
  <p><a class="sc-btn sc-btn-primary sc-btn-lg" href="<?= e($app->tenant->mainUrl('login')) ?>">Patient sign in</a></p>
</div></section>
<?php if ($lab['home']): ?>
<section class="sc-section alt" id="home-collection"><div class="sc-wrap" style="max-width:700px">
  <h2>Home sample collection</h2>
  <p class="sc-lead">We send a trained collector to your door. Tell us where and when.</p>
  <form class="sc-form sc-card" method="post" action="<?= e($base('home-collection')) ?>"><?= \Selfcare\csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="sc-grid g2"><label>Your name<input name="name" required maxlength="160" autocomplete="name"></label><label>Phone<input type="tel" name="phone" required autocomplete="tel"></label>
      <label>Preferred date<input type="date" name="preferred_date" required min="<?= date('Y-m-d') ?>"></label>
      <label>Nearest branch<select name="branch_id"><?php foreach ($lab['branches'] as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e((string) $b['name']) ?></option><?php endforeach; ?></select></label></div>
    <label>Address for collection<input name="address" required maxlength="255" autocomplete="street-address"></label>
    <label>Tests or notes (optional)<textarea name="message" rows="3" maxlength="250"></textarea></label>
    <button class="sc-btn sc-btn-primary" type="submit">Request home collection</button>
  </form>
</div></section>
<?php endif; ?>
<?php endif; ?>
<?php if ($free): ?><section class="sc-section alt" id="free"><div class="sc-wrap"><h2>Free services</h2><div class="sc-grid g3"><?php foreach ($free as $f): ?><div class="sc-card"><div class="sc-ico"><?= icon((string) ($f['icon'] ?: 'heart'), 22) ?></div><h3><?= e($f['title']) ?></h3><p><?= e((string) $f['description']) ?></p><div class="sc-meta"><?php if ($f['schedule']): ?><span><?= icon('clock', 14) ?> <?= e($f['schedule']) ?></span><?php endif; ?><?php if ($f['eligibility']): ?><span>For: <?= e($f['eligibility']) ?></span><?php endif; ?></div></div><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="sc-section" id="request"><div class="sc-wrap" style="max-width:700px">
  <h2><?= e((string) $rq['title']) ?></h2>
  <form class="sc-form sc-card" method="post" action="<?= e($base('request')) ?>"><?= \Selfcare\csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="sc-grid g2"><label>Your name<input name="name" required maxlength="160" value="<?= e(old('name')) ?>" autocomplete="name"></label><label>Phone<input type="tel" name="phone" required value="<?= e(old('phone')) ?>" autocomplete="tel"></label>
      <label>Email (optional)<input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email"></label><label><?= e((string) $rq['date']) ?><input type="date" name="preferred_date" required min="<?= date('Y-m-d') ?>" value="<?= e(old('preferred_date')) ?>"></label></div>
    <?php if (!empty($rq['doctor']) && $doctors): ?><label>Doctor (optional)<select name="doctor_id"><option value="">Any available doctor</option><?php foreach ($doctors as $d): ?><option value="<?= (int) $d['id'] ?>"<?= old('doctor_id') == $d['id'] ? ' selected' : '' ?>>Dr. <?= e($d['first_name'] . ' ' . $d['last_name']) ?> (<?= e((string) $d['specialist']) ?>)</option><?php endforeach; ?></select></label><?php endif; ?>
    <label><?= e((string) $rq['note']) ?><textarea name="message" rows="3" maxlength="1000"><?= e(old('message')) ?></textarea></label>
    <button class="sc-btn sc-btn-primary" type="submit"><?= e((string) $rq['submit']) ?></button>
  </form>
</div></section>

<section class="sc-section alt" id="contact"><div class="sc-wrap" style="max-width:700px">
  <h2>Contact us</h2>
  <form class="sc-form sc-card" method="post" action="<?= e($base('contact')) ?>"><?= \Selfcare\csrf_field() ?>
    <input class="sc-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="sc-grid g2"><label>Name<input name="name" required maxlength="160" autocomplete="name"></label><label>Email<input type="email" name="email" autocomplete="email"></label></div>
    <label>Message<textarea name="message" rows="4" required maxlength="3000"></textarea></label>
    <button class="sc-btn sc-btn-primary" type="submit">Send message</button>
  </form>
</div></section>
<?php \Selfcare\clear_old(); ?>
