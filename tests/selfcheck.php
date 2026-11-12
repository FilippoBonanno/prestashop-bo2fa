<?php
// Test CLI: php bo2fa/tests/selfcheck.php  (exit 0 = tutto ok)
if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../classes/Bo2faTotp.php';
if (is_file(__DIR__ . '/../classes/Bo2faBackupCodes.php')) {
    require __DIR__ . '/../classes/Bo2faBackupCodes.php';
}

$failures = 0;
function check($label, $ok)
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        ++$failures;
    }
}

// Base32 (RFC 4648) sulla chiave dei vettori RFC 6238
$secret = Bo2faTotp::base32Encode('12345678901234567890');
check('base32 encode', $secret === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
check('base32 roundtrip', Bo2faTotp::base32Decode($secret) === '12345678901234567890');
check('base32 accetta minuscole e spazi', Bo2faTotp::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq') === '12345678901234567890');
check('base32 rifiuta caratteri non validi', Bo2faTotp::base32Decode('ABC1') === false);

// Vettori RFC 6238, appendice B, SHA1, 8 cifre
$vectors = [
    59 => '94287082',
    1111111109 => '07081804',
    1111111111 => '14050471',
    1234567890 => '89005924',
    2000000000 => '69279037',
    20000000000 => '65353130',
];
foreach ($vectors as $t => $expected) {
    check('rfc6238 T=' . $t, Bo2faTotp::code($secret, Bo2faTotp::currentStep($t), 8) === $expected);
}

// Verifica con finestra ±1 e anti-riuso
$t = 1234567890;
$step = Bo2faTotp::currentStep($t);
$code = Bo2faTotp::code($secret, $step);
check('codice 6 cifre', strlen($code) === 6);
check('verify intervallo corrente', Bo2faTotp::verify($secret, $code, 0, $t) === $step);
check('verify intervallo precedente', Bo2faTotp::verify($secret, Bo2faTotp::code($secret, $step - 1), 0, $t) === $step - 1);
check('verify intervallo successivo', Bo2faTotp::verify($secret, Bo2faTotp::code($secret, $step + 1), 0, $t) === $step + 1);
check('rifiuta fuori finestra', Bo2faTotp::verify($secret, Bo2faTotp::code($secret, $step - 2), 0, $t) === false);
check('rifiuta riuso (step <= last_step)', Bo2faTotp::verify($secret, $code, $step, $t) === false);
check('rifiuta lunghezza sbagliata', Bo2faTotp::verify($secret, '12345', 0, $t) === false);
check('accetta spazio in mezzo', Bo2faTotp::verify($secret, substr($code, 0, 3) . ' ' . substr($code, 3), 0, $t) === $step);

// Chiave generata e URI
check('chiave generata: 32 caratteri base32', preg_match('/^[A-Z2-7]{32}$/', Bo2faTotp::generateSecret()) === 1);
$uri = Bo2faTotp::uri('ABC', 'Negozio BO (Aziende)', 'a@b.it');
check('uri otpauth', strpos($uri, 'otpauth://totp/Negozio%20BO%20%28Aziende%29%3Aa%40b.it?secret=ABC&issuer=Negozio%20BO%20%28Aziende%29') === 0);

if (class_exists('Bo2faBackupCodes')) {
    $codes = Bo2faBackupCodes::generate();
    check('10 codici backup unici', count($codes) === 10 && count(array_unique($codes)) === 10);
    check('formato XXXX-XXXX senza caratteri ambigui', preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $codes[0]) === 1);
    check('normalize', Bo2faBackupCodes::normalize(' abcd-efgh ') === 'ABCDEFGH');
    check('hash + password_verify', password_verify(Bo2faBackupCodes::normalize(strtolower($codes[0])), Bo2faBackupCodes::hash($codes[0])));
}

echo $failures ? $failures . ' FAILED' . PHP_EOL : 'ALL OK' . PHP_EOL;
exit($failures ? 1 : 0);
