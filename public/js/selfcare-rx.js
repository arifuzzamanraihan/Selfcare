/* Selfcare original JS. Prescription form: add/remove medicine lines, fill the patient details from the chosen patient. */
(function () {
  'use strict';
  var form = document.querySelector('[data-rx]');
  if (!form) return;
  var wrap = form.querySelector('[data-lines]'), tpl = form.querySelector('[data-tpl]');
  function renumber() { Array.prototype.forEach.call(wrap.querySelectorAll('.rx-line'), function (l, i) { var n = l.querySelector('[data-no]'); if (n) n.textContent = i + 1; }); }
  function wire(line) {
    var rm = line.querySelector('[data-rm]');
    if (rm) rm.addEventListener('click', function () {
      if (wrap.querySelectorAll('.rx-line').length > 1) { line.remove(); renumber(); }
      else { Array.prototype.forEach.call(line.querySelectorAll('input'), function (i) { i.value = ''; }); }
    });
  }
  Array.prototype.forEach.call(wrap.querySelectorAll('.rx-line'), wire);
  var add = form.querySelector('[data-add-line]');
  if (add) add.addEventListener('click', function () {
    if (wrap.querySelectorAll('.rx-line').length >= 30) return;
    wrap.appendChild(tpl.content.cloneNode(true)); wire(wrap.lastElementChild); renumber();
    var i = wrap.lastElementChild.querySelector('input'); if (i) i.focus();
  });
  // medicine master: choosing a suggestion fills strength, form, dose, how often, when and days (only the boxes that are still empty)
  var medsEl = document.getElementById('rx-meds'), meds = {};
  try { meds = medsEl ? JSON.parse(medsEl.textContent) : {}; } catch (e) { meds = {}; }
  wrap.addEventListener('change', function (ev) {
    var t = ev.target; if (!t || t.name !== 'drug[]') return;
    var m = meds[t.value]; if (!m) return;
    t.value = m.name;
    var line = t.closest('.rx-line');
    function fill(n, v) { var e = line.querySelector('[name="' + n + '[]"]'); if (e && v !== null && v !== '' && !e.value) e.value = v; }
    fill('strength', m.strength); fill('form', m.form); fill('route', m.route); fill('dose', m.dose); fill('freq', m.freq); fill('timing', m.timing); fill('dur_n', m.dur_n);
    if (m.dur_unit) { var u = line.querySelector('[name="dur_unit[]"]'); if (u && m.dur_n) u.value = m.dur_unit; }
  });
  var pick = form.querySelector('[data-patient]');
  function set(name, v) { var e = form.querySelector('[name=' + name + ']'); if (e && v !== null) e.value = v; }
  if (pick) pick.addEventListener('change', function () {
    var o = pick.options[pick.selectedIndex];
    if (!o || !o.value) return;
    set('patient_name', o.getAttribute('data-name')); set('patient_phone', o.getAttribute('data-phone')); set('sex', o.getAttribute('data-sex'));
    set('age', o.getAttribute('data-age')); set('allergies', o.getAttribute('data-allergies'));
  });
})();
