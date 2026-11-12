<?php
if (!defined('_PS_VERSION_') && PHP_SAPI !== 'cli') {
    exit;
}

/* Codici monouso XXXX-XXXX: alfabeto senza I, O, 0, 1. Salvati solo come hash. */
class Bo2faBackupCodes
{
    const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const COUNT = 10;

    public static function generate($count = self::COUNT)
    {
        $codes = [];
        $max = strlen(self::ALPHABET) - 1;
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < 8; ++$i) {
                $raw .= self::ALPHABET[random_int(0, $max)];
            }
            $codes[$raw] = substr($raw, 0, 4) . '-' . substr($raw, 4);
        }

        return array_values($codes);
    }

    public static function normalize($input)
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $input));
    }

    public static function hash($code)
    {
        return password_hash(self::normalize($code), PASSWORD_DEFAULT);
    }
}
