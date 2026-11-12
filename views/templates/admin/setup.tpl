<div class="e2fa-page">
  <h1 class="e2fa-title">Verifica in due passaggi</h1>
  {if $bo2fa_error}<div class="e2fa-alert e2fa-alert-error" role="alert">{$bo2fa_error|escape:'html':'UTF-8'}</div>{/if}
  {if $bo2fa_message}<div class="e2fa-alert e2fa-alert-ok" role="status">{$bo2fa_message|escape:'html':'UTF-8'}</div>{/if}

  {if $bo2fa_state == 'codes'}
    <div class="e2fa-alert e2fa-alert-ok" role="status">Verifica in due passaggi attiva.</div>
    <section class="e2fa-card">
      <h2>Salva i tuoi 10 codici di backup</h2>
      <p class="e2fa-muted">Servono se perdi il telefono. Ognuno vale una volta. <strong>Non li vedrai più</strong>: potrai solo generarne di nuovi (i vecchi smettono di valere).</p>
      <ol id="e2fa-codes" class="e2fa-codes e2fa-print-area">
        {foreach $bo2fa_codes as $code}<li>{$code|escape:'html':'UTF-8'}</li>{/foreach}
      </ol>
      <div class="e2fa-actions">
        <button type="button" class="e2fa-btn" data-e2fa-download="e2fa-codes" data-e2fa-title="Codici di backup — {$bo2fa_issuer|escape:'html':'UTF-8'}">Scarica .txt</button>
        <button type="button" class="e2fa-btn e2fa-only-desktop" data-e2fa-print>Stampa</button>
        <button type="button" class="e2fa-btn" data-e2fa-copy="e2fa-codes">Copia</button>
      </div>
      <div class="e2fa-actions" style="border-top:1px solid #e6eaed;padding-top:14px;align-items:center">
        <label class="e2fa-check"><input type="checkbox" id="e2fa-saved"> Ho salvato i codici in un posto sicuro</label>
        <button type="button" class="e2fa-btn e2fa-btn-primary" data-e2fa-done="{$bo2fa_done_url|escape:'html':'UTF-8'}" disabled>Fine</button>
      </div>
    </section>

  {elseif $bo2fa_state == 'status'}
    <section class="e2fa-card">
      <h2>Stato: <span class="e2fa-badge e2fa-badge-on">Attiva</span></h2>
      <p class="e2fa-muted">Codici di backup rimasti: <span {if $bo2fa_codes_left <= 2}class="e2fa-danger"{/if}>{$bo2fa_codes_left|intval} di 10</span>.</p>
      <form method="post" action="{$bo2fa_url|escape:'html':'UTF-8'}" class="e2fa-card" style="padding:16px">
        <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
        <label for="e2fa-code-status" style="font-weight:600">Per rigenerare i codici{if !$bo2fa_required} o disattivare la verifica{/if}, inserisci un codice dell'app</label>
        <input id="e2fa-code-status" name="code" class="e2fa-input e2fa-input-code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" placeholder="000 000" required style="max-width:240px">
        <div class="e2fa-actions" style="justify-content:flex-start">
          <button type="submit" name="bo2fa_action" value="regenerate" class="e2fa-btn">Rigenera codici di backup</button>
          {if !$bo2fa_required}
            <button type="submit" name="bo2fa_action" value="disable" class="e2fa-btn e2fa-btn-danger" onclick="return confirm('Disattivare la verifica in due passaggi?');">Disattiva</button>
          {/if}
        </div>
      </form>
    </section>

  {else}
    {if $bo2fa_required}<div class="e2fa-alert e2fa-alert-warn" role="status">Il SuperAdmin ha reso obbligatoria la verifica per il tuo account: completa questi tre passi per continuare a usare il back office.</div>{/if}
    <ol class="e2fa-stepper">
      <li class="{if $bo2fa_step > 1}is-done{else}is-current{/if}" {if $bo2fa_step == 1}aria-current="step"{/if}><span class="e2fa-dot">{if $bo2fa_step > 1}✓{else}1{/if}</span>Installa l'app</li>
      <li class="{if $bo2fa_step > 2}is-done{elseif $bo2fa_step == 2}is-current{/if}" {if $bo2fa_step == 2}aria-current="step"{/if}><span class="e2fa-dot">{if $bo2fa_step > 2}✓{else}2{/if}</span>Collega</li>
      <li class="{if $bo2fa_step == 3}is-current{/if}" {if $bo2fa_step == 3}aria-current="step"{/if}><span class="e2fa-dot">3</span>Verifica e codici</li>
    </ol>

    {if $bo2fa_step == 1}
      <section class="e2fa-card">
        <h2>Installa Google Authenticator</h2>
        <p class="e2fa-muted">Scaricala dallo store del tuo telefono. Se ce l'hai già, vai avanti.</p>
        <div class="e2fa-actions" style="justify-content:flex-start">
          <a class="e2fa-link" href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener">App Store (iPhone)</a>
          <a class="e2fa-link" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener">Google Play (Android)</a>
        </div>
      </section>
      <div class="e2fa-actions" style="justify-content:flex-end">
        <a class="e2fa-btn e2fa-btn-primary" href="{$bo2fa_url|escape:'html':'UTF-8'}&amp;step=2">Avanti</a>
      </div>

    {elseif $bo2fa_step == 2}
      <section class="e2fa-card">
        <div class="e2fa-link-row">
          <div class="e2fa-only-desktop" style="flex:none;min-width:0;align-items:center">
            <div class="e2fa-qr" data-e2fa-qr="{$bo2fa_uri|escape:'html':'UTF-8'}"></div>
            <span class="e2fa-muted" style="font-size:12px">Generato nel browser, nessun servizio esterno</span>
          </div>
          <div>
            <h2>Collega Google Authenticator</h2>
            <p class="e2fa-muted e2fa-only-desktop">Nell'app tocca <strong>+</strong> › <strong>Scansiona un codice QR</strong>. Comparirà la voce «{$bo2fa_issuer|escape:'html':'UTF-8'}».</p>
            <p class="e2fa-muted e2fa-only-mobile">Sei sul telefono? Tocca il pulsante: l'account viene aggiunto all'app.</p>
            <a class="e2fa-btn e2fa-btn-primary e2fa-only-mobile" href="{$bo2fa_uri|escape:'html':'UTF-8'}">Apri in Authenticator</a>
            <span style="font-size:13px;font-weight:600">Oppure inserisci la chiave a mano</span>
            <div class="e2fa-key-row">
              <code id="e2fa-key" class="e2fa-key">{$bo2fa_key|escape:'html':'UTF-8'}</code>
              <button type="button" class="e2fa-btn" data-e2fa-copy="e2fa-key">Copia</button>
            </div>
            <span class="e2fa-muted" style="font-size:12px">Tipo: basato sull'ora · 6 cifre · 30 secondi</span>
            <details class="e2fa-only-mobile" style="flex-direction:column">
              <summary class="e2fa-link">Mostra QR (per un altro telefono)</summary>
              <div class="e2fa-qr" data-e2fa-qr="{$bo2fa_uri|escape:'html':'UTF-8'}"></div>
            </details>
          </div>
        </div>
      </section>
      <div class="e2fa-actions">
        <a class="e2fa-btn" href="{$bo2fa_url|escape:'html':'UTF-8'}&amp;step=1">Indietro</a>
        <a class="e2fa-btn e2fa-btn-primary" href="{$bo2fa_url|escape:'html':'UTF-8'}&amp;step=3">Ho aggiunto l'account → Verifica</a>
      </div>

    {else}
      <form method="post" action="{$bo2fa_url|escape:'html':'UTF-8'}&amp;step=3" class="e2fa-card">
        <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
        <h2>Conferma con un codice</h2>
        <p class="e2fa-muted">Scrivi le 6 cifre che l'app mostra ora sotto «{$bo2fa_issuer|escape:'html':'UTF-8'}».</p>
        <label for="e2fa-code-enable" style="font-weight:600">Codice a 6 cifre</label>
        <input id="e2fa-code-enable" name="code" class="e2fa-input e2fa-input-code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" placeholder="000 000" required autofocus style="max-width:240px">
        <div class="e2fa-actions">
          <a class="e2fa-btn" href="{$bo2fa_url|escape:'html':'UTF-8'}&amp;step=2">Indietro</a>
          <button type="submit" name="bo2fa_action" value="enable" class="e2fa-btn e2fa-btn-primary">Attiva la verifica</button>
        </div>
      </form>
    {/if}
  {/if}
</div>
