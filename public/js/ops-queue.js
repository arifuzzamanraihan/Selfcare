// Selfcare original: work-list queue reorder (drag, buttons, keyboard) and patient field fill-in.
(function () {
  function tokenInput(form) {
    var sel = 'input[name="_csrf"],input[name="_token"],input[name="csrf"]';
    return (form && form.querySelector(sel)) || document.querySelector('form[data-reorder] ' + sel) || document.querySelector(sel);
  }
  function post(url, data, form) {
    var b = new URLSearchParams();
    var inp = tokenInput(form);
    if (inp) b.set(inp.name, inp.value);
    Object.keys(data).forEach(function (k) {
      if (Array.isArray(data[k])) data[k].forEach(function (v) { b.append(k + '[]', v); });
      else b.set(k, data[k]);
    });
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: b, credentials: 'same-origin', redirect: 'follow' });
  }

  document.querySelectorAll('[data-ops-queue]').forEach(function (box) {
    var form = box.querySelector('form[data-reorder]') || box.querySelector('form');
    var url = box.getAttribute('data-reorder-url');
    var rows, drag;
    function ids() {
      return Array.prototype.map.call(box.querySelectorAll('tbody tr[data-id]'), function (r) { return r.getAttribute('data-id'); });
    }
    function save() {
      if (!url) return;
      post(url, { ids: ids() }, form).then(function (r) { if (!r.ok) location.reload(); });
    }
    rows = function () { return box.querySelectorAll('tbody tr[data-id]'); };
    Array.prototype.forEach.call(rows(), function (tr) {
      tr.addEventListener('dragstart', function (e) {
        drag = tr; tr.classList.add('is-drag');
        if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', tr.getAttribute('data-id')); }
      });
      tr.addEventListener('dragend', function () { tr.classList.remove('is-drag'); drag = null; });
      tr.addEventListener('dragover', function (e) { e.preventDefault(); if (!drag || drag === tr) return; var tb = tr.parentNode; var mid = tr.getBoundingClientRect().top + tr.offsetHeight / 2; tb.insertBefore(drag, e.clientY < mid ? tr : tr.nextSibling); });
      tr.addEventListener('drop', function (e) { e.preventDefault(); save(); });
      tr.addEventListener('keydown', function (e) {
        if ((e.altKey || e.metaKey) && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
          e.preventDefault();
          var btn = tr.querySelector(e.key === 'ArrowUp' ? '[data-move=up]' : '[data-move=down]');
          if (btn) btn.click();
        }
      });
    });
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-move]');
      if (!b) return;
      e.preventDefault();
      var tr = b.closest('tr[data-id]');
      if (!tr) return;
      if (b.getAttribute('data-move') === 'up' && tr.previousElementSibling) tr.parentNode.insertBefore(tr, tr.previousElementSibling);
      if (b.getAttribute('data-move') === 'down' && tr.nextElementSibling) tr.parentNode.insertBefore(tr.nextElementSibling, tr);
      save();
      tr.focus();
    });
  });

  document.querySelectorAll('select[data-fill-patient]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var opt = sel.options[sel.selectedIndex];
      if (!opt) return;
      var name = opt.getAttribute('data-name') || '';
      var phone = opt.getAttribute('data-phone') || '';
      var form = sel.form;
      if (!form) return;
      var title = form.querySelector('#f_title, [name=title]');
      var ph = form.querySelector('#f_phone, [name=phone]');
      if (title && name) title.value = name;
      if (ph && phone) ph.value = phone;
    });
  });
})();
