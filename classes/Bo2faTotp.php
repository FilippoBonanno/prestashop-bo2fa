<?php
if (!defined('_PS_VERSION_') && PHP_SAPI !== 'cli') {
    exit;
}

/* TOTP RFC 6238 (SHA1, 6 cifre, 30 s) e Base32 RFC 4648, senza dipendenze. */
class Bo2faTotp
{
    const PERIOD = 30;
    const DIGITS = 6;
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret()
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function base32Encode($binary)
    {
        $bits = '';
        $len = strlen($binary);
        for ($i = 0; $i < $len; ++$i) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    /* Ritorna la stringa binaria, oppure false se il testo contiene caratteri non Base32. */
    public static function base32Decode($text)
    {
        $text = strtoupper(preg_replace('/[\s=]/', '', (string) $text));
        $bits = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; ++$i) {
            $pos = strpos(self::ALPHABET, $text[$i]);
            if ($pos === false) {
                return false;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    public static function currentStep($time = null)
    {
        return (int) floor(($time === null ? time() : $time) / self::PERIOD);
    }

    public static function code($secretBase32, $step, $digits = self::DIGITS)
    {
        $key = self::base32Decode($secretBase32);
        $counter = pack('N', ($step >> 32) & 0xFFFFFFFF) . pack('N', $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (int) pow(10, $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /* Ritorna lo step accettato oppure false. Gli step <= $lastStep sono rifiutati (anti-riuso). */
    public static function verify($secretBase32, $code, $lastStep, $time = null)
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $now = self::currentStep($time);
        for ($offset = -1; $offset <= 1; ++$offset) {
            $step = $now + $offset;
            if ($step <= (int) $lastStep) {
                continue;
            }
            if (hash_equals(self::code($secretBase32, $step), $code)) {
                return $step;
            }
        }

        return false;
    }

    public static function uri($secretBase32, $issuer, $account)
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secretBase32
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }
}
