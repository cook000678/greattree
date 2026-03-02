<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once( __DIR__ . '/core/adodb/adodb.inc.php');
spl_autoload_register(function ($class) {

    $dirs = [
        __DIR__ . '/core/',
        __DIR__ . '/Model/',
    ];

    $classPath = str_replace('\\', '/', $class) . '.php';

    foreach ($dirs as $dir) {
        $file = $dir . $classPath;

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

});