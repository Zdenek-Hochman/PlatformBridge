<?php

/**
 * Demo / Testovací skript pro PlatformBridge
 *
 * Tento soubor ukazuje, jak používat knihovnu lokálně pro vývoj.
 * V produkci by se knihovna používala jako Composer balíček.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Zoom\PlatformBridge\PlatformBridge;

// ============================================================================
// PŘÍKLAD 1: Vytvoření instance s výchozí konfigurací
// ============================================================================

// $bridge = PlatformBridge::createDefault();

// ============================================================================
// PŘÍKLAD 2: Vytvoření instance s vlastní konfigurací
// ============================================================================
$bridge = PlatformBridge::create()
    // Pro standalone režim: explicitní cesty ke konfiguraci
    // Ve vendor režimu se cesty resolví automaticky přes PathResolver
    ->withConfigPath(__DIR__ . '/resources/defaults')
    ->withViewsPath(__DIR__ . '/resources/views')
    ->withCachePath(__DIR__ . '/var/cache')
    // ->withLocale('cs');
    // HMAC podepisování - zapnout/vypnout (secret key se načítá z bridge-config.php)
    ->withSecretKey(true)
    // Volitelně: expirace podepsaných dat (v sekundách) - přepíše hodnotu z bridge-config.php
    // ->withParamsTtl(3600) // 1 hodina
    ->build();

// ============================================================================
// POUŽITÍ
// ============================================================================

// // Získání parametrů z URL (příklad)
$generatorId = $_GET['generator'] ?? 'subject';

$html = $bridge->renderFullForm($generatorId, [
    'get' => [ 'web_id' => 1157 ],
    'body' => [ 'client_id' => 633 ],
    'headers' => [ 'X-Custom-Header' => 'MyValue' ],
    // Dynamické hodnoty propsané do formulářových bloků (match podle 'name' nebo 'id')
    'inject' => [ 'template_id' => 42 ],
]);

echo $html;
