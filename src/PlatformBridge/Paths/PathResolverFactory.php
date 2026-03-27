<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Paths;

/**
 * Factory pro automatickou detekci vendor/standalone režimu a vytvoření PathResolveru.
 */
final class PathResolverFactory
{
    public static function auto(string $packageRoot): PathResolver
    {
        $isVendor = self::detectVendor($packageRoot);

        $projectRoot = $isVendor ? dirname($packageRoot, 3) : $packageRoot;

        $config = PathsLoader::load($projectRoot);

        return new PathResolver(
            packageRoot: $packageRoot,
            projectRoot: $projectRoot,
            config: $config,
        );
    }

    private static function detectVendor(string $packageRoot): bool
    {
        $autoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';

        return file_exists($autoload) && realpath($packageRoot) !== realpath(dirname($packageRoot, 3));
    }
}