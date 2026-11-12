<link rel="stylesheet" href="{$bo2fa_css|escape:'html':'UTF-8'}">
{* Nessun link visibile nel menu: solo i dati per la riga nel profilo. *}
<div class="e2fa-navlink" hidden data-employee="{$bo2fa_employee_id|intval}" data-enabled="{if $bo2fa_enabled}1{else}0{/if}" data-url="{$bo2fa_setup_url|escape:'html':'UTF-8'}"></div>
{literal}
<script>
  // Nel proprio profilo (Dipendenti > Modifica) aggiunge sotto l'email la riga della 2FA, clonando quella dell'email per prendere lo stile della versione.
  document.addEventListener('DOMContentLoaded', function () {
    var nav = document.querySelector('.e2fa-navlink');
    var m = location.pathname.match(/\/employees\/(\d+)\/edit/);
    var email = document.querySelector('[name="employee[email]"]');
    var row = email && email.closest('.form-group');
    if (!nav || !m || m[1] !== nav.getAttribute('data-employee') || !row) {
      return;
    }
    var enabled = nav.getAttribute('data-enabled') === '1';
    var copy = row.cloneNode(true);
    var col = copy.querySelector('[name="employee[email]"]');
    while (col.parentNode !== copy) {
      col = col.parentNode;
    }
    var label = copy.querySelector('label');
    if (label) {
      label.removeAttribute('for');
      label.textContent = 'Autenticazione a due fattori';
    }
    var status = document.createElement('span');
    status.className = 'e2fa-profile-status' + (enabled ? ' is-on' : '');
    status.textContent = enabled ? 'Attiva' : 'Non attiva';
    var btn = document.createElement('a');
    btn.className = 'btn btn-outline-secondary';
    btn.href = nav.getAttribute('data-url');
    btn.textContent = enabled ? 'Gestisci' : 'Attiva';
    col.innerHTML = '';
    col.appendChild(status);
    col.appendChild(btn);
    copy.classList.add('e2fa-profile-row');
    row.parentNode.insertBefore(copy, row.nextSibling);
  });
</script>
{/literal}
