# bo2fa · verifica in due passaggi per il back office PrestaShop

Modulo sviluppato da **Filippo Bonanno**. Un solo codice per PrestaShop 1.7.8, 8.2 e 9.x, provato su copie Docker offline di negozi reali. Preparato anche per [A.B. Tecnology](https://github.com/FilippoBonanno/abtecnology-prestashop).

- TOTP RFC 6238 (Google Authenticator e app compatibili), implementato senza librerie esterne; QR generato nel browser.
- Attivazione guidata, 10 codici di backup monouso (salvati come hash), "ricorda questo dispositivo" per 365 giorni.
- Il SuperAdmin rende la verifica obbligatoria per profilo o per singolo dipendente, azzera la 2FA e revoca i dispositivi.
- Il controllo vale su ogni richiesta del back office: pagine legacy (hook) e pagine Symfony (event subscriber su `kernel.request`).
- Tentativi limitati, token CSRF per sessione, log nel registro di PrestaShop. Test dei vettori RFC in `tests/selfcheck.php`.

---

## Guida d'uso

Compatibile con PrestaShop 1.7.8 → 9.x, PHP 7.4+. Nessuna chiamata a servizi esterni: il QR viene generato nel browser (libreria `views/js/qrcode.js`, qrcode-generator 1.4.4, licenza MIT).

## Installazione
1. Moduli › Carica un modulo › lo ZIP del modulo (cartella `bo2fa`).
2. Moduli › bo2fa › Configura: scegli quali profili hanno la verifica obbligatoria.
3. Ogni dipendente attiva la verifica dal proprio profilo (Dipendenti › Modifica): riga «Autenticazione a due fattori» sotto l'email.

## Uscita di emergenza (nessuno riesce a entrare)
1. Via FTP rinomina `modules/bo2fa` in `modules/bo2fa_off`.
2. Cancella le cartelle `var/cache/prod` e `var/cache/dev`. Senza questo passo la cache Symfony cerca ancora il modulo e il back office va in errore.
3. Accedi con email e password. Poi rinomina la cartella di nuovo in `bo2fa` e, da Moduli › bo2fa › Configura, azzera la 2FA del dipendente bloccato.

In alternativa, da database: `UPDATE <prefisso>module SET active = 0 WHERE name = 'bo2fa';` e poi il punto 2.

## Log
Parametri avanzati › Log: i messaggi del modulo iniziano con `[bo2fa]`.
