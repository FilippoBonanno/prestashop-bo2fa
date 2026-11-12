<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Verifica in due passaggi</title>
<link rel="stylesheet" href="{$bo2fa_css|escape:'html':'UTF-8'}">
</head>
<body class="e2fa-verify-page">
<div class="e2fa-mobile-bar"><div class="e2fa-brand">{$bo2fa_shop|escape:'html':'UTF-8'} <span>· Back office</span></div></div>
<div class="e2fa-split">
  <aside class="e2fa-guide">
    <div class="e2fa-brand">{$bo2fa_shop|escape:'html':'UTF-8'} <span>· Back office</span></div>
    <h2>Dove trovo il codice?</h2>
    <ol class="e2fa-steps">
      <li><span class="e2fa-num" aria-hidden="true">1</span><span>Apri <strong>Google Authenticator</strong> sul tuo telefono.</span></li>
      <li><span class="e2fa-num" aria-hidden="true">2</span><span>Cerca la voce <strong>{$bo2fa_issuer|escape:'html':'UTF-8'}</strong>: ogni shop ha la sua voce.</span></li>
      <li><span class="e2fa-num" aria-hidden="true">3</span><span>Scrivi le 6 cifre prima che il cerchio si svuoti.</span></li>
    </ol>
    <p class="e2fa-guide-help">Problemi? Usa un codice di backup o chiedi al SuperAdmin di azzerare la tua verifica.</p>
  </aside>
  <main class="e2fa-form-wrap">
    <form class="e2fa-form" method="post" action="{$bo2fa_action|escape:'html':'UTF-8'}" autocomplete="off">
      <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
      <input type="hidden" name="mode" value="{$bo2fa_mode|escape:'html':'UTF-8'}">
      {if $bo2fa_mode == 'backup'}
        <h1>Usa un codice di backup</h1>
        <p class="e2fa-muted">Uno dei 10 codici salvati all'attivazione. Ogni codice vale una sola volta.</p>
        <label for="e2fa-backup">Codice di backup</label>
        <input id="e2fa-backup" name="backup_code" class="e2fa-input e2fa-input-mono" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX" required autofocus>
      {else}
        <h1>Verifica in due passaggi</h1>
        <p class="e2fa-muted">Accesso come <strong>{$bo2fa_email|escape:'html':'UTF-8'}</strong> · <a href="{$bo2fa_logout|escape:'html':'UTF-8'}">non sei tu?</a></p>
        <label for="e2fa-code">Codice a 6 cifre</label>
        <input id="e2fa-code" name="code" class="e2fa-input e2fa-input-code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" placeholder="000 000" required autofocus>
      {/if}
      <label class="e2fa-check"><input type="checkbox" name="remember" value="1"> Ricorda questo dispositivo per 365 giorni</label>
      {if $bo2fa_error}<div class="e2fa-alert e2fa-alert-error" role="alert">{$bo2fa_error|escape:'html':'UTF-8'}</div>{/if}
      <button type="submit" name="bo2fa_verify" value="1" class="e2fa-btn e2fa-btn-primary">Verifica e accedi</button>
      <div class="e2fa-links">
        {if $bo2fa_mode == 'backup'}
          <a class="e2fa-link" href="{$bo2fa_action|escape:'html':'UTF-8'}">← Torna al codice dell'app</a>
        {else}
          <a class="e2fa-link" href="{$bo2fa_backup_url|escape:'html':'UTF-8'}">Usa un codice di backup</a>
        {/if}
        <a class="e2fa-link" href="{$bo2fa_logout|escape:'html':'UTF-8'}">Esci</a>
      </div>
    </form>
  </main>
</div>
</body>
</html>
