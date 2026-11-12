<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/* Unico punto di accesso a tabelle, Configuration e log del modulo. Date sempre calcolate da MySQL (NOW()). */
class Bo2faRepository
{
    const MAX_FAILURES = 5;
    const LOCK_MINUTES = 15;
    const DEVICE_DAYS = 365;

    private static function t($name)
    {
        return '`' . _DB_PREFIX_ . 'bo2fa_' . $name . '`';
    }

    public static function getEmployee($idEmployee)
    {
        $row = Db::getInstance()->getRow('SELECT * FROM ' . self::t('employee') . ' WHERE id_employee = ' . (int) $idEmployee, false);

        return $row ?: null;
    }

    public static function isEnabled($row)
    {
        return $row && (int) $row['enabled'] === 1;
    }

    public static function isRequired(Employee $employee, $row)
    {
        return ($row && (int) $row['forced'] === 1)
            || in_array((int) $employee->id_profile, self::getForcedProfiles(), true);
    }

    public static function decryptSecret(array $row)
    {
        $encryption = new PhpEncryption(_NEW_COOKIE_KEY_);

        return (string) $encryption->decrypt($row['secret']);
    }

    public static function enable($idEmployee, $secretBase32, $step)
    {
        $encryption = new PhpEncryption(_NEW_COOKIE_KEY_);
        $secret = pSQL($encryption->encrypt($secretBase32));
        self::revokeDevices($idEmployee);

        return Db::getInstance()->execute(
            'INSERT INTO ' . self::t('employee')
            . ' (id_employee, secret, enabled, forced, date_enabled, last_step, failed_attempts, locked_until)'
            . ' VALUES (' . (int) $idEmployee . ", '" . $secret . "', 1, 0, NOW(), " . (int) $step . ', 0, NULL)'
            . " ON DUPLICATE KEY UPDATE secret = '" . $secret . "', enabled = 1, date_enabled = NOW(), last_step = " . (int) $step
            . ', failed_attempts = 0, locked_until = NULL'
        );
    }

    /* Disattiva la 2FA, cancella codici e dispositivi. Il flag "forced" resta. */
    public static function reset($idEmployee)
    {
        $id = (int) $idEmployee;
        $db = Db::getInstance();
        $ok = $db->execute(
            'UPDATE ' . self::t('employee') . " SET secret = '', enabled = 0, date_enabled = NULL, last_step = 0,"
            . ' failed_attempts = 0, locked_until = NULL WHERE id_employee = ' . $id
        );

        return $ok
            && $db->delete('bo2fa_backup_code', 'id_employee = ' . $id)
            && $db->delete('bo2fa_trusted_device', 'id_employee = ' . $id);
    }

    public static function purge($idEmployee)
    {
        $id = (int) $idEmployee;
        $db = Db::getInstance();

        return $db->delete('bo2fa_employee', 'id_employee = ' . $id)
            && $db->delete('bo2fa_backup_code', 'id_employee = ' . $id)
            && $db->delete('bo2fa_trusted_device', 'id_employee = ' . $id);
    }

    public static function setForced($idEmployee, $forced)
    {
        $f = $forced ? 1 : 0;

        return Db::getInstance()->execute(
            'INSERT INTO ' . self::t('employee') . ' (id_employee, secret, forced) VALUES (' . (int) $idEmployee . ", '', " . $f . ')'
            . ' ON DUPLICATE KEY UPDATE forced = ' . $f
        );
    }

    /* Consuma lo step in modo atomico: false se un'altra richiesta l'ha già usato (doppio invio / riuso). */
    public static function setLastStep($idEmployee, $step)
    {
        $db = Db::getInstance();
        $db->execute(
            'UPDATE ' . self::t('employee') . ' SET last_step = ' . (int) $step . ', failed_attempts = 0, locked_until = NULL'
            . ' WHERE id_employee = ' . (int) $idEmployee . ' AND last_step < ' . (int) $step
        );

        return (int) $db->Affected_Rows() === 1;
    }

    /* Prenota un tentativo prima di verificare il codice: false = bloccato.
       Il contatore sale in modo atomico, quindi anche N richieste parallele ottengono al massimo MAX_FAILURES tentativi. */
    public static function beginAttempt($idEmployee)
    {
        $id = (int) $idEmployee;
        $db = Db::getInstance();
        $t = self::t('employee');
        $db->execute('UPDATE ' . $t . ' SET failed_attempts = 0, locked_until = NULL WHERE id_employee = ' . $id
            . ' AND locked_until IS NOT NULL AND locked_until <= NOW()');
        self::lockIfExhausted($id);
        $db->execute('UPDATE ' . $t . ' SET failed_attempts = failed_attempts + 1 WHERE id_employee = ' . $id
            . ' AND failed_attempts < ' . self::MAX_FAILURES . ' AND locked_until IS NULL');

        return (int) $db->Affected_Rows() === 1;
    }

