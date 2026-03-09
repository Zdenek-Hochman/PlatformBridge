# :robot: Platform Bridge

Middleware platforma pro integraci interní aplikace se Zoom API, umožňující dynamické generování UI formulářů z JSON konfigurace.

## Instalace
```bash
composer require zoom/platform-bridge
```

---

### Požadavky
- PHP 8.3+

---

## :zap:Použití

### Inicializace

> :warning: **Upozornění:** Nepoužívejte slouží primárně pro testování

```php
require_once __DIR__ . '/vendor/autoload.php';
use Zoom\PlatformBridge\PlatformBridge;

//Vytvoření instance s výchozí konfigurací
PlatformBridge::createDefault();
```

<sub>Rozšířené použití</sub>,

```php
require_once __DIR__ . '/vendor/autoload.php';
use Zoom\PlatformBridge\PlatformBridge;

$bridge = PlatformBridge::create()
    ->withConfigPath(__DIR__ . '/resources/config/defaults')
    ->withViewsPath(__DIR__ . '/resources/views')
    ->withCachePath(__DIR__ . '/var/cache')
    ->withTranslationsPath(__DIR__ . '/resources/translations');
    ->withLocale('cs');
    ->withSecretKey(true)
    ->withParamsTtl(3600) // 1 hodina
    ->build();

```
