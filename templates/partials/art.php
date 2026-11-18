<?php // Selfcare original template. Animated inline SVG illustrations. Variables: $name (hero|how|empty)
if ($name === 'hero'): ?>
<svg class="sc-art" viewBox="0 0 480 400" role="img" aria-label="A dashboard with appointments, a pharmacy cross and a lab flask">
  <rect x="30" y="40" width="420" height="300" rx="22" fill="var(--card)" stroke="var(--line)" stroke-width="2"/>
  <rect x="30" y="40" width="420" height="44" rx="22" fill="var(--soft)"/><circle cx="62" cy="62" r="6" fill="var(--brand)"/><circle cx="82" cy="62" r="6" fill="var(--brand2)" opacity=".6"/><circle cx="102" cy="62" r="6" fill="var(--line)"/>
  <rect x="52" y="104" width="86" height="216" rx="12" fill="var(--bg2)"/>
  <rect x="64" y="120" width="62" height="10" rx="5" fill="var(--brand)" opacity=".8"/><rect x="64" y="144" width="50" height="8" rx="4" fill="var(--line)"/><rect x="64" y="164" width="56" height="8" rx="4" fill="var(--line)"/><rect x="64" y="184" width="44" height="8" rx="4" fill="var(--line)"/>
  <rect x="154" y="104" width="86" height="58" rx="12" fill="var(--soft)"/><rect x="250" y="104" width="86" height="58" rx="12" fill="var(--bg2)"/><rect x="346" y="104" width="86" height="58" rx="12" fill="var(--bg2)"/>
  <path class="dash" d="M166 214c30-40 52 20 86-14s52-30 80-44 54 6 84-18" fill="none" stroke="var(--brand)" stroke-width="3" stroke-linecap="round"/>
  <rect x="154" y="240" width="278" height="12" rx="6" fill="var(--line)"/><rect x="154" y="264" width="220" height="12" rx="6" fill="var(--line)"/><rect x="154" y="288" width="250" height="12" rx="6" fill="var(--line)"/>
  <g class="fl"><rect x="352" y="12" width="104" height="54" rx="14" fill="var(--card)" stroke="var(--brand)" stroke-width="2"/><path d="M380 39h16M388 31v16" stroke="var(--brand)" stroke-width="4" stroke-linecap="round"/><rect x="408" y="34" width="34" height="8" rx="4" fill="var(--line)"/></g>
  <g class="fl2"><rect x="10" y="290" width="112" height="60" rx="14" fill="var(--card)" stroke="var(--line)" stroke-width="2"/><path d="M42 308h18l-4 12a8 8 0 0 0 8 8H38a8 8 0 0 0 8-8l-4-12Z" fill="var(--soft)" stroke="var(--brand)" stroke-width="2"/><rect x="72" y="312" width="36" height="8" rx="4" fill="var(--line)"/></g>
  <g class="fl"><circle cx="420" cy="330" r="32" fill="var(--brand)"/><path d="M420 346s-14-9-14-20a8 8 0 0 1 14-5 8 8 0 0 1 14 5c0 11-14 20-14 20Z" fill="var(--brand-ink)"/></g>
</svg>
<?php elseif ($name === 'how'): ?>
<svg class="sc-art" viewBox="0 0 720 160" role="img" aria-label="Register, set up, go live" style="max-height:140px">
  <path class="dash" d="M120 80h160M360 80h160" stroke="var(--brand)" stroke-width="3" stroke-linecap="round" fill="none"/>
  <g class="fl"><circle cx="80" cy="80" r="44" fill="var(--soft)" stroke="var(--brand)" stroke-width="2"/><path d="M66 90l10 10 20-24" fill="none" stroke="var(--brand)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></g>
  <g class="fl2"><circle cx="320" cy="80" r="44" fill="var(--soft)" stroke="var(--brand)" stroke-width="2"/><circle cx="320" cy="80" r="12" fill="none" stroke="var(--brand)" stroke-width="5"/><path d="M320 54v10M320 96v10M294 80h10M336 80h10" stroke="var(--brand)" stroke-width="5" stroke-linecap="round"/></g>
  <g class="fl"><circle cx="560" cy="80" r="44" fill="var(--soft)" stroke="var(--brand)" stroke-width="2"/><circle cx="560" cy="80" r="22" fill="none" stroke="var(--brand)" stroke-width="4"/><path d="M538 80h44M560 58c-10 12-10 32 0 44M560 58c10 12 10 32 0 44" fill="none" stroke="var(--brand)" stroke-width="3"/></g>
</svg>
<?php else: ?>
<svg class="sc-art" viewBox="0 0 200 120" role="img" aria-label="Nothing here yet" style="max-width:220px;margin:auto"><g class="fl"><rect x="50" y="20" width="100" height="70" rx="12" fill="var(--soft)" stroke="var(--brand)" stroke-width="2"/><path d="M70 45h60M70 60h40" stroke="var(--brand)" stroke-width="4" stroke-linecap="round"/></g></svg>
<?php endif; ?>
