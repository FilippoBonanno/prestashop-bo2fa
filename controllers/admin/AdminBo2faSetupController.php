<?php
require_once _PS_MODULE_DIR_ . 'bo2fa/vendor/autoload.php';

/* Wizard di attivazione (mockup 2A), schermata codici di backup, stato con rigenera/disattiva. */
class AdminBo2faSetupController extends ModuleAdminController
{
    private $step = 1;
    private $codes = [];
    private $error = '';
    private $message = '';

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/bo2fa.css?v=' . $this->module->version);
        $this->addJS($this->module->getPathUri() . 'views/js/qrcode.js?v=' . $this->module->version);
        $this->addJS($this->module->getPathUri() . 'views/js/bo2fa.js?v=' . $this->module->version);
    }

    private function pendingSecret()
    {
        $secret = (string) $this->context->cookie->bo2fa_pending;

        return preg_match('/^[A-Z2-7]{32}$/', $secret) ? $secret : '';
    }

    public function postProcess()
    {
        $employee = $this->context->employee;
        $row = Bo2faRepository::getEmployee($employee->id);
        $enabled = Bo2faRepository::isEnabled($row);
        $this->step = max(1, min(3, (int) Tools::getValue('step', 1)));

        if (!$enabled && $this->step >= 2 && $this->pendingSecret() === '') {
            $this->context->cookie->bo2fa_pending = Bo2faTotp::generateSecret();
            $this->context->cookie->write();
        }
        if (!Tools::isSubmit('bo2fa_action')) {
            return;
        }
        if (!Bo2faGate::csrfValid()) {
            $this->error = $this->module->l('Sessione scaduta: ricarica la pagina e riprova.');

            return;
        }

        $action = (string) Tools::getValue('bo2fa_action');
        if ($action === 'enable' && !$enabled) {
            $this->enable($employee);
        } elseif ($enabled && ($action === 'regenerate' || $action === 'disable')) {
            $this->runWithCode($employee, $row, $action);
        }
    }

    private function enable(Employee $employee)
    {
        $secret = $this->pendingSecret();
        $step = $secret === '' ? false : Bo2faTotp::verify($secret, Tools::getValue('code'), 0);
        if ($step === false) {
            $this->step = 3;
            $this->error = $this->module->l('Codice non valido. Controlla di aver aggiunto l\'account giusto e che l\'ora del telefono sia automatica.');

            return;
        }
        Bo2faRepository::enable($employee->id, $secret, $step);
        unset($this->context->cookie->bo2fa_pending);
        $this->codes = Bo2faBackupCodes::generate();
        Bo2faRepository::replaceBackupCodes($employee->id, $this->codes);
        Bo2faGate::markVerified(Bo2faRepository::getEmployee($employee->id));
        Bo2faRepository::log('Verifica in due passaggi attivata', $employee->id);
    }

    private function runWithCode(Employee $employee, array $row, $action)
    {
        if (!Bo2faRepository::beginAttempt($employee->id)) {
            $this->error = $this->module->l('Troppi tentativi errati. Riprova tra 15 minuti.');

            return;
        }
        $step = Bo2faTotp::verify(Bo2faRepository::decryptSecret($row), Tools::getValue('code'), $row['last_step']);
        if ($step === false || !Bo2faRepository::setLastStep($employee->id, $step)) {
            $left = Bo2faRepository::registerFailure($employee->id);
            $this->error = $left === 0
                ? $this->module->l('Troppi tentativi errati. Riprova tra 15 minuti.')
                : sprintf($this->module->l('Codice non valido. Tentativi rimasti: %d.'), $left);

            return;
        }
        if ($action === 'regenerate') {
            $this->codes = Bo2faBackupCodes::generate();
            Bo2faRepository::replaceBackupCodes($employee->id, $this->codes);
            Bo2faRepository::log('Codici di backup rigenerati', $employee->id);

            return;
        }
        if (Bo2faRepository::isRequired($employee, $row)) {
            $this->error = $this->module->l('La verifica è obbligatoria per il tuo account: non puoi disattivarla.');

            return;
        }
        Bo2faRepository::reset($employee->id);
        Bo2faGate::clearDeviceCookie();
        Bo2faRepository::log('Verifica in due passaggi disattivata dal dipendente', $employee->id, 2);
        $this->message = $this->module->l('Verifica in due passaggi disattivata.');
    }

    public function initContent()
    {
        $employee = $this->context->employee;
        $row = Bo2faRepository::getEmployee($employee->id);
        $enabled = Bo2faRepository::isEnabled($row);
        if (!$enabled && $this->step === 3 && $this->pendingSecret() === '') {
            $this->step = 2;
        }
        $secret = $this->pendingSecret();
        $issuer = (string) Configuration::getGlobalValue('BO2FA_ISSUER');
        $url = $this->context->link->getAdminLink('AdminBo2faSetup');

        $this->context->smarty->assign([
            'bo2fa_state' => $this->codes ? 'codes' : ($enabled ? 'status' : 'wizard'),
            'bo2fa_step' => $this->step,
            'bo2fa_codes' => $this->codes,
            'bo2fa_error' => $this->error,
            'bo2fa_message' => $this->message,
            'bo2fa_required' => Bo2faRepository::isRequired($employee, $row),
            'bo2fa_codes_left' => $enabled ? Bo2faRepository::countUnusedCodes($employee->id) : 0,
            'bo2fa_url' => $url,
            'bo2fa_done_url' => $this->context->link->getAdminLink('AdminDashboard'),
            'bo2fa_csrf' => Bo2faGate::csrfToken(),
            'bo2fa_key' => trim(chunk_split($secret, 4, ' ')),
            'bo2fa_uri' => $secret === '' ? '' : Bo2faTotp::uri($secret, $issuer, $employee->email),
            'bo2fa_issuer' => $issuer,
        ]);
        $this->content .= $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/setup.tpl');
        parent::initContent();
    }
}
