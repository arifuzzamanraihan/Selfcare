<?php // Selfcare original partial. Pricing: audience tabs, monthly or yearly switch, plan cards. Variables: $plans, $audiences, $S, $main, $vc
use function Selfcare\e;
$groups = [];
foreach ($plans as $pl) { $groups[(string) ($pl['audience'] ?? '')][] = $pl; }
$common = $groups[''] ?? [];
unset($groups['']);
$audiences = array_values(array_filter(array_unique(array_merge((array) $audiences, array_keys($groups))), static fn ($a) => isset($groups[$a])));
$maxSave = 0; foreach ($plans as $pl) { $maxSave = max($maxSave, \Selfcare\Plans::yearSaving($pl)); }
$card = static function (array $p) use ($vc, $main): string {
    [$ma, $mc] = \Selfcare\Plans::price($p, $vc); [$ya, $yc] = \Selfcare\Plans::yearPrice($p, $vc);
    $custom = (string) ($p['price_label'] ?? '') !== '';
    $free = !$custom && (float) $p['price'] == 0.0;
    $save = \Selfcare\Plans::yearSaving($p);
    $first = trim((string) strtok((string) $p['types'], ','));
    $href = $custom ? $main('') . '#contact' : $main('register') . '?plan=' . (int) $p['id'] . ($first !== '' ? '&type=' . rawurlencode($first) : '');
    $h = '<div class="sc-card sc-plan sc-pcard rv' . ($p['is_featured'] ? ' is-feat' : '') . '" data-aud="' . e((string) ($p['audience'] ?? '')) . '">';
    $h .= '<h3>' . e((string) $p['name']) . ($p['is_featured'] ? ' <span class="sc-tag">Most chosen</span>' : '') . '</h3>';
    if ((string) ($p['size_label'] ?? '') !== '') { $h .= '<div class="sc-size">' . e((string) $p['size_label']) . '</div>'; }
    if ($custom) { $h .= '<div class="sc-price">' . e((string) $p['price_label']) . '</div>'; }
    elseif ($free) { $h .= '<div class="sc-price">Free</div>'; }
    else {
        $mt = \Selfcare\Currency::fmt($ma, $mc, true); $yt = $ya > 0 ? \Selfcare\Currency::fmt($ya, $yc, true) : '';
        $per = $ya > 0 ? \Selfcare\Currency::fmt(round($ya / 12), $yc, true) : '';
        $h .= '<div class="sc-price"><span class="sc-amt" data-m="' . e($mt) . '" data-y="' . e($yt !== '' ? $yt : $mt) . '">' . e($mt) . '</span><small class="sc-per" data-m=" / month" data-y="' . ($yt !== '' ? ' / year' : ' / month') . '"> / month</small></div>';
        $h .= '<div class="sc-sub" data-m="' . ($save > 0 ? 'Pay yearly and save ' . $save . '%' : '') . '" data-y="' . ($ya > 0 ? e($per) . ' per month' . ($save > 0 ? ', you save ' . $save . '%' : '') : '') . '">' . ($save > 0 ? 'Pay yearly and save ' . $save . '%' : '') . '</div>';
    }
    $h .= '<p class="sc-pdesc">' . e((string) $p['description']) . '</p><ul>';
    foreach (\Selfcare\Plans::featureLines($p) as $l) { $h .= '<li>' . e($l) . '</li>'; }
    $h .= '</ul><a class="sc-btn ' . ($p['is_featured'] ? 'sc-btn-primary' : '') . ' sc-pcta" data-href="' . e($href) . '" href="' . e($href) . '">' . ($custom ? 'Talk to us' : ($free ? 'Start free' : ((int) ($p['trial_days'] ?? 0) > 0 ? 'Start ' . (int) $p['trial_days'] . ' day free trial' : 'Choose ' . e((string) $p['name'])))) . '</a></div>';
    return $h;
};
?>
<section class="sc-section alt" id="pricing"><div class="sc-wrap">
  <div class="sc-center rv"><span class="sc-eyebrow">Pricing</span><h2><?= e($S('pricing_title')) ?></h2><p class="sc-lead"><?= e($S('pricing_text')) ?></p></div>
  <div class="sc-ptools">
    <div class="sc-aud" role="tablist" aria-label="Kind of provider"><?php foreach ($audiences as $i => $a): ?><button type="button" role="tab" class="sc-aud-b<?= $i === 0 ? ' on' : '' ?>" data-aud="<?= e($a) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($a) ?></button><?php endforeach; ?></div>
    <div class="sc-bill" role="group" aria-label="Billing period"><button type="button" class="sc-bill-b on" data-per="m">Monthly</button><button type="button" class="sc-bill-b" data-per="y">Yearly<?= $maxSave > 0 ? ' <span class="sc-save">Save up to ' . (int) $maxSave . '%</span>' : '' ?></button></div>
    <form method="get" action="#pricing" class="sc-curpick"><label for="cur">Show prices in </label> <select id="cur" name="cur" onchange="this.form.submit()" aria-label="Currency"><?php foreach (\Selfcare\Currency::all(true) as $cc => $cr): ?><option value="<?= e($cc) ?>"<?= $cc === $vc ? ' selected' : '' ?>><?= e($cc . ' ' . trim((string) $cr['symbol'])) ?></option><?php endforeach; ?></select><noscript> <button class="sc-btn" type="submit">Show</button></noscript></form>
  </div>
  <?php foreach ($audiences as $i => $a): ?>
  <div class="sc-pgroup" data-aud="<?= e($a) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
    <h3 class="sc-pgroup-h"><?= e($a) ?></h3>
    <div class="sc-pgrid"><?php foreach (array_merge($common, $groups[$a]) as $p) { echo $card($p); } ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (!$audiences): ?><div class="sc-pgrid"><?php foreach ($common as $p) { echo $card($p); } ?></div><?php endif; ?>
  <p class="sc-pnote">Prices are in Pakistani Rupees (Rs) and exclude taxes. Pay yearly and get two months free. Every paid plan starts with a free trial and needs no card. Change or cancel your plan at any time. Need something bigger? <a href="<?= e($main('')) ?>#contact">Talk to us</a>.</p>
</div></section>
<script>
(function(){var s=document.getElementById('pricing');if(!s)return;var per='m';
function upd(){s.querySelectorAll('.sc-amt,.sc-per,.sc-sub').forEach(function(e){e.textContent=e.getAttribute('data-'+per)||''});
 s.querySelectorAll('.sc-pcta').forEach(function(a){var h=a.getAttribute('data-href');if(h.indexOf('/register')>-1)h+=(h.indexOf('?')>-1?'&':'?')+'period='+(per==='y'?'yearly':'monthly');a.href=h});
 s.querySelectorAll('.sc-bill-b').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-per')===per)})}
s.querySelectorAll('.sc-bill-b').forEach(function(b){b.addEventListener('click',function(){per=b.getAttribute('data-per');upd()})});
s.querySelectorAll('.sc-aud-b').forEach(function(b){b.addEventListener('click',function(){var a=b.getAttribute('data-aud');s.querySelectorAll('.sc-aud-b').forEach(function(x){var on=x===b;x.classList.toggle('on',on);x.setAttribute('aria-selected',on)});s.querySelectorAll('.sc-pgroup').forEach(function(g){g.hidden=g.getAttribute('data-aud')!==a})})});
upd();})();
</script>
