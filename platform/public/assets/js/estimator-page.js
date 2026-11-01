/* Public estimator: published rates only; native POST fallback, no client-side persistence of personal details. */
(function () {
  'use strict';
  var doc = document, form = doc.getElementById('est-form');
  if (!form) return;
  var notice = doc.getElementById('est-unavailable');
  var result = doc.getElementById('est-result');
  var btn = doc.getElementById('est-submit');
  var msg = doc.getElementById('est-msg');
  var q = doc.getElementById('est-qty'), slider = doc.getElementById('est-qty-range');
  var R = window.WX_RATES;
  var pending = false;
  var state = { service: 'renovation', finish: 'standard', qty: 1000 };

  function fail() {
    notice.hidden = false;
    result.hidden = true;
    btn.disabled = true;
    doc.querySelectorAll('.est-app .est-card').forEach(function (card) { card.hidden = true; });
  }
  function validRates() {
    return R && Array.isArray(R.services) && R.services.length > 0 &&
      Array.isArray(R.finishes) && R.finishes.length > 0 &&
      R.finishes.every(function (f) { return f && typeof f.key === 'string' && typeof f.label === 'string'; }) &&
      R.services.every(function (s) {
        return s && typeof s.key === 'string' && typeof s.label === 'string' && s.rates &&
          ['essential', 'premium'].concat(R.finishes.map(function (f) { return f.key; })).every(function (key) {
            return typeof s.rates[key] === 'number' && Number.isFinite(s.rates[key]) && s.rates[key] >= 0;
          });
      });
  }
  if (!validRates()) { fail(); return; }
  if (!R.services.some(function (s) { return s.key === state.service; })) state.service = R.services[0].key;
  if (!R.finishes.some(function (f) { return f.key === state.finish; })) state.finish = R.finishes[0].key;
  function svc() { return R.services.find(function (s) { return s.key === state.service; }); }
  function fmt(n) {
    n = Math.round(n);
    if (n >= 10000000) return 'Rs ' + (n / 10000000).toFixed(n % 10000000 ? 1 : 0).replace(/\.0$/, '') + ' crore';
    if (n >= 100000) return 'Rs ' + (n / 100000).toFixed(n % 100000 ? 1 : 0).replace(/\.0$/, '') + ' lac';
    return 'Rs ' + n.toLocaleString('en-PK');
  }
  function validQuantity() {
    return /^\d+(?:\.\d{1,3})?$/.test(q.value) && Number.isFinite(Number(q.value)) &&
      Number(q.value) >= Number(q.min) && Number(q.value) <= Number(q.max) &&
      (svc().unit !== 'views' || Number.isInteger(Number(q.value)));
  }
  function calc() {
    var s = svc();
    return { low: s.rates.essential * state.qty, high: s.rates.premium * state.qty, sel: s.rates[state.finish] * state.qty };
  }
  function hidden(name, value) { form.querySelector('[name="' + name + '"]').value = value; }
  function clearSnapshot() { ['service', 'area', 'finish', 'estimate'].forEach(function (name) { hidden(name, ''); }); }
  function renderEstimate() {
    btn.disabled = pending || !validQuantity();
    if (!validQuantity()) {
      result.hidden = true;
      clearSnapshot();
      msg.textContent = 'Enter a valid ' + (svc().unit === 'views' ? 'whole number of views' : 'area') + ' between ' + q.min + ' and ' + q.max + '.';
      msg.className = 'est-msg err';
      return;
    }
    state.qty = Number(q.value);
    var s = svc(), c = calc();
    result.hidden = false;
    doc.getElementById('est-low').textContent = fmt(c.low);
    doc.getElementById('est-high').textContent = fmt(c.high);
    var finish = R.finishes.find(function (f) { return f.key === state.finish; });
    var selection = doc.getElementById('est-sel');
    selection.textContent = 'Your selection: ';
    var strong = doc.createElement('strong');
    strong.textContent = s.label + ' · ' + Number(state.qty).toLocaleString('en-PK') + (s.unit === 'views' ? ' views' : ' sq ft') + ' · ' + finish.label + ' — ' + fmt(c.sel);
    selection.appendChild(strong);
    hidden('service', s.key); hidden('area', String(state.qty)); hidden('finish', state.finish);
    hidden('estimate', 'PKR ' + Math.round(c.low).toLocaleString() + ' to ' + Math.round(c.high).toLocaleString());
    if (msg.className.indexOf('err') !== -1 && msg.textContent.indexOf('Enter a valid ') === 0) {
      msg.textContent = ''; msg.className = 'est-msg';
    }
  }
  function syncQty() {
    var s = svc();
    doc.getElementById('est-qty-label').textContent = s.unitLabel;
    doc.getElementById('est-qty-sub').textContent = s.unit === 'views' ? 'How many photorealistic views do you need?' : 'Enter the approximate covered area.';
    doc.getElementById('est-qty-title').textContent = s.unit === 'views' ? 'How many views?' : 'How big is the space?';
    q.min = slider.min = s.unit === 'views' ? 1 : 100;
    q.max = slider.max = s.unit === 'views' ? 50 : 20000;
    q.step = s.unit === 'views' ? '1' : '0.001';
    slider.step = s.unit === 'views' ? 1 : 100;
    state.qty = Math.min(Math.max(state.qty || (s.unit === 'views' ? 4 : 1000), Number(q.min)), Number(q.max));
    q.value = slider.value = state.qty;
  }
  function renderServices() {
    var box = doc.getElementById('est-services'); box.textContent = '';
    R.services.forEach(function (s) {
      var b = doc.createElement('button'); b.type = 'button';
      b.className = 'est-svc' + (s.key === state.service ? ' on' : '');
      b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', String(s.key === state.service));
      var title = doc.createElement('strong'), hint = doc.createElement('span');
      title.textContent = s.label; hint.textContent = s.hint || ''; b.append(title, hint);
      b.onclick = function () { state.service = s.key; syncQty(); renderAll(); };
      box.appendChild(b);
    });
  }
  function renderFinishes() {
    var box = doc.getElementById('est-finishes'); box.textContent = '';
    R.finishes.forEach(function (f) {
      var b = doc.createElement('button'); b.type = 'button';
      b.className = 'est-fin' + (f.key === state.finish ? ' on' : '');
      b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', String(f.key === state.finish));
      var title = doc.createElement('strong'), hint = doc.createElement('span'), rate = doc.createElement('span');
      title.textContent = f.label; hint.textContent = f.hint || ''; rate.className = 'est-rate';
      rate.textContent = fmt(svc().rates[f.key]) + (svc().unit === 'views' ? ' / view' : ' / sq ft');
      b.append(title, hint, rate);
      b.onclick = function () { state.finish = f.key; renderAll(); };
      box.appendChild(b);
    });
  }
  function renderAll() { renderServices(); renderFinishes(); renderEstimate(); }
  q.addEventListener('input', function () { slider.value = q.value; renderEstimate(); });
  slider.addEventListener('input', function () { q.value = slider.value; renderEstimate(); });
  form.addEventListener('submit', function (event) {
    if (pending) { event.preventDefault(); return; }
    renderEstimate();
    if (!validQuantity() || !form.reportValidity()) { event.preventDefault(); return; }
    var forms = window.WXForms;
    // Valid calculation + no AJAX helper: use the explicit private native POST, not an unbound GET.
    if (!forms || typeof forms.send !== 'function' || typeof forms.waLink !== 'function') return;
    event.preventDefault();
    var data = {};
    new FormData(form).forEach(function (value, key) { if (key !== '_response') data[key] = String(value); });
    pending = true; btn.disabled = true; btn.textContent = 'Sending…'; msg.textContent = ''; msg.className = 'est-msg';
    Promise.resolve().then(function () { return forms.send('estimator', data, form); }).then(function (response) {
      if (!response || response.ok !== true || !Number.isInteger(response.id) || response.id < 1) {
        throw new Error('We could not confirm that your request was saved.');
      }
      form.hidden = true;
      var success = doc.getElementById('est-success'); success.classList.add('show');
      if (!success.querySelector('.wx-wa-btn')) {
        var p = doc.createElement('p'), link = doc.createElement('a'); p.style.marginTop = '16px';
        link.className = 'wx-wa-btn'; link.target = '_blank'; link.rel = 'noopener'; link.textContent = 'Continue on WhatsApp';
        link.href = forms.waLink(data, response.id, ['Estimate: ' + data.service + ', ' + data.area + ', ' + data.finish, data.estimate]);
        p.appendChild(link); success.appendChild(p);
      }
    }).catch(function (error) {
      msg.textContent = (error && error.message ? error.message : 'Could not send your request.') + ' Your details are still here. You can retry or contact us.';
      msg.className = 'est-msg err';
    }).finally(function () { pending = false; btn.textContent = 'Get my detailed quote'; btn.disabled = !validQuantity(); });
  });
  function guard() { if (window.WXForms && typeof window.WXForms.guard === 'function') window.WXForms.guard(form); }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', guard); else guard();
  syncQty(); renderAll(); notice.hidden = true;
})();
