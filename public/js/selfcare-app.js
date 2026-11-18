/* Selfcare original script. Vanilla JS, no libraries. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    /* Sticky header state */
    var head = $('[data-sc-head]');
    var onScroll = function () { if (head) { head.classList.toggle('is-scrolled', window.scrollY > 12); } };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    /* Mobile menu */
    var burger = $('[data-sc-burger]');
    var nav = $('#sc-nav');
    if (burger && nav) {
      burger.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      $$('a', nav).forEach(function (a) {
        a.addEventListener('click', function () { nav.classList.remove('is-open'); burger.setAttribute('aria-expanded', 'false'); });
      });
    }

    /* Password show/hide */
    var eye = $('[data-sc-eye]');
    var pw = $('[data-sc-pw]');
    if (eye && pw) {
      eye.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    }

    /* Number ticker */
    function tick(el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      if (reduce || target === 0) { el.textContent = target.toLocaleString(); return; }
      var start = null, dur = 1400;
      el.textContent = '0';
      function step(ts) {
        if (start === null) { start = ts; }
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 4);
        el.textContent = Math.round(target * eased).toLocaleString();
        if (p < 1) { requestAnimationFrame(step); }
      }
      requestAnimationFrame(step);
    }

    /* Blur-fade and ticker on scroll */
    var fades = $$('.sc-fade');
    var tickers = $$('[data-count]');
    if (!('IntersectionObserver' in window)) {
      fades.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var seen = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) { return; }
        en.target.classList.add('is-in');
        $$('[data-count]', en.target).concat(en.target.hasAttribute('data-count') ? [en.target] : []).forEach(tick);
        seen.unobserve(en.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    fades.forEach(function (el) { seen.observe(el); });

    /* Magic-card spotlight */
    if (!reduce) {
      $$('.sc-magic').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
          var r = card.getBoundingClientRect();
          card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
          card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
      });
    }
  });
})();