    public static function isLocked($idEmployee)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . self::t('employee') . ' WHERE id_employee = ' . (int) $idEmployee . ' AND locked_until > NOW()',
            false
        );
    }

    /* Dopo un codice errato (il tentativo è già contato da beginAttempt). Ritorna i tentativi rimasti; 0 = bloccato per LOCK_MINUTES. */
    public static function registerFailure($idEmployee)
    {
        $id = (int) $idEmployee;
        self::lockIfExhausted($id);
        $row = self::getEmployee($id);

        return $row ? max(0, self::MAX_FAILURES - (int) $row['failed_attempts']) : 0;
    }

    private static function lockIfExhausted($id)
    {
        Db::getInstance()->execute(
            'UPDATE ' . self::t('employee') . ' SET locked_until = DATE_ADD(NOW(), INTERVAL ' . self::LOCK_MINUTES . ' MINUTE)'
            . ' WHERE id_employee = ' . (int) $id . ' AND failed_attempts >= ' . self::MAX_FAILURES . ' AND locked_until IS NULL'
        );
    }

    public static function clearFailures($idEmployee)
    {
        return Db::getInstance()->execute(
            'UPDATE ' . self::t('employee') . ' SET failed_attempts = 0, locked_until = NULL WHERE id_employee = ' . (int) $idEmployee
        );
    }

    public static function replaceBackupCodes($idEmployee, array $codes)
    {
        $id = (int) $idEmployee;
        $db = Db::getInstance();
        $db->delete('bo2fa_backup_code', 'id_employee = ' . $id);
        foreach ($codes as $code) {
            $db->insert('bo2fa_backup_code', [
                'id_employee' => $id,
                'code_hash' => pSQL(Bo2faBackupCodes::hash($code)),
            ]);
        }
    }

    public static function useBackupCode($idEmployee, $input)
    {
        $plain = Bo2faBackupCodes::normalize($input);
        if (strlen($plain) !== 8) {
            return false;
        }
        $db = Db::getInstance();
        $rows = $db->executeS(
            'SELECT id, code_hash FROM ' . self::t('backup_code') . ' WHERE id_employee = ' . (int) $idEmployee . ' AND used_at IS NULL',
            true,
            false
        );
        foreach ((array) $rows as $row) {
            if (password_verify($plain, $row['code_hash'])) {
                $db->execute('UPDATE ' . self::t('backup_code') . ' SET used_at = NOW() WHERE id = ' . (int) $row['id'] . ' AND used_at IS NULL');

                return (int) $db->Affected_Rows() === 1;
            }
        }

        return false;
    }

    public static function countUnusedCodes($idEmployee)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . self::t('backup_code') . ' WHERE id_employee = ' . (int) $idEmployee . ' AND used_at IS NULL',
            false
        );
    }

    public static function addTrustedDevice($idEmployee, $token, $userAgent)
    {
        return Db::getInstance()->execute(
            'INSERT INTO ' . self::t('trusted_device') . ' (id_employee, token_hash, user_agent, created_at, expires_at) VALUES ('
            . (int) $idEmployee . ", '" . hash('sha256', $token) . "', '" . pSQL(Tools::substr((string) $userAgent, 0, 255)) . "',"
            . ' NOW(), DATE_ADD(NOW(), INTERVAL ' . self::DEVICE_DAYS . ' DAY))'
        );
    }

    public static function isTrustedDevice($idEmployee, $token)
    {
        $db = Db::getInstance();
        $db->execute('DELETE FROM ' . self::t('trusted_device') . ' WHERE expires_at <= NOW()');
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $token)) {
            return false;
        }

        return (bool) $db->getValue(
            'SELECT 1 FROM ' . self::t('trusted_device') . ' WHERE id_employee = ' . (int) $idEmployee
            . " AND token_hash = '" . hash('sha256', $token) . "' AND expires_at > NOW()",
            false
        );
    }

    public static function revokeDevices($idEmployee)
    {
        return Db::getInstance()->delete('bo2fa_trusted_device', 'id_employee = ' . (int) $idEmployee);
    }

    public static function getForcedProfiles()
    {
        $raw = (string) Configuration::getGlobalValue('BO2FA_FORCED_PROFILES');

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    public static function setForcedProfiles(array $ids)
    {
        $ids = array_unique(array_filter(array_map('intval', $ids)));

        return Configuration::updateGlobalValue('BO2FA_FORCED_PROFILES', implode(',', $ids));
    }

    public static function listEmployees()
    {
        return (array) Db::getInstance()->executeS(
            'SELECT e.id_employee, e.firstname, e.lastname, e.email, e.id_profile, pl.name AS profile_name,'
            . ' IFNULL(t.enabled, 0) AS enabled, IFNULL(t.forced, 0) AS forced,'
            . ' (SELECT COUNT(*) FROM ' . self::t('backup_code') . ' b WHERE b.id_employee = e.id_employee AND b.used_at IS NULL) AS codes_left,'
            . ' (SELECT COUNT(*) FROM ' . self::t('trusted_device') . ' d WHERE d.id_employee = e.id_employee AND d.expires_at > NOW()) AS devices'
            . ' FROM `' . _DB_PREFIX_ . 'employee` e'
            . ' LEFT JOIN ' . self::t('employee') . ' t ON t.id_employee = e.id_employee'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'profile_lang` pl ON pl.id_profile = e.id_profile AND pl.id_lang = ' . (int) Context::getContext()->language->id
            . ' ORDER BY e.lastname, e.firstname',
            true,
            false
        );
    }

    public static function log($message, $idEmployee, $severity = 1)
    {
        PrestaShopLogger::addLog('[bo2fa] ' . $message, (int) $severity, null, 'Employee', (int) $idEmployee, true);
    }
}
