<?php

namespace PlatformBridge\Paths;

final class UrlResolver {

    /** Známé public root prefixy webserverů (document root variace). */
    private const PUBLIC_PREFIXES = ['public/', 'web/', 'www/', 'htdocs/', 'public_html/'];

	private PathResolver $paths;

    public function __construct(PathResolver $paths)
    {
        $this->paths = $paths;
    }

	public function assetUrl(): string
    {
        return $this->resolve($this->paths->assetsPath());
    }

    public function apiUrl(): string
    {
        return $this->resolve($this->paths->apiFile());
    }

    private function fromDocRoot(string $absolutePath): ?string
    {
        if (php_sapi_name() === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
            return null;
        }

        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }

        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));
        $abs = str_replace('\\', '/', rtrim($absolutePath, '/\\'));

        if (str_starts_with($abs, $docRoot . '/')) {
            return substr($abs, strlen($docRoot));
        }

        return null;
    }

	private function stripPublicPrefix(string $relativePath): string
    {
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return '/' . substr($relativePath, strlen($prefix));
            }
        }

        return '/' . ltrim($relativePath, '/');
    }

	private function resolve(string $targetPath): string
    {
        $url = $this->fromDocRoot($targetPath);

        if ($url !== null) {
            return $url;
        }

		return $this->stripPublicPrefix($targetPath);
    }
}