<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Bo2fa extends Module
{
    const HOOKS = [
        'actionAdminControllerInitBefore',
        'displayAdminNavBarBeforeEnd',
        'actionObjectEmployeeDeleteAfter',
        'actionObjectProfileAddAfter',
    ];

    const TABS = [
        'AdminBo2faVerify' => 'Verifica in due passaggi',
        'AdminBo2faSetup' => 'Attiva verifica in due passaggi',
    ];

    public function __construct()
    {
        $this->name = 'bo2fa';
        $this->tab = 'administration';
        $this->version = '1.0.4';
        $this->author = 'Filippo Bonanno';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.99.99'];
        parent::__construct();
        $this->displayName = $this->l('Autenticazione a due fattori (2FA) per il back office');
        $this->description = $this->l('Codice a 6 cifre per ogni dipendente del back office.');
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTabs()
            && $this->registerHook(self::HOOKS)
            && Configuration::updateGlobalValue('BO2FA_FORCED_PROFILES', '')
            && Configuration::updateGlobalValue('BO2FA_ISSUER', Configuration::get('PS_SHOP_NAME') . ' BO (' . Configuration::get('PS_SHOP_DOMAIN') . ')'); // il dominio distingue gli shop, il nome negozio no
    }

    public function uninstall()
    {
        foreach (array_keys(self::TABS) as $class) {
            $idTab = (int) Tab::getIdFromClassName($class);
            if ($idTab) {
                (new Tab($idTab))->delete();
            }
        }
        Configuration::deleteByName('BO2FA_FORCED_PROFILES');
        Configuration::deleteByName('BO2FA_ISSUER');

        return $this->uninstallDb() && parent::uninstall();
    }

    private function installDb()
    {
        $engine = _MYSQL_ENGINE_;
        $p = _DB_PREFIX_;
        $sql = [
            "CREATE TABLE IF NOT EXISTS `{$p}bo2fa_employee` (
                `id_employee` INT UNSIGNED NOT NULL,
                `secret` TEXT NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `forced` TINYINT(1) NOT NULL DEFAULT 0,
                `date_enabled` DATETIME NULL DEFAULT NULL,
                `last_step` BIGINT NOT NULL DEFAULT 0,
                `failed_attempts` INT NOT NULL DEFAULT 0,
                `locked_until` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id_employee`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bo2fa_backup_code` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_employee` INT UNSIGNED NOT NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `used_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `id_employee` (`id_employee`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bo2fa_trusted_device` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_employee` INT UNSIGNED NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `employee_token` (`id_employee`, `token_hash`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb()
    {
        $p = _DB_PREFIX_;

        return Db::getInstance()->execute(
            "DROP TABLE IF EXISTS `{$p}bo2fa_employee`, `{$p}bo2fa_backup_code`, `{$p}bo2fa_trusted_device`"
        );
    }

    private function installTabs()
    {
        foreach (self::TABS as $class => $label) {
            if (Tab::getIdFromClassName($class)) {
                continue;
            }
            $tab = new Tab();
            $tab->class_name = $class;
            $tab->module = $this->name;
            $tab->id_parent = -1;
            $tab->active = 1;
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[(int) $lang['id_lang']] = $label;
            }
            if (!$tab->add()) {
                return false;
            }
        }
        $this->grantTabAccess();

        return true;
    }

    /*
     * Verifica e Attivazione devono essere raggiungibili da ogni profilo, anche quelli creati dopo.
     * Serve anche il permesso "visualizza" sul modulo: Hook::exec salta gli hook dei moduli che il
     * profilo non può vedere, e senza il controllo in actionAdminControllerInitBefore la 2FA non scatta.
     */
    public function grantTabAccess()
    {
        $access = new Access();
        $profiles = Profile::getProfiles((int) Configuration::get('PS_LANG_DEFAULT'));
        foreach (array_keys(self::TABS) as $class) {
            $idTab = (int) Tab::getIdFromClassName($class);
            foreach ($profiles as $profile) {
                foreach (['view', 'add', 'edit'] as $right) {
                    $access->updateLgcAccess((int) $profile['id_profile'], $idTab, $right, true);
                }
            }
        }
        foreach ($profiles as $profile) {
            $access->updateLgcModuleAccess((int) $profile['id_profile'], (int) $this->id, 'view', true);
        }
    }

    /* Profili senza permesso "visualizza" sul modulo: per loro gli hook non partono e la 2FA non viene chiesta. */
    private function unprotectedProfiles()
    {
        $p = _DB_PREFIX_;

        return Db::getInstance()->executeS(
            "SELECT pl.name FROM `{$p}profile_lang` pl
             WHERE pl.id_lang = " . (int) $this->context->language->id . '
               AND pl.id_profile <> ' . (int) _PS_ADMIN_PROFILE_ . "
               AND pl.id_profile NOT IN (
                   SELECT ma.id_profile FROM `{$p}module_access` ma
                   JOIN `{$p}authorization_role` ar ON ar.id_authorization_role = ma.id_authorization_role
                   WHERE ar.slug = 'ROLE_MOD_MODULE_BO2FA_READ')"
        );
    }

    public function hookActionAdminControllerInitBefore($params)
    {
        $controller = isset($params['controller']) ? $params['controller'] : null;
        $name = ($controller && !empty($controller->controller_name)) ? $controller->controller_name : (string) Tools::getValue('controller');
        $target = Bo2faGate::decide($name);
        if ($target === null) {
            return;
        }
        if (Bo2faGate::isAjaxRequest()) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode(['error' => '2fa_required']);
            exit;
        }
        Tools::redirectAdmin(Bo2faGate::redirectUrl($target));
    }

    public function hookActionObjectEmployeeDeleteAfter($params)
    {
        if (isset($params['object']) && $params['object']->id) {
            Bo2faRepository::purge((int) $params['object']->id);
        }
    }

    /* Un profilo nuovo deve poter raggiungere Verifica e Attivazione, altrimenti i suoi dipendenti restano chiusi fuori. */
    public function hookActionObjectProfileAddAfter($params)
    {
        $this->grantTabAccess();
    }

    public function hookDisplayAdminNavBarBeforeEnd()
    {
        $employee = $this->context->employee;
        if (!$employee || !$employee->id) {
            return '';
        }
        // Qui l'<head> è già stato stampato: il CSS arriva come <link> nel template, non con addCSS.
        $this->context->smarty->assign([
            'bo2fa_css' => $this->_path . 'views/css/bo2fa.css?v=' . $this->version,
            'bo2fa_setup_url' => $this->context->link->getAdminLink('AdminBo2faSetup'),
            'bo2fa_employee_id' => (int) $employee->id,
            'bo2fa_enabled' => Bo2faRepository::isEnabled(Bo2faRepository::getEmployee($employee->id)),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/nav_link.tpl');
    }

    public function getContent()
    {
        if ((int) $this->context->employee->id_profile !== (int) _PS_ADMIN_PROFILE_) {
            return $this->displayError($this->l('Solo il SuperAdmin può gestire la verifica in due passaggi.'));
        }
        $output = $this->processManage();
        $this->context->controller->addCSS($this->_path . 'views/css/bo2fa.css?v=' . $this->version);

        $forced = Bo2faRepository::getForcedProfiles();
        $employees = [];
        foreach (Bo2faRepository::listEmployees() as $row) {
            $row['profile_forced'] = in_array((int) $row['id_profile'], $forced, true);
            $row['required'] = $row['profile_forced'] || (int) $row['forced'] === 1;
            $employees[] = $row;
        }
        $this->context->smarty->assign([
            'bo2fa_profiles' => Profile::getProfiles((int) $this->context->language->id),
            'bo2fa_forced' => $forced,
            'bo2fa_unprotected' => implode(', ', array_column($this->unprotectedProfiles() ?: [], 'name')),
            'bo2fa_employees' => $employees,
            'bo2fa_action' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]),
            'bo2fa_csrf' => Bo2faGate::csrfToken(),
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/manage.tpl');
    }

    private function processManage()
    {
        $submits = ['submitBo2faProfiles', 'submitBo2faForce', 'submitBo2faReset', 'submitBo2faRevoke'];
        $submitted = array_values(array_filter($submits, function ($name) {
            return Tools::isSubmit($name);
        }));
        if (!$submitted) {
            return '';
        }
        if (!Bo2faGate::csrfValid()) {
            return $this->displayError($this->l('Sessione scaduta: ricarica la pagina e riprova.'));
        }
        $me = (int) $this->context->employee->id;

        if ($submitted[0] === 'submitBo2faProfiles') {
            $ids = Tools::getValue('profiles');
            Bo2faRepository::setForcedProfiles(is_array($ids) ? array_filter($ids, 'is_scalar') : []);
            $this->grantTabAccess();
            Bo2faRepository::log('Profili con verifica obbligatoria: ' . implode(',', Bo2faRepository::getForcedProfiles()), $me);

            return $this->displayConfirmation($this->l('Regole salvate.'));
        }

        $target = new Employee((int) Tools::getValue('id_employee'));
        if (!Validate::isLoadedObject($target)) {
            return $this->displayError($this->l('Dipendente non trovato.'));
        }
        if ($submitted[0] === 'submitBo2faForce') {
            $forced = (int) Tools::getValue('forced') === 1;
            Bo2faRepository::setForced($target->id, $forced);
            Bo2faRepository::log(($forced ? 'Obbligo attivato' : 'Obbligo tolto') . ' per dipendente ' . $target->id, $me);

            return $this->displayConfirmation($this->l('Obbligo aggiornato.'));
        }
        if ($submitted[0] === 'submitBo2faReset') {
            Bo2faRepository::reset($target->id);
            Bo2faRepository::log('2FA azzerata dal SuperAdmin per dipendente ' . $target->id, $me, 2);

            return $this->displayConfirmation($this->l('Verifica azzerata: al prossimo accesso il dipendente dovrà riattivarla se obbligatoria.'));
        }
        Bo2faRepository::revokeDevices($target->id);
        Bo2faRepository::log('Dispositivi ricordati revocati per dipendente ' . $target->id, $me);

        return $this->displayConfirmation($this->l('Dispositivi revocati.'));
    }
}
