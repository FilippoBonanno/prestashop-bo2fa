<?php
require_once _PS_MODULE_DIR_ . 'bo2fa/vendor/autoload.php';

/* Pagina Verifica (mockup 1C): HTML completo, senza layout del BO. */
class AdminBo2faVerifyController extends ModuleAdminController
{
    private $error = '';
    private $mode = 'totp';

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function postProcess()
    {
        $employee = $this->context->employee;
        $row = Bo2faRepository::getEmployee($employee->id);
        if (!Bo2faRepository::isEnabled($row) || Bo2faGate::isVerified($row)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }
        $this->mode = Tools::getValue('mode') === 'backup' ? 'backup' : 'totp';
        if (!Tools::isSubmit('bo2fa_verify')) {
            return;
        }
        if (!Bo2faGate::csrfValid()) {
            $this->error = $this->module->l('Sessione scaduta: ricarica la pagina e riprova.');

            return;
        }
        if (!Bo2faRepository::beginAttempt($employee->id)) {
            $this->error = $this->module->l('Troppi tentativi errati. Riprova tra 15 minuti.');

            return;
        }

        $ok = false;
        if ($this->mode === 'backup') {
            $ok = Bo2faRepository::useBackupCode($employee->id, Tools::getValue('backup_code'));
            if ($ok) {
                Bo2faRepository::clearFailures($employee->id);
                Bo2faRepository::log('Accesso con codice di backup', $employee->id, 2);
            }
        } else {
            $step = Bo2faTotp::verify(Bo2faRepository::decryptSecret($row), Tools::getValue('code'), $row['last_step']);
            // setLastStep è atomico: false se lo stesso codice è già stato usato da un'altra richiesta.
            $ok = $step !== false && Bo2faRepository::setLastStep($employee->id, $step);
        }

        if (!$ok) {
            $left = Bo2faRepository::registerFailure($employee->id);
            if ($left === 0) {
                Bo2faRepository::log('Bloccato per troppi tentativi errati', $employee->id, 3);
                $this->error = $this->module->l('Troppi tentativi errati. Riprova tra 15 minuti.');
            } else {
                Bo2faRepository::log('Codice errato', $employee->id, 2);
                $this->error = sprintf(
                    $this->module->l('Codice non valido. Tentativi rimasti: %d. Se il codice sembra giusto, controlla che l\'ora del telefono sia automatica.'),
                    $left
                );
            }

            return;
        }

        Bo2faGate::markVerified($row);
        if (Tools::getValue('remember')) {
            $token = bin2hex(random_bytes(32));
            Bo2faRepository::addTrustedDevice($employee->id, $token, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
            Bo2faGate::setDeviceCookie($token);
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
    }

    /* PS 9 (LegacyController) chiama initContent() ma mai display(): la pagina si disegna da qui in tutte le versioni. */
    public function initContent()
    {
        $this->display();
    }

    public function display()
    {
        $url = $this->context->link->getAdminLink('AdminBo2faVerify');
        $this->context->smarty->assign([
            'bo2fa_error' => $this->error,
            'bo2fa_mode' => $this->mode,
            'bo2fa_email' => $this->context->employee->email,
            'bo2fa_issuer' => (string) Configuration::getGlobalValue('BO2FA_ISSUER'),
            'bo2fa_shop' => (string) Configuration::get('PS_SHOP_NAME'),
            'bo2fa_action' => $url,
            'bo2fa_backup_url' => $url . '&mode=backup',
            'bo2fa_csrf' => Bo2faGate::csrfToken(),
            'bo2fa_logout' => Bo2faGate::logoutUrl(),
            'bo2fa_css' => $this->module->getPathUri() . 'views/css/bo2fa.css?v=' . $this->module->version,
        ]);
        $html = $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/verify.tpl');
        // exit salta il salvataggio del cookie fatto da PrestaShop a fine richiesta: lo scriviamo prima di inviare output.
        $this->context->cookie->write();
        echo $html;
        exit;
    }
}
