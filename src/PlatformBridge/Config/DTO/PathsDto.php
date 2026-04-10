<?php
declare(strict_types=1);

namespace PlatformBridge\Config\DTO;

use PlatformBridge\Paths\PathResolver;

final readonly class PathsDto {
    public function __construct(
        public ?string $configPath,
        public ?string $viewsPath,
        public ?string $cachePath,
        public PathResolver $resolver,
    ) {}

	public function getConfigPath(): string
    {
		return $this->configPath ?? $this->resolver->configPath();
    }
}