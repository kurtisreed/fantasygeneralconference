(function () {
  'use strict';

  var form = document.getElementById('sheet');
  if (!form) return;

  // "×" clears an apostle row so a mis-tap isn't permanent.
  form.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.clear-row');
    if (!btn) return;
    form.querySelectorAll('input[name="q[' + btn.dataset.q + ']"]').forEach(function (i) {
      i.checked = false;
    });
    updateCount();
  });

  var answered = document.getElementById('answered');
  var total = document.getElementById('total');
  if (!answered || !total) return;

  // Count each question once, whether it's a radio group or a text/number field.
  var names = {};
  form.querySelectorAll('input[name^="q["], select[name^="q["]').forEach(function (el) {
    if (el.disabled) return;
    names[el.name] = true;
  });
  var keys = Object.keys(names);
  total.textContent = String(keys.length);

  function updateCount() {
    var n = 0;
    keys.forEach(function (name) {
      var els = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');
      var filled = false;
      els.forEach(function (el) {
        if (el.type === 'radio' || el.type === 'checkbox') {
          if (el.checked) filled = true;
        } else if (el.value.trim() !== '') {
          filled = true;
        }
      });
      if (filled) n++;
    });
    answered.textContent = String(n);
  }

  form.addEventListener('change', updateCount);
  form.addEventListener('input', updateCount);
  updateCount();
})();
