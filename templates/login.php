<?php // Selfcare original template. Variables: $error ?>
<section class="sc-auth">
  <div class="sc-hero-bg" aria-hidden="true"><i class="sc-aurora a1"></i><i class="sc-aurora a2"></i><i class="sc-grid"></i></div>
  <div class="sc-panel sc-beam sc-fade">
    <a class="sc-auth-mark" href="./" aria-label="Self Care home"><img src="img/logo-mark.svg" width="52" height="52" alt=""></a>
    <h1>Welcome back</h1>
    <p class="sc-sub">Sign in to your Self Care workspace.</p>
    <?php if (!empty($error)): ?><p class="sc-error" role="alert"><?= \Selfcare\e((string) $error) ?></p><?php endif; ?>
    <form method="post" action="login" class="sc-form">
      <label>Email <input type="email" name="email" required autocomplete="username" placeholder="you@hospital.com"></label>
      <label>Password
        <span class="sc-pw"><input type="password" name="password" required autocomplete="current-password" placeholder="Your password" data-sc-pw>
        <button type="button" class="sc-eye" aria-label="Show password" data-sc-eye><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
      </label>
      <button type="submit" class="sc-btn sc-btn-shimmer sc-btn-block">Sign in</button>
    </form>
    <p class="sc-back"><a href="./">&larr; Back to home</a></p>
  </div>
</section>
