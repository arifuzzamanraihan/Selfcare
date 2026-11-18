/* Selfcare original JS. Invoice item rows: add / remove rows and live totals. */
(function () {
  'use strict';
  // forms: repeating rows
  Array.prototype.forEach.call(document.querySelectorAll('[data-addrow]'), function (b) {
    b.addEventListener('click', function () { var t = document.getElementById(b.getAttribute('data-addrow')); t.querySelector('tbody').appendChild(t.querySelector('template').content.cloneNode(true)); });
  });
  var form = document.querySelector('[data-invoice]');
  if (!form) return;
  var body = form.querySelector('[data-rows]'), tpl = form.querySelector('template');
  var cur = form.getAttribute('data-currency') || '';
  function n(v) { v = parseFloat(v); return isNaN(v) ? 0 : v; }
  function fmt(v) { return v.toFixed(2); }
  function calc() {
    var sub = 0, tax = 0;
    Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr) {
      var q = n(tr.querySelector('[data-q]').value), p = n(tr.querySelector('[data-p]').value), t = n(tr.querySelector('[data-t]') ? tr.querySelector('[data-t]').value : 0);
      var a = q * p; tr.querySelector('[data-a]').textContent = fmt(a); sub += a; tax += a * t / 100;
    });
    var dpct = n((form.querySelector('[name=discount_pct]') || {}).value), dfix = n((form.querySelector('[name=discount]') || {}).value);
    var disc = Math.min(sub, sub * dpct / 100 + dfix);
    var taxAfter = sub > 0 ? tax * (sub - disc) / sub : 0;
    var total = sub - disc + taxAfter, paid = n((form.querySelector('[name=pay_amount]') || {}).value);
    set('sub', sub); set('disc', disc); set('tax', taxAfter); set('total', total); set('due', Math.max(0, total - paid));
  }
  function set(k, v) { var e = form.querySelector('[data-out=' + k + ']'); if (e) e.textContent = cur + ' ' + fmt(v); }
  function add() { var r = tpl.content.cloneNode(true); body.appendChild(r); wire(body.lastElementChild); calc(); }
  function wire(tr) {
    Array.prototype.forEach.call(tr.querySelectorAll('input'), function (i) { i.addEventListener('input', calc); });
    var rm = tr.querySelector('[data-rm]'); if (rm) rm.addEventListener('click', function () { if (body.children.length > 1) { tr.remove(); calc(); } });
    var pick = tr.querySelector('[data-pick]');
    if (pick) pick.addEventListener('change', function () {
      var o = pick.options[pick.selectedIndex]; if (!o || !o.value) return;
      tr.querySelector('[data-name]').value = o.getAttribute('data-name'); tr.querySelector('[data-p]').value = o.getAttribute('data-price') || 0;
      var c = tr.querySelector('[data-cat]'); if (c) c.value = o.getAttribute('data-cat') || '';
      var s = tr.querySelector('[data-stock]'); if (s) s.value = o.getAttribute('data-stock') || '';
      var cd = tr.querySelector('[data-code]'); if (cd) cd.value = o.getAttribute('data-code') || '';
      calc();
    });
  }
  Array.prototype.forEach.call(body.querySelectorAll('tr'), wire);
  var addBtn = form.querySelector('[data-add]'); if (addBtn) addBtn.addEventListener('click', add);
  Array.prototype.forEach.call(form.querySelectorAll('[name=discount_pct],[name=discount],[name=pay_amount]'), function (i) { i.addEventListener('input', calc); });
  calc();
})();
