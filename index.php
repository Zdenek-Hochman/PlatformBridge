<?php

// Pro standalone použití (testování, CLI)
if (php_sapi_name() === 'cli' || !defined('PLATFORM_BRIDGE_LOADED')) {
    require_once __DIR__ . '/vendor/autoload.php';
    define('PLATFORM_BRIDGE_LOADED', true);
}

// Vrátí namespace třídy pro require
return \PlatformBridge\PlatformBridge::class;