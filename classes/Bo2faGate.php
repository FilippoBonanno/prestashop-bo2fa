<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/* Decide per ogni richiesta BO se servono verifica o attivazione. Stato "verificato" = HMAC nel cookie employee. */
class Bo2faGate
{
    const WHITELIST = ['adminlogin', 'adminbo2faverify'];
    const DEVICE_COOKIE = 'bo2fa_td';

    /* null = continua; 'verify' o 'setup' = pagina dove mandare il dipendente. */
    public static function decide($controllerName)
    {
        // Fonte di verità: il cookie employee (in PS 9 riallineato alla sessione Symfony a ogni richiesta, priorità 7).
        $context = Context::getContext();
        $idEmployee = (int) $context->cookie->id_employee;
        $employee = $context->employee;
        if ($idEmployee && (!$employee || (int) $employee->id !== $idEmployee)) {
            $employee = new Employee($idEmployee);
        }
        if (!$idEmployee || !$employee || !$employee->id || !$employee->isLoggedBack()) {
            return null;
        }
        $name = Tools::strtolower((string) $controllerName);
        if (in_array($name, self::WHITELIST, true)) {
            return null;
        }
        $row = Bo2faRepository::getEmployee($employee->id);
        if (!Bo2faRepository::isEnabled($row)) {
            return ($name !== 'adminbo2fasetup' && Bo2faRepository::isRequired($employee, $row)) ? 'setup' : null;
        }
        // Con la verifica attiva, anche la pagina di gestione resta chiusa finché il codice non è stato inserito.
        if (self::isVerified($row)) {
            return null;
        }
        if (isset($_COOKIE[self::DEVICE_COOKIE]) && is_string($_COOKIE[self::DEVICE_COOKIE])
            && Bo2faRepository::isTrustedDevice($employee->id, $_COOKIE[self::DEVICE_COOKIE])) {
            self::markVerified($row);

            return null;
        }

        return 'verify';
    }

    public static function isVerified(array $row)
    {
        $flag = (string) Context::getContext()->cookie->bo2fa_ok;
        $expected = self::fingerprint($row);

        return $flag !== '' && $expected !== '' && hash_equals($expected, $flag);
    }

    public static function markVerified(array $row)
    {
        $cookie = Context::getContext()->cookie;
        $cookie->bo2fa_ok = self::fingerprint($row);
        $cookie->write();
    }

    /* Legato a sessione PS e data di attivazione: un reset o un nuovo login lo invalidano. '' = sessione assente, mai valido. */
    private static function fingerprint(array $row)
    {
        $cookie = Context::getContext()->cookie;
        if ((string) $cookie->session_id === '' || (string) $cookie->session_token === '') {
            return '';
        }

        return hash_hmac(
            'sha256',
            $row['id_employee'] . '|' . $cookie->session_id . '|' . $cookie->session_token . '|' . $row['date_enabled'],
            _COOKIE_KEY_
        );
    }

    public static function redirectUrl($target)
    {
        return Context::getContext()->link->getAdminLink($target === 'setup' ? 'AdminBo2faSetup' : 'AdminBo2faVerify');
    }

    // Token anti-CSRF dei form del modulo: casuale, uno per sessione, nel cookie admin (cifrato da PrestaShop).
    // Non uso Tools::getAdminTokenLite: da PS 9 restituisce un token Symfony diverso a ogni chiamata.
    public static function csrfToken()
    {
        $cookie = Context::getContext()->cookie;
        if (empty($cookie->bo2fa_csrf)) {
            $cookie->bo2fa_csrf = bin2hex(random_bytes(16));
            $cookie->write();
        }

        return (string) $cookie->bo2fa_csrf;
    }

    public static function csrfValid()
    {
        return hash_equals(self::csrfToken(), (string) Tools::getValue('bo2fa_csrf'));
    }

    public static function logoutUrl()
    {
        $link = Context::getContext()->link;
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            return $link->getAdminLink('AdminLogin', true, ['route' => 'admin_logout'], ['logout' => 1]);
        }

        return $link->getAdminLink('AdminLogin', true, [], ['logout' => 1]);
    }

    public static function setDeviceCookie($token)
    {
        header(
            'Set-Cookie: ' . self::DEVICE_COOKIE . '=' . $token
            . '; Max-Age=' . (86400 * Bo2faRepository::DEVICE_DAYS)
            . '; Path=' . self::cookiePath() . '; HttpOnly; SameSite=Lax'
            . (Tools::usingSecureMode() ? '; Secure' : ''),
            false
        );
    }

    public static function clearDeviceCookie()
    {
        header('Set-Cookie: ' . self::DEVICE_COOKIE . '=deleted; Max-Age=0; Path=' . self::cookiePath() . '; HttpOnly; SameSite=Lax', false);
    }

    private static function cookiePath()
    {
        return __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/';
    }

    public static function isAjaxRequest()
    {
        return Tools::getValue('ajax')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && Tools::strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
