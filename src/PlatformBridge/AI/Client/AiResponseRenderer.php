<?php

declare(strict_types=1);

namespace PlatformBridge\AI\Client;

use PlatformBridge\Paths\PathResolver;
use PlatformBridge\Template\Engine;

/**
 * Třída zodpovědná za renderování AI odpovědí pomocí template engine.
 *
 * Tato třída odděluje logiku renderování od API handleru,
 * což umožňuje lepší testovatelnost a dodržuje Single Responsibility Principle.
 */
final class AiResponseRenderer
{
    public function __construct(private Engine $engine)
    {
    }

    /**
     * Vytvoří instanci s výchozí konfigurací template engine.
     *
     * Cesty se resolvují přes PathResolver:
     *   - Standalone: views a cache přímo z balíčku
     *   - Vendor: cache z {projectRoot}/var/cache/
     */
    public static function create(PathResolver $paths, array $engineConfig = []): self
    {
        $defaultConfig = [
            'tpl_dir'   => $paths->viewsPath() . '/',
            'cache_dir' => $paths->cachePath() . '/',
            'debug'     => false,
        ];

        $config = array_merge($defaultConfig, $engineConfig);

        return new self(new Engine($config));
    }

    /**
     * Renderuje AI odpověď pomocí šablony.
     *
     * @param object $response
     * @param string $template Šablona bez přípony
     * @param array<string,mixed> $extraVars
     */
    public function render($response, string $template, array $extraVars = []): string
    {
        $vars = [
            'response' => $response,
            ...$extraVars
        ];

        return $this->engine->assign($vars)->render($template);
    }

    /**
     * Přístup k engine pro pokročilé použití.
     */
    public function getEngine(): Engine
    {
        return $this->engine;
    }
}
