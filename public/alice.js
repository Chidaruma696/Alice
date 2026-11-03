/* Alice panel: inline saves and confirmations. No framework. */
(function () {
  'use strict';
  var strings = document.querySelector('.js-strings');
  if (!strings) { return; }
  var S = function (key, arg) {
    var tpl = strings.getAttribute('data-' + key) || key;
    return arg === undefined ? tpl : tpl.replace('%s', arg);
  };

  var flashTimer = null;
  function flash(text, bad) {
    var el = document.querySelector('.flash');
    if (!el) { el = document.createElement('div'); el.className = 'flash'; document.body.appendChild(el); }
    el.textContent = text;
    el.classList.toggle('bad', !!bad);
    clearTimeout(flashTimer);
    flashTimer = setTimeout(function () { el.remove(); }, bad ? 7000 : 2500);
  }

  function api(method, path, body) {
    return fetch(path, {
      method: method,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().catch(function () { return { ok: false, error: res.status + ' ' + res.statusText }; }).then(function (data) {
        if (res.status === 401 && data.pin_url) {
          window.location = data.pin_url + '?return=' + encodeURIComponent(window.location.pathname + window.location.search);
          throw new Error('pin required');
        }
        if (!res.ok || data.ok === false) { throw new Error(data.error || (res.status + ' ' + res.statusText)); }
        return data;
      });
    });
  }

  function busy(el, promise, successText, reload) {
    if (el && el.tagName === 'BUTTON') { el.disabled = true; }
    return promise.then(function (data) {
      flash(typeof successText === 'function' ? successText(data) : (successText || S('saved')));
      if (reload) { setTimeout(function () { window.location.reload(); }, 400); }
      return data;
    }).catch(function (err) {
      if (err.message !== 'pin required') { flash(S('error', err.message), true); }
    }).finally(function () {
      if (el && el.tagName === 'BUTTON') { el.disabled = false; }
    });
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-action]');
    if (!el) { return; }
    var action = el.getAttribute('data-action');
    var key = el.getAttribute('data-key');
    switch (action) {
      case 'test':
        ev.preventDefault();
        busy(el, api('POST', '/api/printers/' + encodeURIComponent(key) + '/test'), function (d) { return S('sent', d.job); }, true);
        break;
      case 'reprint':
        ev.preventDefault();
        busy(el, api('POST', '/api/printers/' + encodeURIComponent(key) + '/reprint'), function (d) { return S('sent', d.job); }, true);
        break;
      case 'remove-printer':
        ev.preventDefault();
        if (window.confirm(S('confirm-remove-printer', el.getAttribute('data-name')))) {
          busy(el, api('DELETE', '/api/printers/' + encodeURIComponent(key)), null, true);
        }
        break;
      case 'add-installed':
        ev.preventDefault();
        busy(el, api('POST', '/api/printers', { name: el.getAttribute('data-name'), kind: 'local' }), function (d) { return S('added', d.printer.key); }, true);
        break;
      case 'remove-station':
        ev.preventDefault();
        var ip = el.getAttribute('data-ip');
        if (window.confirm(S('confirm-remove-station', ip))) {
          busy(el, api('DELETE', '/api/stations/' + encodeURIComponent(ip)), null, true);
        }
        break;
      case 'refresh-installed':
        ev.preventDefault();
        busy(el, api('GET', '/api/printers/installed?refresh=1'), null, true);
        break;
      case 'set-dry-run':
        ev.preventDefault();
        busy(el, api('PUT', '/api/config', { dry_run: el.getAttribute('data-value') === 'true' }), null, true);
        break;
      case 'clear-pin':
        ev.preventDefault();
        busy(el, api('DELETE', '/api/pin'), null, true);
        break;
    }
  });

  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.matches('select[data-action="set-default"]')) {
      busy(null, api('PUT', '/api/default-printer', { printer: el.value || null }), null, true);
      return;
    }
    var row = el.closest('tr[data-station]');
    if (row && el.hasAttribute('data-field')) {
      var body = {};
      body[el.getAttribute('data-field')] = el.value;
      busy(null, api('PUT', '/api/stations/' + encodeURIComponent(row.getAttribute('data-station')), body));
    }
  });

  document.addEventListener('submit', function (ev) {
    var form = ev.target.closest('form[data-form]');
    if (!form) { return; }
    ev.preventDefault();
    var fields = {};
    Array.prototype.forEach.call(form.elements, function (f) { if (f.name) { fields[f.name] = f.value; } });
    var button = form.querySelector('button[type="submit"]');
    switch (form.getAttribute('data-form')) {
      case 'add-printer':
        busy(button, api('POST', '/api/printers', { name: fields.name, destination: fields.destination }), function (d) { return S('added', d.printer.key); }, true);
        break;
      case 'add-station':
        busy(button, api('PUT', '/api/stations', { ip: fields.ip, name: fields.name || '' }), null, true);
        break;
      case 'set-pin':
        busy(button, api('PUT', '/api/pin', { pin: fields.pin }), null, true);
        break;
    }
  });
})();
