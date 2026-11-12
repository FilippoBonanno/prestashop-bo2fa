<div class="e2fa-page">
  <h1 class="e2fa-title">Verifica in due passaggi — dipendenti</h1>

  {if $bo2fa_unprotected}
    <div class="alert alert-danger">
      Questi profili non hanno il permesso «visualizza» sul modulo, quindi per loro la verifica non viene chiesta:
      <strong>{$bo2fa_unprotected|escape:'html':'UTF-8'}</strong>.
      Premi «Salva» qui sotto per ripristinarlo.
    </div>
  {/if}

  <form method="post" action="{$bo2fa_action|escape:'html':'UTF-8'}" class="e2fa-card e2fa-profiles">
    <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
    <h2 style="margin-right:8px">Obbligatoria per profilo</h2>
    {foreach $bo2fa_profiles as $profile}
      <label class="e2fa-check"><input type="checkbox" name="profiles[]" value="{$profile.id_profile|intval}" {if in_array($profile.id_profile|intval, $bo2fa_forced)}checked{/if}> {$profile.name|escape:'html':'UTF-8'}</label>
    {/foreach}
    <button type="submit" name="submitBo2faProfiles" value="1" class="e2fa-btn e2fa-btn-primary" style="margin-left:auto">Salva</button>
  </form>

  <div class="e2fa-table-wrap">
    <table class="e2fa-table">
      <thead>
        <tr><th scope="col">Dipendente</th><th scope="col">Profilo</th><th scope="col">Stato</th><th scope="col">Obbligo</th><th scope="col">Codici backup</th><th scope="col">Dispositivi ricordati</th><th scope="col">Azioni</th></tr>
      </thead>
      <tbody>
      {foreach $bo2fa_employees as $e}
        <tr>
          <td data-label="Dipendente"><span><strong>{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</strong><br><span class="e2fa-muted" style="font-size:12px">{$e.email|escape:'html':'UTF-8'}</span></span></td>
          <td data-label="Profilo">{$e.profile_name|escape:'html':'UTF-8'}</td>
          <td data-label="Stato">
            {if $e.enabled}<span class="e2fa-badge e2fa-badge-on">Attiva</span>
            {elseif $e.required}<span class="e2fa-badge e2fa-badge-todo">Da attivare</span>
            {else}<span class="e2fa-badge e2fa-badge-off">Non attiva</span>{/if}
          </td>
          <td data-label="Obbligo">
            {if $e.profile_forced}Da profilo
            {else}
              <form method="post" action="{$bo2fa_action|escape:'html':'UTF-8'}" class="e2fa-inline-form">
                <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
                <input type="hidden" name="id_employee" value="{$e.id_employee|intval}">
                <input type="hidden" name="forced" value="{if $e.forced}0{else}1{/if}">
                <button type="submit" name="submitBo2faForce" value="1" class="e2fa-btn e2fa-btn-small">{if $e.forced}Forzata · togli{else}Rendi obbligatoria{/if}</button>
              </form>
            {/if}
          </td>
          <td data-label="Codici backup">{if $e.enabled}<span {if $e.codes_left <= 2}class="e2fa-danger"{/if}>{$e.codes_left|intval} di 10</span>{else}—{/if}</td>
          <td data-label="Dispositivi ricordati">{if $e.enabled}{$e.devices|intval}{else}—{/if}</td>
          <td data-label="Azioni">
            {if $e.enabled}
              <form method="post" action="{$bo2fa_action|escape:'html':'UTF-8'}" class="e2fa-inline-form">
                <input type="hidden" name="bo2fa_csrf" value="{$bo2fa_csrf|escape:'html':'UTF-8'}">
                <input type="hidden" name="id_employee" value="{$e.id_employee|intval}">
                <button type="submit" name="submitBo2faRevoke" value="1" class="e2fa-btn e2fa-btn-small">Revoca dispositivi</button>
                <button type="submit" name="submitBo2faReset" value="1" class="e2fa-btn e2fa-btn-small e2fa-btn-danger" onclick="return confirm('Azzerare la verifica di questo dipendente? Chiave, codici di backup e dispositivi ricordati verranno eliminati.');">Azzera 2FA</button>
              </form>
            {else}—{/if}
          </td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>
  <p class="e2fa-muted" style="font-size:12px">«Azzera 2FA» cancella chiave, codici e dispositivi ricordati: al prossimo accesso il dipendente dovrà riattivarla se obbligatoria. Ogni azione finisce nel log del back office (Parametri avanzati › Log).</p>
</div>
