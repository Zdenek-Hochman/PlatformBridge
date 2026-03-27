<?php

/**
 * PlatformBridge – Konfigurace API připojení
 *
 * Tento soubor byl vygenerován příkazem:
 *   php vendor/bin/platformbridge install
 *
 * Nastavuje adresu a parametry API, na které se odkazuje AJAX z frontendu.
 * Při install se kopíruje do {projectRoot}/public/bridge-config.php.
 * Tento soubor se NEPŘEPISUJE při composer update.
 *
 * ⚠️  Bezpečnostní klíče (secretKey, ttl) jsou v samostatném souboru
 *     security-config.php, ke kterému má přístup pouze interní jádro.
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // ─── AI Provider ────────────────────────────────────────────
    // API klíč pro autentizaci vůči AI provideru
    'api_key'     => 'CHANGE-ME-your-api-key-here',

    // Timeout HTTP požadavku na AI v sekundách
    'timeout'     => 30,

    // Počet opakování při selhání požadavku
    'max_retries' => 3,

    // URL AI API endpointu, kam se odesílají AJAX požadavky
    // Nastavte na konkrétní URL vašeho prostředí (nedoporučuje se dynamická detekce z $_SERVER)
    'base_url'    => 'https://your-domain.com/platformbridge/api.php',

    // ─── Endpointy ──────────────────────────────────────────────
    // Registrace vlastních AI endpointů.
    //
    // K dispozici jsou 3 úrovně konfigurace — od nejjednodušší po nejflexibilnější:
    //
    // ── Úroveň 1: Deklarativní pole (doporučeno pro většinu případů) ──
    //
    // Povinné klíče:
    //   generator_id    (string|null) — ID generátoru v generators.json
    //   response_type   (string)      — Typ odpovědi: 'string' | 'array' | 'nested'
    //   template        (string)      — Cesta k šabloně pro renderování
    //
    // Volitelné klíče:
    //   variant_key     (string|null) — Klíč ve vstupu pro detekci varianty
    //   variants        (array)       — Deklarativní pravidla dle varianty:
    //       'remove_fields' => ['pole_k_odstraneni'],
    //       'keep_fields'   => ['pole_k_ponechani'],
    //       'defaults'      => ['pole' => 'výchozí_hodnota'],
    //
    // Příklad:
    //   'CreateSubject' => [
    //       'generator_id'  => 'subject',
    //       'response_type' => 'nested',
    //       'template'      => '/Components/NestedResult',
    //       'variant_key'   => 'type',
    //       'variants'      => [
    //           'template' => ['remove_fields' => ['topic_source']],
    //           'custom'   => ['remove_fields' => ['template_id', 'topic_source']],
    //       ],
    //   ],
    //
    // ── Úroveň 2: Callable transform (pro složitější logiku transformace) ──
    //
    // Přidejte klíč 'transform' s callable — má NEJVYŠŠÍ prioritu (přepíše 'variants').
    // Funguje stejně jako dřívější metoda transformInput() — můžete ohýbat výstup libovolně.
    //
    // Příklad:
    //   'CreateSubject' => [
    //       'generator_id'  => 'subject',
    //       'response_type' => 'nested',
    //       'template'      => '/Components/NestedResult',
    //       'variant_key'   => 'type',
    //       'transform'     => function(array $input, mixed ...$context): array {
    //           $variant = $context[0] ?? null;  // 'template', 'custom', ...
    //           if ($variant === 'template') {
    //               unset($input['topic_source']);
    //               $input['mode'] = 'guided';
    //           }
    //           return $input;
    //       },
    //   ],
    //
    // ── Úroveň 3: Vlastní třída (maximum flexibility) ──
    //
    // Pro pokročilé případy — vlastní třída dědící z EndpointDefinition.
    // Umožňuje vytvářet libovolné metody (transformTemplateVariant, transformCustomVariant atd.)
    // a plně kontrolovat chování endpointu.
    //
    // Příklad:
    //   'CreateSubject' => \App\Endpoints\CreateSubjectEndpoint::class,
    //
    'endpoints' => [],
];