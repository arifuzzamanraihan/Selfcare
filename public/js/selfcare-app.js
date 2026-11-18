/* Selfcare original JS. Theme toggle, menus, reveal, confirm dialog, auto submit, number ticker. No libraries. */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }

  // theme
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var dark = root.getAttribute('data-theme') === 'dark' || (!root.getAttribute('data-theme') && matchMedia('(prefers-color-scheme: dark)').matches);
      var n = dark ? 'light' : 'dark';
      root.setAttribute('data-theme', n);
      try { localStorage.setItem('sc-theme', n); } catch (e) {}
    });
  });

  // public burger + header shadow
  var burger = d.querySelector('[data-sc-burger]'), nav = d.getElementById('sc-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var o = nav.classList.toggle('is-open'); burger.setAttribute('aria-expanded', o ? 'true' : 'false');
    });
  }
  var head = d.querySelector('[data-sc-head]');
  if (head) { var onS = function () { head.classList.toggle('is-scrolled', window.scrollY > 6); }; onS(); window.addEventListener('scroll', onS, { passive: true }); }

  // dashboard sidebar
  var sb = d.querySelector('[data-side]'), sbBtn = d.querySelector('[data-side-toggle]');
  if (sb && sbBtn) { sbBtn.addEventListener('click', function () { sb.classList.toggle('is-open'); }); d.addEventListener('click', function (e) { if (sb.classList.contains('is-open') && !sb.contains(e.target) && !sbBtn.contains(e.target)) sb.classList.remove('is-open'); }); }

  // reveal
  var rv = $$('.rv');
  if (rv.length) {
    if ('IntersectionObserver' in window && !reduce) {
      var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { threshold: .12 });
      rv.forEach(function (n) { io.observe(n); });
    } else { rv.forEach(function (n) { n.classList.add('in'); }); }
  }

  // number ticker
  $$('[data-count]').forEach(function (n) {
    var to = parseInt(n.getAttribute('data-count'), 10) || 0;
    if (reduce || !('IntersectionObserver' in window) || to < 2) { n.textContent = to; return; }
    var go = new IntersectionObserver(function (es) {
      if (!es[0].isIntersecting) return; go.disconnect();
      var t0 = null; (function step(t) { t0 = t0 || t; var p = Math.min(1, (t - t0) / 1000); n.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); })(performance.now());
    }); go.observe(n);
  });

  // auto submit filters
  $$('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  // confirm dialog (forms with data-confirm)
  var modal;
  function ask(text, ok) {
    if (!modal) {
      modal = d.createElement('div'); modal.className = 'd-modal'; modal.hidden = true; modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true');
      modal.innerHTML = '<div class="d-modal-box"><h3>Please confirm</h3><p data-m></p><div class="d-modal-actions"><button type="button" class="d-btn d-btn-soft" data-no>Cancel</button><button type="button" class="d-btn d-btn-danger" data-yes>Yes, continue</button></div></div>';
      d.body.appendChild(modal);
      modal.addEventListener('click', function (e) { if (e.target === modal || e.target.hasAttribute('data-no')) modal.hidden = true; });
      d.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
    }
    modal.querySelector('[data-m]').textContent = text; modal.hidden = false;
    var y = modal.querySelector('[data-yes]'); var c = y.cloneNode(true); y.parentNode.replaceChild(c, y);
    c.addEventListener('click', function () { modal.hidden = true; ok(); }); c.focus();
  }
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (f._ok) return; e.preventDefault(); ask(f.getAttribute('data-confirm'), function () { f._ok = true; f.submit(); }); });
  });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
})();
