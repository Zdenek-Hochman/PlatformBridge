<?php
declare(strict_types=1);

namespace PlatformBridge\Config\DTO;

final readonly class SecurityDto {
    public function __construct(
        public string $configPath,
        public bool $useHmac,
        public ?int $paramsTtl,
    ) {}
}