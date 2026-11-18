<?php // Selfcare original template. Variables: $counts, $user ?>
<?php
$first = trim((string) ($user['first_name'] ?? ''));
$icons = [
  'users'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  'cal'     => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  'card'    => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
  'pill'    => '<path d="m10.5 20.5-7-7a4.95 4.95 0 0 1 7-7l7 7a4.95 4.95 0 0 1-7 7Z"/><path d="m8.5 8.5 7 7"/>',
  'flask'   => '<path d="M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2.2h12.4a1.5 1.5 0 0 0 1.3-2.2L14 9V3"/><path d="M7.5 15h9"/>',
  'bed'     => '<path d="M3 20V6M3 16h18v4M21 16v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="12" r="2"/>',
  'drop'    => '<path d="M12 2.7s6 6.1 6 10.6a6 6 0 0 1-12 0C6 8.8 12 2.7 12 2.7Z"/>',
  'chart'   => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
];
$svg = static fn (string $k): string => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$k] . '</svg>';
$modules = ['Patients', 'Appointments', 'OPD', 'IPD', 'Billing', 'Pharmacy', 'Laboratory', 'Beds', 'Blood bank', 'Reports', 'Prescriptions', 'Departments'];
?>
<section class="sc-hero">
  <div class="sc-hero-bg" aria-hidden="true"><i class="sc-aurora a1"></i><i class="sc-aurora a2"></i><i class="sc-aurora a3"></i><i class="sc-grid"></i></div>
  <div class="sc-hero-in">
    <a class="sc-pill sc-fade" href="#features"><span class="sc-pill-dot"></span>Hospital management, rebuilt <span aria-hidden="true">&rarr;</span></a>
    <h1 class="sc-fade" style="--d:80ms">Run your whole hospital <span class="sc-gradient">from one calm place</span></h1>
    <p class="sc-lead sc-fade" style="--d:160ms">Self Care brings patients, appointments, billing, pharmacy, labs and beds together, so every team works from the same page.</p>
    <div class="sc-cta sc-fade" style="--d:240ms">
      <?php if (empty($user)): ?>
        <a class="sc-btn sc-btn-shimmer" href="login">Sign in to Self Care</a>
      <?php else: ?>
        <span class="sc-welcome">Welcome back<?= $first !== '' ? ', ' . \Selfcare\e($first) : '' ?></span>
      <?php endif; ?>
      <a class="sc-btn sc-btn-ghost" href="#features">See what's inside</a>
    </div>
    <div class="sc-mock sc-beam sc-fade" style="--d:340ms" aria-hidden="true">
      <div class="sc-mock-bar"><i></i><i></i><i></i><span>Sample dashboard</span></div>
      <div class="sc-mock-body">
        <div class="sc-mock-side"><b></b><b></b><b></b><b></b><b></b></div>
        <div class="sc-mock-main">
          <div class="sc-mock-kpis">
            <div><small>Appointments</small><strong>128</strong></div>
            <div><small>Admitted</small><strong>46</strong></div>
            <div><small>Beds free</small><strong>19</strong></div>
          </div>
          <div class="sc-mock-chart"><svg viewBox="0 0 300 80" preserveAspectRatio="none"><defs><linearGradient id="mg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1abc9c" stop-opacity=".45"/><stop offset="1" stop-color="#1abc9c" stop-opacity="0"/></linearGradient></defs><path d="M0 60 C30 50 50 20 90 32 S150 70 190 40 250 10 300 24 V80 H0Z" fill="url(#mg)"/><path d="M0 60 C30 50 50 20 90 32 S150 70 190 40 250 10 300 24" fill="none" stroke="#0e8a7a" stroke-width="2.5"/></svg></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sc-marquee-wrap sc-fade" id="modules" aria-label="Modules">
  <p class="sc-eyebrow">Everything a hospital runs on</p>
  <div class="sc-marquee">
    <?php for ($r = 0; $r < 2; $r++): ?>
    <ul class="sc-marquee-row"<?= $r ? ' aria-hidden="true"' : '' ?>>
      <?php foreach ($modules as $m): ?><li><?= \Selfcare\e($m) ?></li><?php endforeach; ?>
    </ul>
    <?php endfor; ?>
  </div>
