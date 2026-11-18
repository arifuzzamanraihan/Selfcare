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
  var scrim = d.querySelector('[data-side-scrim]');
  if (sb && sbBtn) {
    var setSide = function (open) { sb.classList.toggle('is-open', open); if (scrim) scrim.classList.toggle('is-on', open); d.body.classList.toggle('d-nav-open', open); sbBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) { var f = sb.querySelector('a'); if (f && f.focus) f.focus({ preventScroll: true }); } else if (document.activeElement && sb.contains(document.activeElement)) { sbBtn.focus({ preventScroll: true }); } };
    sbBtn.addEventListener('click', function (e) { e.stopPropagation(); setSide(!sb.classList.contains('is-open')); });
    if (scrim) scrim.addEventListener('click', function () { setSide(false); });
    d.addEventListener('click', function (e) { if (sb.classList.contains('is-open') && !sb.contains(e.target) && !sbBtn.contains(e.target)) setSide(false); });
    sb.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('a[href]'); if (a && sb.classList.contains('is-open')) setSide(false); });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sb.classList.contains('is-open')) setSide(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 980 && sb.classList.contains('is-open')) setSide(false); });
  }

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

  // overflow menu: first row action stays visible, the rest open from the kebab
  function closeMenus(keep) {
    $$('.d-more').forEach(function (m) {
      if (m === keep) return;
      var menu = m.querySelector('.d-more-menu');
      var b = m.querySelector('.d-more-btn');
      if (menu) menu.hidden = true;
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }
  function placeMenu(btn, menu) {
    var r = btn.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top = Math.round(r.bottom + 4) + 'px';
    menu.style.right = Math.max(8, Math.round(window.innerWidth - r.right)) + 'px';
    menu.style.left = 'auto';
    menu.style.zIndex = '200';
  }
  d.addEventListener('click', function (e) {
    var btn = e.target.closest('.d-more-btn');
    if (btn) {
      e.preventDefault();
      var wrap = btn.closest('.d-more');
      var menu = wrap && wrap.querySelector('.d-more-menu');
      if (!menu) return;
      var open = menu.hidden;
      closeMenus(wrap);
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) placeMenu(btn, menu);
      return;
    }
    closeMenus();
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });
  window.addEventListener('resize', function () { closeMenus(); });

  // long lists: a search box filters the options without losing the current value
  $$('select[data-searchable]').forEach(function (sel) {
    if (sel.dataset.searchReady) return;
    sel.dataset.searchReady = '1';
    var wrap = d.createElement('div');
    wrap.className = 'd-combo';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    var q = d.createElement('input');
    q.type = 'search';
    q.className = 'd-combo-q';
    q.setAttribute('aria-label', 'Search this list');
    q.placeholder = 'Search…';
    q.autocomplete = 'off';
    wrap.insertBefore(q, sel);
    var all = [];
    Array.prototype.forEach.call(sel.options, function (o) {
      all.push({ value: o.value, text: o.text, disabled: o.disabled });
    });
    q.addEventListener('input', function () {
      var cur = sel.value;
      var n = q.value.trim().toLowerCase();
      sel.innerHTML = '';
      all.forEach(function (o) {
        var hit = !n || o.text.toLowerCase().indexOf(n) !== -1 || String(o.value).toLowerCase().indexOf(n) !== -1;
        if (!hit && o.value !== cur) return;
        var opt = d.createElement('option');
        opt.value = o.value;
        opt.textContent = o.text;
        opt.disabled = !!o.disabled;
        if (o.value === cur) opt.selected = true;
        sel.appendChild(opt);
      });
      if (cur) sel.value = cur;
    });
  });

  // web address field follows the name until the person types in it

  // On a phone, keep the page header on one row: extra header actions go in a kebab.

  function packTopActions() {
    var top = document.querySelector('.d-top');
    var bar = document.querySelector('.d-top-actions');
    if (!bar || !top) return;
    var existing = bar.querySelector('.d-top-more');
    if (existing) {
      var menu = existing.querySelector('.d-more-menu');
      if (menu) {
        while (menu.firstChild) bar.insertBefore(menu.firstChild, existing);
      }
      existing.remove();
    }
    if (window.innerWidth > 980) return;
    var kids = Array.prototype.slice.call(bar.children).filter(function (el) {
      return el.nodeType === 1 && el.tagName !== 'SCRIPT';
    });
    if (!kids.length) return;
    // Always collapse header actions on a phone so the page title stays readable on one row.
    var wrap = document.createElement('div');
    wrap.className = 'd-more d-top-more';
    wrap.innerHTML = '<button type="button" class="d-act d-more-btn" aria-haspopup="true" aria-expanded="false" aria-label="More actions">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button><div class="d-more-menu" hidden></div>';
    var menu = wrap.querySelector('.d-more-menu');
    kids.forEach(function (el) { menu.appendChild(el); });
    bar.appendChild(wrap);
    if (overflows() && bar.contains(wrap) === false) { /* no-op */ }
  }
  packTopActions();
  window.addEventListener('resize', packTopActions);
  window.addEventListener('resize', packTopActions);

  $$('[data-slug-from]').forEach(function (inp) {
    var src = document.getElementById(inp.getAttribute('data-slug-from'));
    if (!src) return;
    inp.addEventListener('input', function () { inp.dataset.touched = '1'; });
    src.addEventListener('input', function () {
      if (inp.dataset.touched === '1') return;
      inp.value = src.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    });
  });
})();
