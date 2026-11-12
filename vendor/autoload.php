<?php
// Autoloader del modulo: AppKernel lo include per i moduli attivi, bo2fa.php lo include sempre.
spl_autoload_register(function ($class) {
    if (strpos($class, 'Bo2fa') !== 0) {
        return;
    }
    $file = dirname(__DIR__) . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