</section>

<section class="sc-section" id="features">
  <div class="sc-head-block sc-fade">
    <p class="sc-eyebrow">Features</p>
    <h2>Built for the way hospitals actually work</h2>
    <p class="sc-sub">Every desk, ward and department sees the same up-to-date record.</p>
  </div>
  <div class="sc-bento">
    <article class="sc-magic sc-fade b-wide">
      <span class="sc-ico"><?= $svg('users') ?></span>
      <h3>Patient records, one timeline</h3>
      <p>Visits, diagnoses, prescriptions and charges stay together, from first registration to discharge.</p>
      <div class="sc-chips" aria-hidden="true"><span>OPD</span><span>IPD</span><span>History</span><span>Documents</span></div>
    </article>
    <article class="sc-magic sc-fade" style="--d:60ms">
      <span class="sc-ico"><?= $svg('cal') ?></span>
      <h3>Appointments</h3>
      <p>Book, move and confirm visits without phone tag.</p>
    </article>
    <article class="sc-magic sc-fade" style="--d:120ms">
      <span class="sc-ico"><?= $svg('card') ?></span>
      <h3>Billing and payments</h3>
      <p>Clear invoices and payment status on every case.</p>
    </article>
    <article class="sc-magic sc-fade b-tall" style="--d:60ms">
      <span class="sc-ico"><?= $svg('bed') ?></span>
      <h3>Beds and wards</h3>
      <p>See which beds are free, who is admitted and who goes home today.</p>
      <div class="sc-bars" aria-hidden="true"><i style="--h:70%"></i><i style="--h:45%"></i><i style="--h:85%"></i><i style="--h:55%"></i><i style="--h:92%"></i><i style="--h:38%"></i></div>
    </article>
    <article class="sc-magic sc-fade" style="--d:120ms">
      <span class="sc-ico"><?= $svg('pill') ?></span>
      <h3>Pharmacy</h3>
      <p>Medicines, stock and purchase bills in step with prescriptions.</p>
    </article>
    <article class="sc-magic sc-fade" style="--d:180ms">
      <span class="sc-ico"><?= $svg('flask') ?></span>
      <h3>Laboratory</h3>
      <p>Orders, results and reports reach the doctor faster.</p>
    </article>
    <article class="sc-magic sc-fade" style="--d:120ms">
      <span class="sc-ico"><?= $svg('drop') ?></span>
      <h3>Blood bank</h3>
      <p>Donors, groups and stock at a glance.</p>
    </article>
    <article class="sc-magic sc-fade b-wide3" style="--d:60ms">
      <span class="sc-ico"><?= $svg('chart') ?></span>
      <h3>Reports that answer questions</h3>
      <p>Know how the hospital is doing today, this week and this year, without building a spreadsheet.</p>
    </article>
  </div>
</section>

<section class="sc-section sc-numbers" id="numbers">
  <div class="sc-head-block sc-fade">
    <p class="sc-eyebrow">Platform</p>
    <h2>Live from your Self Care database</h2>
  </div>
  <div class="sc-cards">
    <?php foreach (($counts ?? []) as $label => $value): ?>
      <article class="sc-card sc-magic sc-fade"><strong data-count="<?= (int) $value ?>"><?= (int) $value ?></strong><span><?= \Selfcare\e((string) $label) ?></span></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="sc-section">
  <div class="sc-final sc-beam sc-fade">
    <h2>Ready to run a calmer hospital?</h2>
    <p>Sign in and pick up where your team left off.</p>
    <?php if (empty($user)): ?><a class="sc-btn sc-btn-shimmer" href="login">Sign in</a><?php endif; ?>
  </div>
</section>
