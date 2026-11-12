/* bo2fa: QR locale, copia, download e stampa codici, abilitazione pulsante Fine. */
/* Il BO carica lo script nell'<head>: si parte solo a DOM pronto. */
(function (init) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(function () {
  'use strict';

  var qrs = document.querySelectorAll('[data-e2fa-qr]');
  for (var i = 0; i < qrs.length; i++) {
    if (typeof window.qrcode !== 'function') {
      break;
    }
    var qr = window.qrcode(0, 'M');
    qr.addData(qrs[i].getAttribute('data-e2fa-qr'));
    qr.make();
    qrs[i].innerHTML = qr.createSvgTag(5, 8);
    var svg = qrs[i].querySelector('svg');
    if (svg) {
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Codice QR da scansionare con Google Authenticator');
    }
  }

  function textOf(id) {
    var el = document.getElementById(id);
    return el ? el.innerText.trim() : '';
  }

  document.addEventListener('click', function (e) {
    var copy = e.target.closest('[data-e2fa-copy]');
    if (copy && navigator.clipboard) {
      navigator.clipboard.writeText(textOf(copy.getAttribute('data-e2fa-copy')).replace(/\s+/g, ' ')).then(function () {
        copy.textContent = 'Copiato';
      });
    }
    var dl = e.target.closest('[data-e2fa-download]');
    if (dl) {
      var body = dl.getAttribute('data-e2fa-title') + '\n\n' + textOf(dl.getAttribute('data-e2fa-download')) + '\n';
      var a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([body], { type: 'text/plain;charset=utf-8' }));
      a.download = 'codici-backup-2fa.txt';
      document.body.appendChild(a);
      a.click();
      a.remove();
    }
    if (e.target.closest('[data-e2fa-print]')) {
      window.print();
    }
    var done = e.target.closest('[data-e2fa-done]');
    if (done && !done.disabled) {
      window.location.href = done.getAttribute('data-e2fa-done');
    }
  });

  var saved = document.getElementById('e2fa-saved');
  var doneBtn = document.querySelector('[data-e2fa-done]');
  if (saved && doneBtn) {
    saved.addEventListener('change', function () {
      doneBtn.disabled = !saved.checked;
    });
  }
});
